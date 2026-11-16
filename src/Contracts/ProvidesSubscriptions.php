<?php

namespace Applu\CentralBridge\Contracts;

use Applu\CentralBridge\Data\Page;

/** Capability `subscriptions`. */
interface ProvidesSubscriptions
{
    /**
     * @param  string|null  $status  trialing|active|past_due|canceled|expired|paused, or null for all
     * @return Page<\Applu\CentralBridge\Data\SubscriptionRecord>
     */
    public function subscriptions(?string $status, int $page, int $perPage): Page;
}
