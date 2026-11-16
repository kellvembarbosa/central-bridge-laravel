<?php

namespace Applu\CentralBridge\Exceptions;

use Applu\CentralBridge\ErrorCode;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

/**
 * Business-rule error that becomes the contract envelope
 * {"error": {"code": "...", "message": "..."}}. Handlers may throw it.
 */
class BridgeException extends RuntimeException
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        ?string $message = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message ?? $errorCode->defaultMessage(), 0, $previous);
    }

    public static function make(ErrorCode $code, ?string $message = null): static
    {
        return new static($code, $message);
    }

    public function status(): int
    {
        return $this->errorCode->httpStatus();
    }

    /**
     * @return array{error: array{code: string, message: string}}
     */
    public function toEnvelope(): array
    {
        return ['error' => ['code' => $this->errorCode->value, 'message' => $this->getMessage()]];
    }

    public function render(): JsonResponse
    {
        return new JsonResponse(
            $this->toEnvelope(),
            $this->status(),
            [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * Expected outcome, not a bug: keep it out of the product log.
     */
    public function report(): void {}
}
