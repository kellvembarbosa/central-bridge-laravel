<?php

namespace Applu\CentralBridge\Contracts;

use Applu\CentralBridge\Data\Actor;
use Applu\CentralBridge\Data\PlanChange;

/** Capability `change_plan`. Throw UserNotFound for an unknown ref. */
interface ManagesUserPlans
{
    public function changePlan(string $ref, PlanChange $change, Actor $actor): void;
}
