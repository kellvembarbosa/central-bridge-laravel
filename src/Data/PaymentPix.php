<?php

namespace Applu\CentralBridge\Data;

use Applu\CentralBridge\Support\StrictWire;

/**
 * Pix charge data, only present while the payment is pending.
 */
final readonly class PaymentPix
{
    public function __construct(
        public string $copyPaste,
        public ?string $qrCodeImageUrl = null,
        public ?string $expiresAt = null,
    ) {}

    public static function fromArray(array $data, string $path = 'payment.pix.'): self
    {
        $o = StrictWire::object($data, rtrim($path, '.'));

        return new self(
            StrictWire::string($o, 'copy_paste', $path),
            StrictWire::stringOrNull($o, 'qr_code_image_url', $path),
            StrictWire::stringOrNull($o, 'expires_at', $path),
        );
    }

    public function toArray(): array
    {
        return [
            'copy_paste' => $this->copyPaste,
            'qr_code_image_url' => $this->qrCodeImageUrl,
            'expires_at' => $this->expiresAt,
        ];
    }
}
