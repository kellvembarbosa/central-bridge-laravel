<?php

namespace Applu\CentralBridge\Tests\Feature;

use Applu\CentralBridge\Headers;
use Applu\CentralBridge\Signature;
use Applu\CentralBridge\Tests\TestCase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;

class SignatureMiddlewareTest extends TestCase
{
    private const HEALTH = '/central-api/v1/health';

    public function test_valid_signature_passes(): void
    {
        $this->signed('GET', self::HEALTH)->assertOk()->assertJsonPath('product', 'demo');
    }

    public function test_disabled_bridge_answers_404_even_when_signed(): void
    {
        config(['central-bridge.enabled' => false]);

        $this->assertError($this->signed('GET', self::HEALTH), 404, 'not_found', 'Recurso não encontrado.');
    }

    public function test_disabled_check_runs_before_ip_check(): void
    {
        config(['central-bridge.enabled' => false, 'central-bridge.allowed_ips' => ['10.9.9.9']]);

        $this->assertError($this->signed('GET', self::HEALTH), 404, 'not_found');
    }

    public function test_ip_outside_allow_list_is_403(): void
    {
        config(['central-bridge.allowed_ips' => ['10.0.0.1']]);

        $this->assertError($this->signed('GET', self::HEALTH), 403, 'ip_not_allowed', 'Endereço IP não autorizado.');
    }

    public function test_ip_check_runs_before_authentication(): void
    {
        config(['central-bridge.allowed_ips' => ['10.0.0.1']]);

        $this->assertError($this->rawCall('GET', self::HEALTH), 403, 'ip_not_allowed');
    }

    public function test_ip_inside_allow_list_or_cidr_passes(): void
    {
        config(['central-bridge.allowed_ips' => ['10.0.0.1', '127.0.0.0/8']]);

        $this->signed('GET', self::HEALTH)->assertOk();
    }

    public function test_missing_headers_are_unauthorized(): void
    {
        $this->assertError(
            $this->rawCall('GET', self::HEALTH),
            401,
            'unauthorized',
            'Requisição sem autenticação válida da Central.',
        );
    }

    public function test_missing_signature_is_unauthorized(): void
    {
        $this->assertError(
            $this->rawCall('GET', self::HEALTH, '', [Headers::TIMESTAMP => (string) time()]),
            401,
            'unauthorized',
        );
    }

    public static function badTimestamps(): array
    {
        return [
            'missing' => [null],
            'not digits' => ['17632e5'],
            'negative' => ['-1763200000'],
            'thirteen digits' => ['1763200000000'],
            'empty' => [''],
        ];
    }

    #[DataProvider('badTimestamps')]
    public function test_invalid_timestamp_is_unauthorized(?string $timestamp): void
    {
        $headers = [Headers::SIGNATURE => str_repeat('a', 64)];

        if ($timestamp !== null) {
            $headers[Headers::TIMESTAMP] = $timestamp;
        }

        $this->assertError($this->rawCall('GET', self::HEALTH, '', $headers), 401, 'unauthorized');
    }

    public function test_missing_configured_token_is_unauthorized(): void
    {
        config(['central-bridge.token' => '']);

        $this->assertError($this->signed('GET', self::HEALTH, token: ''), 401, 'unauthorized');
    }

    public function test_old_timestamp_is_skew(): void
    {
        $this->assertError(
            $this->signed('GET', self::HEALTH, timestamp: Carbon::now()->getTimestamp() - 301),
            401,
            'timestamp_skew',
            'Requisição expirada: o relógio difere em mais de 5 minutos.',
        );
    }

    public function test_future_timestamp_is_skew(): void
    {
        $this->assertError(
            $this->signed('GET', self::HEALTH, timestamp: Carbon::now()->getTimestamp() + 301),
            401,
            'timestamp_skew',
        );
    }

    public function test_skew_is_checked_before_signature(): void
    {
        $headers = [
            Headers::TIMESTAMP => (string) (Carbon::now()->getTimestamp() - 1000),
            Headers::SIGNATURE => 'garbage',
        ];

        $this->assertError($this->rawCall('GET', self::HEALTH, '', $headers), 401, 'timestamp_skew');
    }

    public function test_wrong_token_is_signature_invalid(): void
    {
        $this->assertError(
            $this->signed('GET', self::HEALTH, token: 'another-token'),
            401,
            'signature_invalid',
            'Assinatura da Central inválida.',
        );
    }

    public function test_tampered_query_is_signature_invalid(): void
    {
        $timestamp = Carbon::now()->getTimestamp();
        $headers = [
            Headers::TIMESTAMP => (string) $timestamp,
            Headers::SIGNATURE => Signature::make(self::TOKEN, 'GET', '/central-api/v1/users?page=1', $timestamp, ''),
        ];

        $this->assertError(
            $this->rawCall('GET', '/central-api/v1/users?page=2', '', $headers),
            401,
            'signature_invalid',
        );
    }

    public function test_tampered_body_is_signature_invalid(): void
    {
        $timestamp = Carbon::now()->getTimestamp();
        $uri = '/central-api/v1/users/u_42/suspend';
        $headers = [
            Headers::TIMESTAMP => (string) $timestamp,
            Headers::SIGNATURE => Signature::make(self::TOKEN, 'POST', $uri, $timestamp, '{"reason":"a"}'),
            Headers::ACTOR => self::ACTOR,
            Headers::IDEMPOTENCY_KEY => 'k',
        ];

        $this->assertError($this->rawCall('POST', $uri, '{"reason":"b"}', $headers), 401, 'signature_invalid');
        $this->assertSame([], $this->fake()->callsTo('suspend'));
    }

    public static function httpVectors(): iterable
    {
        $vectors = self::vectors('signature.json');

        foreach ($vectors['cases'] as $case) {
            yield $case['name'] => [$case, $vectors['token']];
        }
    }

    /**
     * The shared vectors replayed end-to-end through the middleware.
     */
    #[DataProvider('httpVectors')]
    public function test_shared_vector_over_http(array $case, string $token): void
    {
        config(['central-bridge.token' => $token]);
        $this->travelTo(Carbon::createFromTimestamp($case['now']));

        $response = $this->rawCall($case['method'], $case['path'], $case['body'], [
            Headers::TIMESTAMP => (string) $case['timestamp'],
            Headers::SIGNATURE => $case['signature'],
        ]);

        if ($case['expect'] === 'valid') {
            $this->assertNotSame(401, $response->getStatusCode(), (string) $response->getContent());
        } else {
            $this->assertError($response, 401, $case['expect']);
        }
    }
}
