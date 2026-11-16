<?php

namespace Applu\CentralBridge\Contracts;

use Applu\CentralBridge\Data\InvoiceRequest;

/** Capability `invoices` (together with ReceivesInvoices). */
interface ProvidesInvoiceRequests
{
    public function invoiceRequest(string $provider, string $paymentId): InvoiceRequest;
}
