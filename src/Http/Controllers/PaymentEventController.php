<?php

namespace Applu\CentralBridge\Http\Controllers;

use Applu\CentralBridge\Capability;
use Applu\CentralBridge\Contracts\ReceivesPaymentEvents;
use Applu\CentralBridge\Data\PaymentEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentEventController extends BridgeController
{
    public function __invoke(Request $request): JsonResponse
    {
        return $this->respond(function () use ($request) {
            $this->requireCapability(Capability::PaymentEvents);
            $handler = $this->handler(ReceivesPaymentEvents::class);

            $event = PaymentEvent::fromArray($this->body($request));

            $handler->paymentEvent($event, $this->actor($request));

            return ['ok' => true];
        });
    }
}
