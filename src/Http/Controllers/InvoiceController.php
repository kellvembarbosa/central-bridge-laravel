<?php

namespace Applu\CentralBridge\Http\Controllers;

use Applu\CentralBridge\Capability;
use Applu\CentralBridge\Contracts\ReceivesInvoices;
use Applu\CentralBridge\Data\InvoiceDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoiceController extends BridgeController
{
    public function __invoke(Request $request): JsonResponse
    {
        return $this->respond(function () use ($request) {
            $this->requireCapability(Capability::Invoices);
            $handler = $this->handler(ReceivesInvoices::class);

            $body = $this->body($request);

            // Identifiers may arrive as JSON numbers; the wire type is string.
            foreach (['invoice_id', 'request_ref', 'payment_id', 'number'] as $field) {
                if (is_int($body[$field] ?? null)) {
                    $body[$field] = (string) $body[$field];
                }
            }

            $input = $this->validated($body, [
                'invoice_id' => ['required', 'string', 'max:191'],
                'request_ref' => ['nullable', 'string', 'max:191'],
                'provider' => ['required', 'string', 'max:64'],
                'payment_id' => ['required', 'string', 'max:191'],
                'status' => ['required', Rule::in([InvoiceDelivery::STATUS_ISSUED, InvoiceDelivery::STATUS_CANCELED])],
                'number' => ['nullable', 'string', 'max:64'],
                'access_key' => ['nullable', 'string', 'max:191'],
                'issued_at' => ['nullable', 'string', 'max:64'],
                'pdf_url' => ['nullable', 'string', 'max:2048'],
                'xml_url' => ['nullable', 'string', 'max:2048'],
                'message' => ['nullable', 'string', 'max:2000'],
                'view_url' => ['nullable', 'string', 'max:2048'],
            ]);

            $handler->receiveInvoice(InvoiceDelivery::fromArray($input), $this->actor($request));

            return ['ok' => true];
        });
    }
}
