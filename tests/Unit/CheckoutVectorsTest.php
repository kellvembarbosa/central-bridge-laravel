<?php

namespace Applu\CentralBridge\Tests\Unit;

use Applu\CentralBridge\Data\NewPayment;
use Applu\CentralBridge\Data\PaymentEvent;
use Applu\CentralBridge\Data\PaymentRecord;
use Applu\CentralBridge\Data\Plan;
use Applu\CentralBridge\ErrorCode;
use Applu\CentralBridge\Exceptions\BridgeException;
use Applu\CentralBridge\Tests\TestCase;

/**
 * Shared checkout vectors (contract §10): wire must parse into the same
 * values the other packages produce (*_parsed is camelCase).
 */
class CheckoutVectorsTest extends TestCase
{
    public function test_plan_vector(): void
    {
        $v = self::vectors('payment_event.json');

        $this->assertSame($v['plan_parsed'], self::camel(Plan::fromArray($v['plan_wire'])));
        $this->assertSame($v['plan_wire'], Plan::fromArray($v['plan_wire'])->toArray());
    }

    public function test_payment_vector(): void
    {
        $v = self::vectors('payment_event.json');
        $payment = PaymentRecord::fromArray($v['payment_wire']);

        $this->assertSame($v['payment_parsed'], self::camel($payment));
        $this->assertSame($v['payment_wire'], json_decode(json_encode($payment->toArray()), true));
        $this->assertTrue($payment->isPending());
    }

    public function test_event_vectors(): void
    {
        $v = self::vectors('payment_event.json');

        foreach (['event', 'renewal', 'prepaid'] as $name) {
            $event = PaymentEvent::fromArray($v[$name.'_wire']);

            $this->assertSame($v[$name.'_parsed'], self::camel($event), $name);
            $this->assertSame($v[$name.'_wire'], json_decode(json_encode($event->toArray()), true), $name);
        }
    }

    public function test_invalid_events_are_rejected(): void
    {
        foreach (self::vectors('payment_event.json')['invalid_events'] as $case) {
            try {
                PaymentEvent::fromArray($case['wire']);
                $this->fail('accepted: '.$case['why']);
            } catch (BridgeException $e) {
                $this->assertSame(ErrorCode::ValidationFailed, $e->errorCode, $case['why']);
            }
        }
    }

    public function test_defaults_for_optional_fields(): void
    {
        $wire = self::vectors('payment_event.json')['payment_wire'];
        unset($wire['refunded_cents'], $wire['tracking'], $wire['pix'], $wire['access_until']);
        $wire['method'] = null;

        $payment = PaymentRecord::fromArray($wire);

        $this->assertSame(0, $payment->refundedCents);
        $this->assertSame('other', $payment->method);
        $this->assertSame([], $payment->tracking);
        $this->assertNull($payment->pix);
        $this->assertNull($payment->accessUntil); // older Central omits it
        $this->assertSame('{}', json_encode($payment->toArray()['tracking']));
    }

    public function test_access_until_must_be_a_string_or_null(): void
    {
        $wire = self::vectors('payment_event.json')['payment_wire'];
        $wire['access_until'] = 123;

        try {
            PaymentRecord::fromArray($wire);
            $this->fail('accepted a non-string access_until');
        } catch (BridgeException $e) {
            $this->assertSame(ErrorCode::ValidationFailed, $e->errorCode);
        }
    }

    public function test_new_payment_wire(): void
    {
        $this->assertSame(
            [
                'plan' => 'mensal',
                'customer' => ['name' => 'Ana', 'email' => 'ana@x.test', 'document' => '12345678909'],
                'product_user_ref' => '42',
                'method' => 'pix',
                'tracking' => ['utm_source' => 'google'],
            ],
            (new NewPayment('mensal', 'Ana', 'ana@x.test', '42', '12345678909', 'pix', ['utm_source' => 'google']))->toArray(),
        );

        $this->assertSame(
            ['plan' => 'mensal', 'customer' => ['name' => 'Ana', 'email' => 'ana@x.test'], 'product_user_ref' => '42', 'method' => null],
            (new NewPayment('mensal', 'Ana', 'ana@x.test', '42'))->toArray(),
        );
    }

    /**
     * Public properties as a camelCase array (nested Data objects too).
     */
    private static function camel(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        return is_array($value) ? array_map(self::camel(...), $value) : $value;
    }
}
