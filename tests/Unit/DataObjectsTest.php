<?php

namespace Applu\CentralBridge\Tests\Unit;

use Applu\CentralBridge\Data\Actor;
use Applu\CentralBridge\Data\Health;
use Applu\CentralBridge\Data\InvoiceDelivery;
use Applu\CentralBridge\Data\InvoiceRequest;
use Applu\CentralBridge\Data\MetricsDay;
use Applu\CentralBridge\Data\Page;
use Applu\CentralBridge\Data\PaymentOwner;
use Applu\CentralBridge\Data\PlanChange;
use Applu\CentralBridge\Data\SubscriptionRecord;
use Applu\CentralBridge\Data\UserDetail;
use Applu\CentralBridge\Data\UserSummary;
use Applu\CentralBridge\ErrorCode;
use PHPUnit\Framework\TestCase;

class DataObjectsTest extends TestCase
{
    public function test_round_trips(): void
    {
        $shapes = [
            PaymentOwner::class => ['owned' => true, 'user_ref' => 'u1', 'kind' => 'order', 'local_status' => 'paid'],
            InvoiceRequest::class => [
                'requested' => true, 'request_ref' => 'r1', 'email' => 'a@b.c', 'service_description' => 'x',
                'taker' => ['document' => '123', 'name' => 'N'],
            ],
            SubscriptionRecord::class => [
                'ref' => 's', 'user_ref' => 'u', 'provider' => 'stripe', 'provider_subscription_id' => 'p',
                'status' => 'active', 'plan' => 'pro', 'amount_cents' => 100, 'currency' => 'BRL',
                'interval' => 'month', 'current_period_end' => '2026-01-01T00:00:00+00:00',
            ],
            UserSummary::class => [
                'ref' => 'u', 'email' => 'a@b.c', 'name' => null, 'plan' => null, 'suspended' => true,
                'created_at' => null, 'last_seen_at' => null,
            ],
            PlanChange::class => ['plan' => 'pro', 'until' => '2026-12-31', 'reason' => null],
            Health::class => ['version' => '2.1.0', 'product' => 'demo', 'capabilities' => ['metrics']],
            Actor::class => ['email' => 'a@b.c', 'request_id' => 'rq'],
            InvoiceDelivery::class => [
                'invoice_id' => 'i', 'request_ref' => null, 'provider' => 'stripe', 'payment_id' => 'p',
                'status' => 'issued', 'number' => '1', 'access_key' => 'k', 'issued_at' => null,
                'pdf_url' => null, 'xml_url' => null, 'message' => null, 'view_url' => 'https://v',
            ],
        ];

        foreach ($shapes as $class => $shape) {
            $this->assertSame($shape, $class::fromArray($shape)->toArray(), $class);
        }
    }

    public function test_invoice_delivery_omits_absent_view_url(): void
    {
        $delivery = new InvoiceDelivery('i', null, 'stripe', 'p', 'canceled');

        $this->assertArrayNotHasKey('view_url', $delivery->toArray());
        $this->assertTrue($delivery->isCanceled());
    }

    public function test_empty_maps_serialize_as_objects(): void
    {
        $detail = new UserDetail(new UserSummary('u', 'a@b.c'));

        $this->assertSame('{}', json_encode($detail->toArray()['limits']));
        $this->assertSame('{"date":"2026-01-01","metrics":{}}', json_encode((new MetricsDay('2026-01-01', []))->toArray()));
    }

    public function test_page_maps_items(): void
    {
        $page = Page::fromArray(
            ['data' => [['ref' => 'u', 'email' => 'a@b.c']], 'page' => 2, 'per_page' => 25, 'has_more' => true],
            UserSummary::fromArray(...),
        );

        $this->assertInstanceOf(UserSummary::class, $page->data[0]);
        $this->assertSame(['data', 'page', 'per_page', 'has_more'], array_keys($page->toArray()));
        $this->assertSame('u', $page->toArray()['data'][0]['ref']);
    }

    public function test_error_codes_match_contract(): void
    {
        $expected = [
            'unauthorized' => 401, 'signature_invalid' => 401, 'timestamp_skew' => 401, 'ip_not_allowed' => 403,
            'not_found' => 404, 'idempotency_conflict' => 409, 'validation_failed' => 422, 'missing_actor' => 422,
            'internal_error' => 500, 'capability_unsupported' => 501,
        ];

        foreach ($expected as $code => $status) {
            $this->assertSame($status, ErrorCode::from($code)->httpStatus());
            $this->assertNotSame('', ErrorCode::from($code)->defaultMessage());
        }

        $this->assertCount(count($expected), ErrorCode::cases());
    }
}
