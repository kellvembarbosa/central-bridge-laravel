<?php

namespace Applu\CentralBridge\Http\Controllers;

use Applu\CentralBridge\Contracts\ProvidesMetrics;
use Applu\CentralBridge\Data\MetricsDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetricsController extends BridgeController
{
    public function __invoke(Request $request): JsonResponse
    {
        return $this->respond(function () use ($request) {
            $handler = $this->handler(ProvidesMetrics::class);

            $input = $this->validated($request->query(), [
                'from' => ['required', 'date_format:Y-m-d'],
                'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            ]);

            $days = [];

            foreach ($handler->metrics($input['from'], $input['to']) as $day) {
                $days[] = ($day instanceof MetricsDay ? $day : MetricsDay::fromArray($day))->toArray();
            }

            return ['data' => $days];
        });
    }
}
