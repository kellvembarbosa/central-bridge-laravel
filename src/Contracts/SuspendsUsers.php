<?php

namespace Applu\CentralBridge\Contracts;

use Applu\CentralBridge\Data\Actor;

/** Capability `suspend`. Throw UserNotFound for an unknown ref. */
interface SuspendsUsers
{
    public function suspend(string $ref, string $reason, Actor $actor): void;

    public function unsuspend(string $ref, string $reason, Actor $actor): void;
}
