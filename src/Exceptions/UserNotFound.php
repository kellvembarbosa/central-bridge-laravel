<?php

namespace Applu\CentralBridge\Exceptions;

use Applu\CentralBridge\ErrorCode;

class UserNotFound extends BridgeException
{
    public function __construct(public readonly ?string $ref = null)
    {
        parent::__construct(ErrorCode::NotFound);
    }
}
