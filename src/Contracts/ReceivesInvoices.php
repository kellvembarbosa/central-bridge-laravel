<?php

namespace Applu\CentralBridge\Contracts;

use Applu\CentralBridge\Data\Actor;
use Applu\CentralBridge\Data\InvoiceDelivery;

/** Capability `invoices` (together with ProvidesInvoiceRequests). Must be idempotent by invoice_id. */
interface ReceivesInvoices
{
    public function receiveInvoice(InvoiceDelivery $delivery, Actor $actor): void;
}
