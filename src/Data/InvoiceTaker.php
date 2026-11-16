<?php

namespace Applu\CentralBridge\Data;

final readonly class InvoiceTaker
{
    /**
     * @param  string  $document  CPF or CNPJ, digits only
     */
    public function __construct(
        public string $document,
        public string $name,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self((string) $data['document'], (string) $data['name']);
    }

    public function toArray(): array
    {
        return ['document' => $this->document, 'name' => $this->name];
    }
}
