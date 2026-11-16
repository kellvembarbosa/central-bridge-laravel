<?php

namespace Applu\CentralBridge\Http\Controllers;

use Applu\CentralBridge\Capability;
use Applu\CentralBridge\CentralBridge;
use Applu\CentralBridge\Data\Health;
use Illuminate\Http\JsonResponse;

class HealthController extends BridgeController
{
    public function __invoke(): JsonResponse
    {
        return $this->respond(fn () => new Health(
            (string) config('central-bridge.version', CentralBridge::VERSION),
            config('central-bridge.product'),
            Capability::supported($this->handlers),
        ));
    }
}
