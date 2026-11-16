<?php

namespace Applu\CentralBridge\Contracts;

use Applu\CentralBridge\Data\PaymentOwner;

/** Capability `payment_owner`. */
interface ProvidesPaymentOwnership
{
    public function paymentOwner(string $provider, string $paymentId): PaymentOwner;
}
