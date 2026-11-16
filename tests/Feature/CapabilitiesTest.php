<?php

namespace Applu\CentralBridge\Tests\Feature;

use Applu\CentralBridge\Contracts\ProvidesInvoiceRequests;
use Applu\CentralBridge\Contracts\ProvidesMetrics;
use Applu\CentralBridge\Tests\Fakes\FakeBridgeHandler;
use Applu\CentralBridge\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class CapabilitiesTest extends TestCase
{
    public function test_health_announces_every_capability_when_all_handlers_exist(): void
    {
        $this->signed('GET', '/central-api/v1/health')
            ->assertOk()
            ->assertExactJson([
                'version' => '2.0.0',
                'product' => 'demo',
                'capabilities' => [
                    'payment_owner', 'subscriptions', 'users_search', 'user_detail', 'change_plan',
                    'password_reset', 'suspend', 'metrics', 'invoices',
                ],
            ]);
    }

    public function test_health_without_handlers_announces_nothing(): void
    {
        config(['central-bridge.handlers' => []]);

        $this->signed('GET', '/central-api/v1/health')->assertOk()->assertJsonPath('capabilities', []);
    }

    public function test_invoices_needs_both_handlers(): void
    {
        config(['central-bridge.handlers' => [ProvidesInvoiceRequests::class => FakeBridgeHandler::class]]);

        $this->signed('GET', '/central-api/v1/health')->assertOk()->assertJsonPath('capabilities', []);

        $this->assertError(
            $this->signed('GET', '/central-api/v1/payments/stripe/pi_1/invoice-request'),
            501,
            'capability_unsupported',
        );
    }

    public function test_container_binding_also_counts_as_handler(): void
    {
        config(['central-bridge.handlers' => []]);
        $this->app->bind(ProvidesMetrics::class, FakeBridgeHandler::class);

        $this->signed('GET', '/central-api/v1/health')->assertJsonPath('capabilities', ['metrics']);
        $this->signed('GET', '/central-api/v1/metrics?from=2026-11-01&to=2026-11-02')->assertOk();
    }

    public function test_handler_not_implementing_contract_is_internal_error(): void
    {
        config(['central-bridge.handlers' => [ProvidesMetrics::class => \stdClass::class]]);

        $this->assertError(
            $this->signed('GET', '/central-api/v1/metrics?from=2026-11-01&to=2026-11-02'),
            500,
            'internal_error',
        );
    }

    public static function routes(): array
    {
        return [
            'owner' => ['GET', '/central-api/v1/payments/stripe/pi_1/owner', null],
            'invoice-request' => ['GET', '/central-api/v1/payments/stripe/pi_1/invoice-request', null],
            'subscriptions' => ['GET', '/central-api/v1/subscriptions', null],
            'users' => ['GET', '/central-api/v1/users', null],
            'user' => ['GET', '/central-api/v1/users/u_42', null],
            'metrics' => ['GET', '/central-api/v1/metrics?from=2026-11-01&to=2026-11-02', null],
            'plan' => ['POST', '/central-api/v1/users/u_42/plan', ['plan' => 'pro']],
            'password-reset' => ['POST', '/central-api/v1/users/u_42/password-reset', []],
            'suspend' => ['POST', '/central-api/v1/users/u_42/suspend', ['reason' => 'x']],
            'unsuspend' => ['POST', '/central-api/v1/users/u_42/unsuspend', ['reason' => 'x']],
            'invoices' => ['POST', '/central-api/v1/invoices', ['invoice_id' => 'i']],
        ];
    }

    #[DataProvider('routes')]
    public function test_route_without_handler_is_501(string $method, string $uri, ?array $body): void
    {
        config(['central-bridge.handlers' => []]);

        $response = $method === 'GET' ? $this->signed('GET', $uri) : $this->signedWrite($uri, $body);

        $this->assertError($response, 501, 'capability_unsupported', 'Este produto não oferece esta operação.');
    }

    public function test_501_is_not_stored_for_idempotency(): void
    {
        config(['central-bridge.handlers' => []]);
        $this->signedWrite('/central-api/v1/users/u_42/suspend', ['reason' => 'x'], 'key-501')->assertStatus(501);

        config(['central-bridge.handlers' => array_fill_keys(self::ALL_CONTRACTS, FakeBridgeHandler::class)]);
        $this->signedWrite('/central-api/v1/users/u_42/suspend', ['reason' => 'x'], 'key-501')->assertOk();
    }
}
