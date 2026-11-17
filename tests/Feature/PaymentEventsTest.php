<?php

namespace Applu\CentralBridge\Tests\Feature;

use Applu\CentralBridge\Contracts\ReceivesPaymentEvents;
use Applu\CentralBridge\Data\Actor;
use Applu\CentralBridge\Data\PaymentEvent;
use Applu\CentralBridge\Headers;
use Applu\CentralBridge\Tests\Fakes\FakeBridgeHandler;
use Applu\CentralBridge\Tests\TestCase;

class PaymentEventsTest extends TestCase
{
    private const URI = '/central-api/v1/payment-events';

    public function test_delivers_parsed_event_and_actor_to_handler(): void
    {
        $wire = self::vectors('payment_event.json')['event_wire'];

        $this->signedWrite(self::URI, $wire, $wire['id'])->assertOk()->assertExactJson(['ok' => true]);

        [$event, $actor] = $this->fake()->callsTo('paymentEvent')[0];

        $this->assertInstanceOf(PaymentEvent::class, $event);
        $this->assertTrue($event->is(PaymentEvent::PAYMENT_PAID));
        $this->assertSame('7f6d3c1e-4b2a-4c8e-9f10-2a3b4c5d6e7f', $event->payment->id);
        $this->assertSame('vitalicio', $event->payment->plan->slug);
        $this->assertInstanceOf(Actor::class, $actor);
        $this->assertSame(self::ACTOR, $actor->email);
    }

    public function test_renewal_carries_subscription_period_end(): void
    {
        $wire = self::vectors('payment_event.json')['renewal_wire'];

        $this->signedWrite(self::URI, $wire, $wire['id'])->assertOk();

        $event = $this->fake()->callsTo('paymentEvent')[0][0];
        $this->assertSame(PaymentEvent::SUBSCRIPTION_RENEWED, $event->type);
        $this->assertSame('2027-01-17T12:00:00.000+00:00', $event->subscription->currentPeriodEnd);
    }

    public function test_same_event_replays_without_calling_handler_again(): void
    {
        $wire = self::vectors('payment_event.json')['event_wire'];

        $this->signedWrite(self::URI, $wire, $wire['id'])->assertOk();

        $second = $this->signedWrite(self::URI, $wire, $wire['id']);
        $second->assertOk()->assertExactJson(['ok' => true]);
        $second->assertHeader(Headers::IDEMPOTENCY_REPLAYED, 'true');

        $this->assertCount(1, $this->fake()->callsTo('paymentEvent'));
    }

    public function test_same_key_with_other_body_is_409(): void
    {
        $wire = self::vectors('payment_event.json')['event_wire'];

        $this->signedWrite(self::URI, $wire, 'evt-key')->assertOk();

        $wire['type'] = PaymentEvent::PAYMENT_REFUNDED;
        $this->assertError($this->signedWrite(self::URI, $wire, 'evt-key'), 409, 'idempotency_conflict');
    }

    public function test_invalid_vector_events_are_422(): void
    {
        foreach (self::vectors('payment_event.json')['invalid_events'] as $i => $case) {
            $this->assertError(
                $this->signedWrite(self::URI, $case['wire'], 'bad-'.$i),
                422,
                'validation_failed',
            );
        }

        $this->assertSame([], $this->fake()->callsTo('paymentEvent'));
    }

    public function test_wrong_types_are_422_naming_the_field(): void
    {
        $wire = self::vectors('payment_event.json')['event_wire'];
        $wire['payment']['amount_cents'] = '2990';

        $this->assertError(
            $this->signedWrite(self::URI, $wire, 'bad-amount'),
            422,
            'validation_failed',
            'Dados inválidos: o campo "payment.amount_cents" é inválido.',
        );
        $this->assertError($this->signedWrite(self::URI, 'not json', 'bad-json'), 422, 'validation_failed');
    }

    public function test_missing_actor_is_422(): void
    {
        $wire = self::vectors('payment_event.json')['event_wire'];

        $response = $this->signed('POST', self::URI, json_encode($wire), [Headers::IDEMPOTENCY_KEY => $wire['id']]);

        $this->assertError($response, 422, 'missing_actor');
    }

    public function test_without_handler_is_501_and_not_announced(): void
    {
        config(['central-bridge.handlers' => []]);

        $this->assertError(
            $this->signedWrite(self::URI, self::vectors('payment_event.json')['event_wire']),
            501,
            'capability_unsupported',
        );
        $this->signed('GET', '/central-api/v1/health')->assertJsonPath('capabilities', []);
    }

    public function test_only_payment_events_handler_announces_capability(): void
    {
        config(['central-bridge.handlers' => [ReceivesPaymentEvents::class => FakeBridgeHandler::class]]);

        $this->signed('GET', '/central-api/v1/health')->assertJsonPath('capabilities', ['payment_events']);
    }
}
