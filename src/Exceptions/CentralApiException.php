<?php

namespace Applu\CentralBridge\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Non-2xx answer (or transport failure) from the Central invoicing API.
 */
class CentralApiException extends RuntimeException
{
    public const CONNECTION_FAILED = 'connection_failed';

    public const UNEXPECTED_RESPONSE = 'unexpected_response';

    /**
     * @param  array<mixed>|null  $body
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 0,
        public readonly ?array $body = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }
}
