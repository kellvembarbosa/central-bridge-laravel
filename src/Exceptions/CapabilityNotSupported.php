<?php

namespace Applu\CentralBridge\Exceptions;

use Applu\CentralBridge\ErrorCode;

class CapabilityNotSupported extends BridgeException
{
    public function __construct(public readonly ?string $contract = null)
    {
        parent::__construct(ErrorCode::CapabilityUnsupported);
    }
}
