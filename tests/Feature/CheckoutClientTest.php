<?php

namespace Applu\CentralBridge\Tests\Feature;

use Applu\CentralBridge\Client\CentralClient;
use Applu\CentralBridge\Data\NewPayment;
use Applu\CentralBridge\Data\PaymentRecord;
use Applu\CentralBridge\Data\Plan;
use Applu\CentralBridge\Exceptions\CentralApiException;
use Applu\CentralBridge\Headers;
use Applu\CentralBridge\Signature;
use Applu\CentralBridge\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class CheckoutClientTest extends TestCase
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

    private function assertSigned(Request $request, string $method, string $path): bool
    {
        $this->assertSame($method, $request->method());
        $this->assertSame('demo', $request->header(Headers::PRODUCT)[0]);
        $this->assertSame(
            Signature::make(self::TOKEN, $method, $path, self::NOW, $request->body()),
            $request->header(Headers::SIGNATURE)[0],
        );

        return true;
    }

    public function test_list_plans(): void
    {
        $v = self::vectors('payment_event.json');
        Http::fake(['central.test/*' => Http::response(['data' => [$v['plan_wire']]])]);

        $plans = $this->client()->listPlans();

        $this->assertCount(1, $plans);
        $this->assertInstanceOf(Plan::class, $plans[0]);
        $this->assertSame(1090, $plans[0]->amountCents);
        $this->assertSame(['Simulado ilimitado'], $plans[0]->features);

        Http::assertSent(function (Request $request) {
            $this->assertSame('https://central.test/api/v1/plans', $request->url());

            return $this->assertSigned($request, 'GET', '/api/v1/plans');
        });
    }

    public function test_create_payment_sends_wire_body_and_key(): void
    {
        $v = self::vectors('payment_event.json');
        Http::fake(['central.test/*' => Http::response(['data' => $v['payment_wire']], 201)]);

        $payment = $this->client()->createPayment(
            new NewPayment('vitalicio', 'Ana Souza', 'ana@cliente.test', '42', method: 'pix', tracking: ['utm_source' => 'google']),
            'checkout-42-1',
        );

        $this->assertInstanceOf(PaymentRecord::class, $payment);
        $this->assertSame('00020101021226...6304ABCD', $payment->pix->copyPaste);

        Http::assertSent(function (Request $request) {
            $this->assertSame('https://central.test/api/v1/payments', $request->url());
            $this->assertSame('checkout-42-1', $request->header(Headers::IDEMPOTENCY_KEY)[0]);
            $this->assertSame([
                'plan' => 'vitalicio',
                'customer' => ['name' => 'Ana Souza', 'email' => 'ana@cliente.test'],
                'product_user_ref' => '42',
                'method' => 'pix',
                'tracking' => ['utm_source' => 'google'],
            ], json_decode($request->body(), true));

            return $this->assertSigned($request, 'POST', '/api/v1/payments');
        });
    }

    public function test_get_payment_encodes_id(): void
    {
        $v = self::vectors('payment_event.json');
        Http::fake(['central.test/*' => Http::response(['data' => $v['event_wire']['payment']])]);

        $this->assertTrue($this->client()->getPayment('a/b c')->isPaid());

        Http::assertSent(fn (Request $request) => $this->assertSigned($request, 'GET', '/api/v1/payments/a%2Fb%20c'));
    }

    public function test_central_errors_and_malformed_answers_throw(): void
    {
        Http::fake([
            'central.test/api/v1/payments' => Http::response(
                ['error' => ['code' => 'plan_unavailable', 'message' => 'Plano indisponível.']],
                422,
            ),
            'central.test/api/v1/payments/*' => Http::response(['data' => ['id' => 'x']]),
        ]);

        try {
            $this->client()->createPayment(['plan' => 'x'], 'k');
            $this->fail('expected exception');
        } catch (CentralApiException $e) {
            $this->assertSame('plan_unavailable', $e->errorCode);
            $this->assertSame(422, $e->status);
        }

        try {
            $this->client()->getPayment('x');
            $this->fail('expected exception');
        } catch (CentralApiException $e) {
            $this->assertSame(CentralApiException::UNEXPECTED_RESPONSE, $e->errorCode);
        }
    }
}
