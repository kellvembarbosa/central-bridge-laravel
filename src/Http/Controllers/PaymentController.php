<?php

namespace Applu\CentralBridge\Http\Controllers;

use Applu\CentralBridge\Capability;
use Applu\CentralBridge\Contracts\ProvidesInvoiceRequests;
use Applu\CentralBridge\Contracts\ProvidesPaymentOwnership;
use Illuminate\Http\JsonResponse;

class PaymentController extends BridgeController
{
    public function owner(string $provider, string $paymentId): JsonResponse
    {
        return $this->respond(
            fn () => $this->handler(ProvidesPaymentOwnership::class)->paymentOwner($provider, $paymentId),
        );
    }

    public function invoiceRequest(string $provider, string $paymentId): JsonResponse
    {
        return $this->respond(function () use ($provider, $paymentId) {
            $this->requireCapability(Capability::Invoices);

            return $this->handler(ProvidesInvoiceRequests::class)->invoiceRequest($provider, $paymentId);
        }, allow: ['taker.document']);
    }
}
