<?php

namespace Applu\CentralBridge\Data;

final readonly class Health
{
    /**
     * @param  list<string>  $capabilities
     */
    public function __construct(
        public string $version,
        public ?string $product,
        public array $capabilities,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['version'],
            $data['product'] ?? null,
            array_values($data['capabilities'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'product' => $this->product,
            'capabilities' => $this->capabilities,
        ];
    }
}
