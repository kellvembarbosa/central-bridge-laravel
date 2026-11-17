<?php

namespace Applu\CentralBridge\Data;

/**
 * Input for CentralClient::createPayment (POST /api/v1/payments).
 */
final readonly class NewPayment
{
    /**
     * @param  string  $plan  plan slug (from listPlans); the price comes from the Central
     * @param  string  $productUserRef  the buyer's id in the product (`user_ref`)
     * @param  string|null  $method  pix|card|boleto, or null for the gateway default
     * @param  array<string, string>|null  $tracking  utm_* and similar
     */
    public function __construct(
        public string $plan,
        public string $customerName,
        public string $customerEmail,
        public string $productUserRef,
        public ?string $customerDocument = null,
        public ?string $method = null,
        public ?array $tracking = null,
    ) {}

    public function toArray(): array
    {
        $customer = ['name' => $this->customerName, 'email' => $this->customerEmail];

        if ($this->customerDocument !== null && $this->customerDocument !== '') {
            $customer['document'] = $this->customerDocument;
        }

        $data = [
            'plan' => $this->plan,
            'customer' => $customer,
            'product_user_ref' => $this->productUserRef,
            'method' => $this->method,
        ];

        if ($this->tracking) {
            $data['tracking'] = $this->tracking;
        }

        return $data;
    }
}
