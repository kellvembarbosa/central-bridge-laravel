<?php

namespace Applu\CentralBridge\Http\Controllers;

use Applu\CentralBridge\CentralBridge;
use Applu\CentralBridge\Contracts\ProvidesSubscriptions;
use Applu\CentralBridge\Data\SubscriptionRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends BridgeController
{
    public function __invoke(Request $request): JsonResponse
    {
        return $this->respond(function () use ($request) {
            $handler = $this->handler(ProvidesSubscriptions::class);

            $input = $this->validated($request->query(), [
                'status' => ['nullable', 'string', Rule::in(SubscriptionRecord::STATUSES)],
                'page' => ['nullable', 'integer', 'min:1'],
            ]);

            return $handler->subscriptions($input['status'] ?? null, $this->page($input), CentralBridge::PER_PAGE);
        });
    }
}
