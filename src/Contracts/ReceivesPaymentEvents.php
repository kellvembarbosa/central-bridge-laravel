<?php

namespace Applu\CentralBridge\Contracts;

use Applu\CentralBridge\Data\Actor;
use Applu\CentralBridge\Data\PaymentEvent;

/**
 * Capability `payment_events` (Central checkout, contract §10). Must be
 * idempotent by event id and, to grant access, by payment id.
 */
interface ReceivesPaymentEvents
{
    public function paymentEvent(PaymentEvent $event, Actor $actor): void;
}
