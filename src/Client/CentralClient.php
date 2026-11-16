<?php

namespace Applu\CentralBridge\Client;

use Applu\CentralBridge\Exceptions\CentralApiException;
use Applu\CentralBridge\Headers;
use Applu\CentralBridge\Signature;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use LogicException;

/**
 * Client for the Central invoicing API (contract §9). Same token and HMAC
 * scheme as the Bridge, plus X-Central-Product. Writes need an idempotency key.
 */
class CentralClient
{
    private const INVOICES_PATH = '/api/v1/invoices';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly string $product,
        private readonly int $timeoutSeconds = 30,
    ) {
        if ($baseUrl === '' || $token === '' || $product === '') {
            throw new LogicException(
                'CentralClient precisa de CENTRAL_URL, CENTRAL_BRIDGE_TOKEN e CENTRAL_BRIDGE_PRODUCT configurados.',
            );
        }
    }

    public static function fromConfig(): self
    {
        return new self(
            (string) config('central-bridge.central_url', ''),
            (string) config('central-bridge.token', ''),
            (string) config('central-bridge.product', ''),
        );
    }

    /**
     * Issues an NFS-e.
     *
     * @param  array{taker: array{document: string, name: string, email?: string}, amount_cents: int, description: string, service_code?: string, reference?: string}  $invoice
     * @return array<mixed>
     */
    public function issueInvoice(array $invoice, string $idempotencyKey): array
    {
        return $this->send('POST', self::INVOICES_PATH, $invoice, $idempotencyKey)->json() ?? [];
    }

    /**
     * @return array<mixed>
     */
    public function listInvoices(int $page = 1): array
    {
        return $this->send('GET', self::INVOICES_PATH, query: ['page' => max(1, $page)])->json() ?? [];
    }

    /**
     * @return array<mixed>
     */
    public function getInvoice(string|int $id): array
    {
        return $this->send('GET', $this->invoicePath($id))->json() ?? [];
    }

    /**
     * DANFSe PDF bytes.
     */
    public function invoicePdf(string|int $id): string
    {
        return $this->send('GET', $this->invoicePath($id, '/pdf'), accept: 'application/pdf')->body();
    }

    /**
     * Authorized XML.
     */
    public function invoiceXml(string|int $id): string
    {
        return $this->send('GET', $this->invoicePath($id, '/xml'), accept: 'application/xml')->body();
    }

    /**
     * @param  string  $reason  15-255 characters
     * @return array<mixed>
     */
    public function cancelInvoice(string|int $id, string $reason, string $idempotencyKey): array
    {
        return $this->send('POST', $this->invoicePath($id, '/cancel'), ['reason' => $reason], $idempotencyKey)->json() ?? [];
    }

    private function invoicePath(string|int $id, string $suffix = ''): string
    {
        return self::INVOICES_PATH.'/'.rawurlencode((string) $id).$suffix;
    }

    /**
     * @param  array<mixed>|null  $payload
     * @param  array<string, scalar>  $query
     */
    private function send(
        string $method,
        string $path,
        ?array $payload = null,
        ?string $idempotencyKey = null,
        array $query = [],
        string $accept = 'application/json',
    ): Response {
        $base = rtrim($this->baseUrl, '/');
        $basePath = rtrim((string) parse_url($base, PHP_URL_PATH), '/');
        $queryString = $query === [] ? '' : '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        $pathWithQuery = $basePath.$path.$queryString;
        $url = $base.$path.$queryString;

        $body = $payload === null
            ? ''
            : (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $timestamp = (string) Carbon::now()->getTimestamp();

        $headers = [
            'Accept' => $accept,
            Headers::TIMESTAMP => $timestamp,
            Headers::SIGNATURE => Signature::make($this->token, $method, $pathWithQuery, $timestamp, $body),
            Headers::PRODUCT => $this->product,
        ];

        if ($idempotencyKey !== null) {
            $headers[Headers::IDEMPOTENCY_KEY] = $idempotencyKey;
        }

        $request = Http::withHeaders($headers)->timeout($this->timeoutSeconds);

        if ($payload !== null) {
            $request = $request->withBody($body, 'application/json');
        }

        try {
            $response = $request->send($method, $url);
        } catch (ConnectionException $e) {
            throw new CentralApiException(
                CentralApiException::CONNECTION_FAILED,
                'Não foi possível conectar à Central.',
                0,
                null,
                $e,
            );
        }

        if (! $response->successful()) {
            $this->throwFor($response);
        }

        return $response;
    }

    private function throwFor(Response $response): never
    {
        $json = $response->json();
        $json = is_array($json) ? $json : null;
        $error = is_array($json['error'] ?? null) ? $json['error'] : [];

        throw new CentralApiException(
            is_string($error['code'] ?? null) ? $error['code'] : CentralApiException::UNEXPECTED_RESPONSE,
            is_string($error['message'] ?? null)
                ? $error['message']
                : sprintf('A Central respondeu com o status HTTP %d.', $response->status()),
            $response->status(),
            $json,
        );
    }
}
