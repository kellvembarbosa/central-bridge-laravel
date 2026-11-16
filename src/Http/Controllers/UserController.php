<?php

namespace Applu\CentralBridge\Http\Controllers;

use Applu\CentralBridge\CentralBridge;
use Applu\CentralBridge\Contracts\ManagesUserPlans;
use Applu\CentralBridge\Contracts\ProvidesUsers;
use Applu\CentralBridge\Contracts\SendsPasswordResets;
use Applu\CentralBridge\Contracts\SuspendsUsers;
use Applu\CentralBridge\Data\PlanChange;
use Applu\CentralBridge\Exceptions\UserNotFound;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends BridgeController
{
    public const PASSWORD_RESET_MESSAGE = 'E-mail de redefinição enviado.';

    public function index(Request $request): JsonResponse
    {
        return $this->respond(function () use ($request) {
            $handler = $this->handler(ProvidesUsers::class);

            $input = $this->validated($request->query(), [
                'q' => ['nullable', 'string', 'max:191'],
                'page' => ['nullable', 'integer', 'min:1'],
            ]);

            return $handler->searchUsers($input['q'] ?? null, $this->page($input), CentralBridge::PER_PAGE);
        });
    }

    public function show(string $ref): JsonResponse
    {
        return $this->respond(
            fn () => $this->handler(ProvidesUsers::class)->findUser($ref) ?? throw new UserNotFound($ref),
        );
    }

    public function changePlan(Request $request, string $ref): JsonResponse
    {
        return $this->respond(function () use ($request, $ref) {
            $handler = $this->handler(ManagesUserPlans::class);

            $input = $this->validated($this->body($request), [
                'plan' => ['required', 'string', 'max:64'],
                'until' => ['nullable', 'date_format:Y-m-d'],
                'reason' => ['nullable', 'string', 'max:500'],
            ]);

            $handler->changePlan($ref, PlanChange::fromArray($input), $this->actor($request));

            return ['ok' => true];
        });
    }

    public function passwordReset(Request $request, string $ref): JsonResponse
    {
        return $this->respond(function () use ($request, $ref) {
            $this->handler(SendsPasswordResets::class)->sendPasswordReset($ref, $this->actor($request));

            return ['ok' => true, 'message' => self::PASSWORD_RESET_MESSAGE];
        });
    }

    public function suspend(Request $request, string $ref): JsonResponse
    {
        return $this->respond(function () use ($request, $ref) {
            $handler = $this->handler(SuspendsUsers::class);
            $input = $this->validated($this->body($request), ['reason' => ['required', 'string', 'max:500']]);

            $handler->suspend($ref, $input['reason'], $this->actor($request));

            return ['ok' => true];
        });
    }

    public function unsuspend(Request $request, string $ref): JsonResponse
    {
        return $this->respond(function () use ($request, $ref) {
            $handler = $this->handler(SuspendsUsers::class);
            $input = $this->validated($this->body($request), ['reason' => ['required', 'string', 'max:500']]);

            $handler->unsuspend($ref, $input['reason'], $this->actor($request));

            return ['ok' => true];
        });
    }
}
