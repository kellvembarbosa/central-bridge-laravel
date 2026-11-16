<?php

namespace Applu\CentralBridge\Contracts;

use Applu\CentralBridge\Data\Actor;

/** Capability `password_reset`. Throw UserNotFound for an unknown ref. */
interface SendsPasswordResets
{
    public function sendPasswordReset(string $ref, Actor $actor): void;
}
