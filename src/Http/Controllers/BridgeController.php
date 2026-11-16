<?php

namespace Applu\CentralBridge\Http\Controllers;

use Applu\CentralBridge\Capability;
use Applu\CentralBridge\Data\Actor;
use Applu\CentralBridge\ErrorCode;
use Applu\CentralBridge\Exceptions\BridgeException;
use Applu\CentralBridge\Exceptions\CapabilityNotSupported;
use Applu\CentralBridge\Http\Middleware\EnforceIdempotency;
use Applu\CentralBridge\Support\HandlerRegistry;
use Applu\CentralBridge\Support\ResponseSanitizer;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

abstract class BridgeController
{
    public function __construct(protected readonly HandlerRegistry $handlers) {}

    /**
     * Runs the action and turns its result into a sanitized 200, or any
     * failure into the contract error envelope.
     *
     * @param  Closure(): mixed  $action
     * @param  list<string>  $allow  sanitizer allow-list (exact dot paths)
     */
    protected function respond(Closure $action, array $allow = []): JsonResponse
    {
        try {
            $payload = $this->toPayload($action());
        } catch (BridgeException $e) {
            return $e->render();
        } catch (ValidationException) {
            return BridgeException::make(ErrorCode::ValidationFailed)->render();
        } catch (ModelNotFoundException) {
            return BridgeException::make(ErrorCode::NotFound)->render();
        } catch (Throwable $e) {
            report($e);

            return BridgeException::make(ErrorCode::InternalError)->render();
        }

        return new JsonResponse(
            ResponseSanitizer::sanitize($payload, $allow),
            200,
            [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @return T
     */
    protected function handler(string $contract): object
    {
        return $this->handlers->resolve($contract) ?? throw new CapabilityNotSupported($contract);
    }

    protected function requireCapability(Capability $capability): void
    {
        if (! $capability->isSupportedBy($this->handlers)) {
            throw new CapabilityNotSupported;
        }
    }

    protected function actor(Request $request): Actor
    {
        $actor = $request->attributes->get(EnforceIdempotency::ACTOR_ATTRIBUTE);

        return $actor instanceof Actor ? $actor : throw BridgeException::make(ErrorCode::MissingActor);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function validated(array $input, array $rules): array
    {
        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            throw BridgeException::make(ErrorCode::ValidationFailed);
        }

        return $validator->validated();
    }

    /**
     * Raw JSON body as an array (independent of the Content-Type header).
     *
     * @return array<string, mixed>
     */
    protected function body(Request $request): array
    {
        $decoded = json_decode($request->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function page(array $input): int
    {
        return (int) ($input['page'] ?? 1);
    }

    private function toPayload(mixed $result): array
    {
        if (is_object($result) && method_exists($result, 'toArray')) {
            return $result->toArray();
        }

        return (array) $result;
    }
}
