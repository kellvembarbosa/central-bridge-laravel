<?php

namespace Applu\CentralBridge\Contracts;

use Applu\CentralBridge\Data\Actor;
use Applu\CentralBridge\Data\PaymentEvent;

/**
 * Capability `payment_events` (Central checkout, contract §10). Must be
 * idempotent by event id and, to grant access, by payment id.
 *
 * On `payment.paid` and `subscription.renewed` set the access of
 * `payment->productUserRef` to `payment->plan->slug` until
 * `payment->accessUntil` (null = forever); `payment.refunded` revokes;
 * `subscription.ended` only means the gateway will not charge again (access
 * still runs until `accessUntil`).
 */
interface ReceivesPaymentEvents
{
    public function paymentEvent(PaymentEvent $event, Actor $actor): void;
}
