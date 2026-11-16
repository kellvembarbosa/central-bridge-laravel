<?php

namespace Applu\CentralBridge\Tests\Feature;

use Applu\CentralBridge\Data\InvoiceDelivery;
use Applu\CentralBridge\Data\PlanChange;
use Applu\CentralBridge\Tests\TestCase;

class RoutesTest extends TestCase
{
    public function test_payment_owner(): void
    {
        $this->signed('GET', '/central-api/v1/payments/stripe/pi_1/owner')
            ->assertOk()
            ->assertExactJson(['owned' => true, 'user_ref' => 'u_42', 'kind' => 'subscription', 'local_status' => 'active']);

        $this->assertSame([['stripe', 'pi_1']], $this->fake()->callsTo('paymentOwner'));
    }

    public function test_payment_not_owned(): void
    {
        $this->signed('GET', '/central-api/v1/payments/mercadopago/unknown/owner')
            ->assertOk()
            ->assertExactJson(['owned' => false, 'user_ref' => null, 'kind' => null, 'local_status' => null]);
    }

    public function test_invoice_request_keeps_only_taker_document(): void
    {
        $this->signed('GET', '/central-api/v1/payments/stripe/pi_1/invoice-request')
            ->assertOk()
            ->assertExactJson([
                'requested' => true,
                'request_ref' => 'req_1',
                'email' => 'cliente@acme.test',
                'service_description' => 'Assinatura mensal',
                'taker' => ['document' => '12345678000195', 'name' => 'ACME Ltda'],
            ]);
    }

    public function test_subscriptions_page(): void
    {
        $this->signed('GET', '/central-api/v1/subscriptions?status=active&page=2')
            ->assertOk()
            ->assertJsonPath('page', 2)
            ->assertJsonPath('per_page', 25)
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('data.0', [
                'ref' => 'sub_1', 'user_ref' => 'u_42', 'provider' => 'stripe', 'provider_subscription_id' => 'sub_abc',
                'status' => 'active', 'plan' => 'pro', 'amount_cents' => 1990, 'currency' => 'BRL',
                'interval' => 'month', 'current_period_end' => '2026-12-01T00:00:00+00:00',
            ]);

        $this->assertSame([['active', 2, 25]], $this->fake()->callsTo('subscriptions'));
    }

    public function test_subscriptions_defaults_and_validation(): void
    {
        $this->signed('GET', '/central-api/v1/subscriptions')->assertOk();
        $this->assertSame([[null, 1, 25]], $this->fake()->callsTo('subscriptions'));

        $this->assertError($this->signed('GET', '/central-api/v1/subscriptions?status=bogus'), 422, 'validation_failed');
        $this->assertError($this->signed('GET', '/central-api/v1/subscriptions?page=0'), 422, 'validation_failed');
    }

    public function test_users_search(): void
    {
        $this->signed('GET', '/central-api/v1/users?q=ana%40x.com&page=2')
            ->assertOk()
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('data.0', [
                'ref' => 'u_42', 'email' => 'ana@x.com', 'name' => 'Ana', 'plan' => 'pro', 'suspended' => false,
                'created_at' => '2026-01-02T03:04:05+00:00', 'last_seen_at' => null,
            ]);

        $this->assertSame([['ana@x.com', 2, 25]], $this->fake()->callsTo('searchUsers'));
    }

    public function test_users_search_validation(): void
    {
        $q = str_repeat('a', 192);

        $this->assertError($this->signed('GET', "/central-api/v1/users?q={$q}"), 422, 'validation_failed');
        $this->assertError($this->signed('GET', '/central-api/v1/users?page=abc'), 422, 'validation_failed');
    }

    public function test_user_detail_is_sanitized(): void
    {
        $response = $this->signed('GET', '/central-api/v1/users/u_42')->assertOk();

        $response->assertJsonPath('ref', 'u_42')
            ->assertJsonPath('limits', ['projects' => 10, 'storage' => null])
            ->assertJsonPath('usage', ['projects' => 4])
            ->assertJsonPath('subscriptions.0.ref', 'sub_1')
            ->assertJsonPath('recent_events.0', [
                'type' => 'login', 'at' => '2026-11-14T10:00:00+00:00', 'description' => 'Entrou pelo app',
            ]);

        $this->assertStringNotContainsString('api_token', (string) $response->getContent());
        $this->assertStringNotContainsString('password', (string) $response->getContent());
    }

    public function test_unknown_user_is_404(): void
    {
        $this->assertError($this->signed('GET', '/central-api/v1/users/nobody'), 404, 'not_found', 'Recurso não encontrado.');
    }

    public function test_handler_crash_is_500_without_details(): void
    {
        $this->fake()->explode = true;

        $response = $this->signed('GET', '/central-api/v1/users/u_42');

        $this->assertError($response, 500, 'internal_error', 'Erro interno no produto.');
        $this->assertStringNotContainsString('secret details', (string) $response->getContent());
    }

