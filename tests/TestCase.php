<?php

namespace Applu\CentralBridge\Tests;

use Applu\CentralBridge\CentralBridge;
use Applu\CentralBridge\CentralBridgeServiceProvider;
use Applu\CentralBridge\Contracts\ManagesUserPlans;
use Applu\CentralBridge\Contracts\ProvidesInvoiceRequests;
use Applu\CentralBridge\Contracts\ProvidesMetrics;
use Applu\CentralBridge\Contracts\ProvidesPaymentOwnership;
use Applu\CentralBridge\Contracts\ProvidesSubscriptions;
use Applu\CentralBridge\Contracts\ProvidesUsers;
use Applu\CentralBridge\Contracts\ReceivesInvoices;
use Applu\CentralBridge\Contracts\ReceivesPaymentEvents;
use Applu\CentralBridge\Contracts\SendsPasswordResets;
use Applu\CentralBridge\Contracts\SuspendsUsers;
use Applu\CentralBridge\Headers;
use Applu\CentralBridge\Signature;
use Applu\CentralBridge\Tests\Fakes\FakeBridgeHandler;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public const TOKEN = 'cbt_test_token_0123456789abcdef';

    public const ACTOR = 'ops@central.test';

    public const ALL_CONTRACTS = [
        ProvidesPaymentOwnership::class,
        ProvidesSubscriptions::class,
        ProvidesUsers::class,
        ManagesUserPlans::class,
        SendsPasswordResets::class,
        SuspendsUsers::class,
        ProvidesMetrics::class,
        ProvidesInvoiceRequests::class,
        ReceivesInvoices::class,
        ReceivesPaymentEvents::class,
    ];

    protected function getPackageProviders($app): array
    {
        return [CentralBridgeServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('central-bridge.enabled', true);
        $app['config']->set('central-bridge.token', self::TOKEN);
        $app['config']->set('central-bridge.product', 'demo');
        $app['config']->set('central-bridge.central_url', 'https://central.test');
        $app['config']->set(
            'central-bridge.handlers',
            array_fill_keys(self::ALL_CONTRACTS, FakeBridgeHandler::class),
        );

        $app->singleton(FakeBridgeHandler::class);
    }

    protected function defineRoutes($router): void
    {
        CentralBridge::routes();
    }

    protected function fake(): FakeBridgeHandler
    {
        return $this->app->make(FakeBridgeHandler::class);
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function signed(
        string $method,
        string $uri,
        ?string $body = null,
        array $headers = [],
        ?int $timestamp = null,
        string $token = self::TOKEN,
    ): TestResponse {
        $body ??= '';
        $timestamp ??= Carbon::now()->getTimestamp();

        $headers = [
            Headers::TIMESTAMP => (string) $timestamp,
            Headers::SIGNATURE => Signature::make($token, $method, $uri, $timestamp, $body),
            ...$headers,
        ];

        return $this->rawCall($method, $uri, $body, $headers);
    }

    /**
     * Signed write with actor + idempotency key.
     *
     * @param  array<mixed>|string  $body
     * @param  array<string, string>  $headers
     */
    protected function signedWrite(string $uri, array|string $body = [], ?string $key = 'key-1', array $headers = []): TestResponse
    {
        $raw = is_string($body) ? $body : json_encode($body);

        $extra = [Headers::ACTOR => self::ACTOR, 'Content-Type' => 'application/json'];

        if ($key !== null) {
            $extra[Headers::IDEMPOTENCY_KEY] = $key;
        }

        return $this->signed('POST', $uri, $raw, [...$extra, ...$headers]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function rawCall(string $method, string $uri, string $body = '', array $headers = [], array $server = []): TestResponse
    {
        return $this->call($method, $uri, [], [], [], [...$this->transformHeadersToServerVars($headers), ...$server], $body);
    }

    protected function assertError(TestResponse $response, int $status, string $code, ?string $message = null): void
    {
        $response->assertStatus($status);
        $response->assertJsonPath('error.code', $code);

        if ($message !== null) {
            $response->assertJsonPath('error.message', $message);
        }

        $this->assertSame(['error'], array_keys($response->json()));
    }

    protected static function vectors(string $file): array
    {
        // Standalone repo: ./test-vectors. Monorepo: ../bridge-test-vectors (canonical).
        $dir = is_dir(__DIR__.'/../test-vectors') ? __DIR__.'/../test-vectors' : __DIR__.'/../../bridge-test-vectors';

        return json_decode(
            (string) file_get_contents($dir.'/'.$file),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
