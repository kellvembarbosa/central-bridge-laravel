<?php

namespace Applu\CentralBridge\Tests\Feature;

use Applu\CentralBridge\Client\CentralClient;
use Applu\CentralBridge\Exceptions\CentralApiException;
use Applu\CentralBridge\Headers;
use Applu\CentralBridge\Signature;
use Applu\CentralBridge\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use LogicException;

class CentralClientTest extends TestCase
{
    private const NOW = 1763200000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::createFromTimestamp(self::NOW));
    }

    private function client(): CentralClient
    {
        return $this->app->make(CentralClient::class);
    }

    private function assertSignedRequest(Request $request, string $method, string $pathWithQuery): bool
    {
        $this->assertSame($method, $request->method());
        $this->assertSame((string) self::NOW, $request->header(Headers::TIMESTAMP)[0]);
        $this->assertSame('demo', $request->header(Headers::PRODUCT)[0]);
        $this->assertSame(
            Signature::make(self::TOKEN, $method, $pathWithQuery, self::NOW, $request->body()),
            $request->header(Headers::SIGNATURE)[0],
        );

        return true;
    }

    public function test_issue_invoice_signs_body_and_sends_idempotency_key(): void
    {
        Http::fake(['central.test/*' => Http::response(['data' => ['id' => 9, 'status' => 'processing']], 201)]);

        $payload = [
            'taker' => ['document' => '12345678000195', 'name' => 'ACME Ação', 'email' => 'nf@acme.test'],
            'amount_cents' => 1990,
            'description' => 'Assinatura mensal',
            'reference' => 'pi_1',
        ];

        $result = $this->client()->issueInvoice($payload, 'issue-pi_1');

        $this->assertSame(['data' => ['id' => 9, 'status' => 'processing']], $result);

        Http::assertSent(function (Request $request) use ($payload) {
            $this->assertSame('https://central.test/api/v1/invoices', $request->url());
            $this->assertSame('issue-pi_1', $request->header(Headers::IDEMPOTENCY_KEY)[0]);
            $this->assertSame($payload, json_decode($request->body(), true));
            $this->assertStringContainsString('Ação', $request->body());
            $this->assertStringStartsWith('application/json', $request->header('Content-Type')[0]);

            return $this->assertSignedRequest($request, 'POST', '/api/v1/invoices');
        });
    }

    public function test_list_invoices_signs_path_with_query(): void
    {
        Http::fake(['central.test/*' => Http::response(['data' => [], 'meta' => []])]);

        $this->client()->listInvoices(2);

        Http::assertSent(function (Request $request) {
            $this->assertSame('https://central.test/api/v1/invoices?page=2', $request->url());
            $this->assertSame('', $request->body());
            $this->assertFalse($request->hasHeader(Headers::IDEMPOTENCY_KEY));

            return $this->assertSignedRequest($request, 'GET', '/api/v1/invoices?page=2');
        });
    }

    public function test_get_invoice(): void
    {
        Http::fake(['central.test/*' => Http::response(['data' => ['id' => 7]])]);

        $this->assertSame(['data' => ['id' => 7]], $this->client()->getInvoice(7));

        Http::assertSent(fn (Request $request) => $this->assertSignedRequest($request, 'GET', '/api/v1/invoices/7'));
    }

    public function test_pdf_and_xml_return_raw_bytes(): void
    {
        Http::fake([
            'central.test/api/v1/invoices/7/pdf' => Http::response('%PDF-1.7 bytes', 200, ['Content-Type' => 'application/pdf']),
            'central.test/api/v1/invoices/7/xml' => Http::response('<NFSe/>', 200, ['Content-Type' => 'application/xml']),
        ]);

        $this->assertSame('%PDF-1.7 bytes', $this->client()->invoicePdf(7));
        $this->assertSame('<NFSe/>', $this->client()->invoiceXml(7));

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/pdf')
            && $r->header('Accept')[0] === 'application/pdf'
            && $this->assertSignedRequest($r, 'GET', '/api/v1/invoices/7/pdf'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/xml')
            && $this->assertSignedRequest($r, 'GET', '/api/v1/invoices/7/xml'));
    }

    public function test_cancel_invoice(): void
    {
        Http::fake(['central.test/*' => Http::response(['data' => ['id' => 7, 'status' => 'canceled']])]);

        $this->client()->cancelInvoice(7, 'Cobrança emitida em duplicidade', 'cancel-7');

        Http::assertSent(function (Request $request) {
            $this->assertSame('https://central.test/api/v1/invoices/7/cancel', $request->url());
            $this->assertSame(['reason' => 'Cobrança emitida em duplicidade'], json_decode($request->body(), true));
            $this->assertSame('cancel-7', $request->header(Headers::IDEMPOTENCY_KEY)[0]);

            return $this->assertSignedRequest($request, 'POST', '/api/v1/invoices/7/cancel');
        });
    }

    public function test_base_url_path_is_part_of_the_signed_path(): void
    {
        config(['central-bridge.central_url' => 'https://central.test/gestao/']);
        Http::fake(['central.test/*' => Http::response(['data' => []])]);

        $this->client()->getInvoice('abc');

        Http::assertSent(function (Request $request) {
            $this->assertSame('https://central.test/gestao/api/v1/invoices/abc', $request->url());

            return $this->assertSignedRequest($request, 'GET', '/gestao/api/v1/invoices/abc');
        });
    }

    public function test_error_envelope_becomes_typed_exception(): void
    {
        Http::fake(['central.test/*' => Http::response(
            ['error' => ['code' => 'validation_failed', 'message' => 'Dados inválidos.']],
            422,
        )]);

        try {
            $this->client()->issueInvoice(['amount_cents' => 0], 'k');
            $this->fail('Expected CentralApiException');
        } catch (CentralApiException $e) {
            $this->assertSame('validation_failed', $e->errorCode);
            $this->assertSame('Dados inválidos.', $e->getMessage());
            $this->assertSame(422, $e->status);
            $this->assertSame('validation_failed', $e->body['error']['code']);
        }
    }

    public function test_non_json_error_still_throws(): void
    {
        Http::fake(['central.test/*' => Http::response('Bad Gateway', 502)]);

        try {
            $this->client()->getInvoice(1);
            $this->fail('Expected CentralApiException');
        } catch (CentralApiException $e) {
            $this->assertSame(CentralApiException::UNEXPECTED_RESPONSE, $e->errorCode);
            $this->assertSame(502, $e->status);
            $this->assertNull($e->body);
        }
    }

    public function test_connection_failure_throws(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        try {
            $this->client()->listInvoices();
            $this->fail('Expected CentralApiException');
        } catch (CentralApiException $e) {
            $this->assertSame(CentralApiException::CONNECTION_FAILED, $e->errorCode);
            $this->assertSame(0, $e->status);
        }
    }

    public function test_missing_configuration_fails_fast(): void
    {
        config(['central-bridge.central_url' => null]);

        $this->expectException(LogicException::class);

        $this->client();
    }
}