    public function test_metrics(): void
    {
        $this->signed('GET', '/central-api/v1/metrics?from=2026-11-01&to=2026-11-02')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['date' => '2026-11-01', 'metrics' => ['signups' => 3, 'revenue_cents' => 1990]],
                ['date' => '2026-11-02', 'metrics' => ['signups' => 5]],
            ]]);

        $this->assertSame([['2026-11-01', '2026-11-02']], $this->fake()->callsTo('metrics'));
    }

    public function test_metrics_validation(): void
    {
        $this->assertError($this->signed('GET', '/central-api/v1/metrics?from=2026-11-01'), 422, 'validation_failed');
        $this->assertError($this->signed('GET', '/central-api/v1/metrics?from=01/11/2026&to=2026-11-02'), 422, 'validation_failed');
        $this->assertError($this->signed('GET', '/central-api/v1/metrics?from=2026-11-05&to=2026-11-02'), 422, 'validation_failed');
    }

    public function test_change_plan(): void
    {
        $this->signedWrite('/central-api/v1/users/u_42/plan', ['plan' => 'pro', 'until' => '2026-12-31', 'reason' => 'Cortesia'])
            ->assertOk()
            ->assertExactJson(['ok' => true]);

        [$ref, $change, $actor] = $this->fake()->callsTo('changePlan')[0];

        $this->assertSame('u_42', $ref);
        $this->assertEquals(new PlanChange('pro', '2026-12-31', 'Cortesia'), $change);
        $this->assertSame(self::ACTOR, $actor->email);
    }

    public function test_change_plan_without_end_date(): void
    {
        $this->signedWrite('/central-api/v1/users/u_42/plan', ['plan' => 'free', 'until' => null])->assertOk();

        $this->assertEquals(new PlanChange('free'), $this->fake()->callsTo('changePlan')[0][1]);
    }

    public function test_change_plan_validation_and_not_found(): void
    {
        $this->assertError(
            $this->signedWrite('/central-api/v1/users/u_42/plan', ['plan' => str_repeat('p', 65)], 'k1'),
            422,
            'validation_failed',
        );
        $this->assertError(
            $this->signedWrite('/central-api/v1/users/u_42/plan', ['plan' => 'pro', 'until' => '31/12/2026'], 'k2'),
            422,
            'validation_failed',
        );
        $this->assertError(
            $this->signedWrite('/central-api/v1/users/u_42/plan', ['plan' => 'pro', 'reason' => str_repeat('r', 501)], 'k3'),
            422,
            'validation_failed',
        );
        $this->assertError(
            $this->signedWrite('/central-api/v1/users/nobody/plan', ['plan' => 'pro'], 'k4'),
            404,
            'not_found',
        );
    }

    public function test_password_reset(): void
    {
        $this->signedWrite('/central-api/v1/users/u_42/password-reset')
            ->assertOk()
            ->assertExactJson(['ok' => true, 'message' => 'E-mail de redefinição enviado.']);

        $this->assertSame('u_42', $this->fake()->callsTo('sendPasswordReset')[0][0]);
    }

    public function test_suspend_and_unsuspend(): void
    {
        $this->signedWrite('/central-api/v1/users/u_42/suspend', ['reason' => 'Fraude confirmada'], 'k-s')
            ->assertOk()->assertExactJson(['ok' => true]);
        $this->signedWrite('/central-api/v1/users/u_42/unsuspend', ['reason' => 'Revisado'], 'k-u')
            ->assertOk()->assertExactJson(['ok' => true]);

        $this->assertSame('Fraude confirmada', $this->fake()->callsTo('suspend')[0][1]);
        $this->assertSame('Revisado', $this->fake()->callsTo('unsuspend')[0][1]);
    }

    public function test_suspend_requires_reason(): void
    {
        $this->assertError($this->signedWrite('/central-api/v1/users/u_42/suspend', [], 'k-1'), 422, 'validation_failed');
        $this->assertError($this->signedWrite('/central-api/v1/users/u_42/unsuspend', 'not json', 'k-2'), 422, 'validation_failed');
    }

    public function test_receive_invoice(): void
    {
        $payload = [
            'invoice_id' => 'inv_1',
            'request_ref' => 'req_1',
            'provider' => 'stripe',
            'payment_id' => 'pi_1',
            'status' => 'issued',
            'number' => '123',
            'access_key' => 'NFSE123',
            'issued_at' => '2026-11-15T10:00:00+00:00',
            'pdf_url' => 'https://central.test/inv_1.pdf',
            'xml_url' => 'https://central.test/inv_1.xml',
            'message' => null,
            'view_url' => 'https://central.test/v/inv_1',
        ];

        $this->signedWrite('/central-api/v1/invoices', $payload)->assertOk()->assertExactJson(['ok' => true]);

        [$delivery, $actor] = $this->fake()->callsTo('receiveInvoice')[0];

        $this->assertInstanceOf(InvoiceDelivery::class, $delivery);
        $this->assertSame($payload, $delivery->toArray());
        $this->assertSame(self::ACTOR, $actor->email);
    }

    public function test_receive_invoice_accepts_numeric_ids_and_validates_status(): void
    {
        $this->signedWrite('/central-api/v1/invoices', [
            'invoice_id' => 77, 'request_ref' => null, 'provider' => 'stripe', 'payment_id' => 'pi_1', 'status' => 'canceled',
        ], 'k-num')->assertOk();

        $this->assertSame('77', $this->fake()->callsTo('receiveInvoice')[0][0]->invoiceId);

        $this->assertError($this->signedWrite('/central-api/v1/invoices', [
            'invoice_id' => 'i', 'provider' => 'stripe', 'payment_id' => 'pi_1', 'status' => 'pending',
        ], 'k-bad'), 422, 'validation_failed');
    }

    public function test_bridge_routes_skip_csrf_and_are_named(): void
    {
        $route = $this->app['router']->getRoutes()->getByName('central-bridge.users.suspend');

        $this->assertNotNull($route);
        $this->assertSame('central-api/v1/users/{ref}/suspend', $route->uri());
        $this->assertContains(
            'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken',
            $route->excludedMiddleware(),
        );
    }
}
