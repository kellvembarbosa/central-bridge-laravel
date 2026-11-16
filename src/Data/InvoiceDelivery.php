<?php

namespace Applu\CentralBridge\Data;

use Applu\CentralBridge\Support\Wire;

/**
 * Invoice the Central delivers to the product (POST /invoices).
 */
final readonly class InvoiceDelivery
{
    public const STATUS_ISSUED = 'issued';

    public const STATUS_CANCELED = 'canceled';

    public function __construct(
        public string $invoiceId,
        public ?string $requestRef,
        public string $provider,
        public string $paymentId,
        public string $status,
        public ?string $number = null,
        public ?string $accessKey = null,
        public ?string $issuedAt = null,
        public ?string $pdfUrl = null,
        public ?string $xmlUrl = null,
        public ?string $message = null,
        public ?string $viewUrl = null,
    ) {}

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isCanceled(): bool
    {
        return $this->status === self::STATUS_CANCELED;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['invoice_id'],
            Wire::nullableString($data['request_ref'] ?? null),
            (string) $data['provider'],
            (string) $data['payment_id'],
            (string) $data['status'],
            Wire::nullableString($data['number'] ?? null),
            Wire::nullableString($data['access_key'] ?? null),
            Wire::dateTime($data['issued_at'] ?? null),
            Wire::nullableString($data['pdf_url'] ?? null),
            Wire::nullableString($data['xml_url'] ?? null),
            Wire::nullableString($data['message'] ?? null),
            Wire::nullableString($data['view_url'] ?? null),
        );
    }

    public function toArray(): array
    {
        $data = [
            'invoice_id' => $this->invoiceId,
            'request_ref' => $this->requestRef,
            'provider' => $this->provider,
            'payment_id' => $this->paymentId,
            'status' => $this->status,
            'number' => $this->number,
            'access_key' => $this->accessKey,
            'issued_at' => $this->issuedAt,
            'pdf_url' => $this->pdfUrl,
            'xml_url' => $this->xmlUrl,
            'message' => $this->message,
        ];

        if ($this->viewUrl !== null) {
            $data['view_url'] = $this->viewUrl;
        }

        return $data;
    }
}
