<?php

namespace Applu\CentralBridge\Http\Middleware;

use Applu\CentralBridge\Data\Actor;
use Applu\CentralBridge\ErrorCode;
use Applu\CentralBridge\Exceptions\BridgeException;
use Applu\CentralBridge\Headers;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contract §3: every write needs X-Central-Actor and X-Idempotency-Key.
 * Same key + same fingerprint replays the stored answer; same key with a
 * different fingerprint, or still running, is 409.
 */
class EnforceIdempotency
{
    public const ACTOR_ATTRIBUTE = 'central_bridge.actor';

    public const IN_PROGRESS_MESSAGE = 'Outra requisição com esta chave de idempotência ainda está em andamento.';

    private const LOCK_SECONDS = 120;

    public function handle(Request $request, Closure $next): Response
    {
        $email = trim((string) $request->header(Headers::ACTOR, ''));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return BridgeException::make(ErrorCode::MissingActor)->render();
        }

        $key = (string) $request->header(Headers::IDEMPOTENCY_KEY, '');

        if ($key === '' || mb_strlen($key) > 128) {
            return BridgeException::make(ErrorCode::ValidationFailed)->render();
        }

        $request->attributes->set(
            self::ACTOR_ATTRIBUTE,
            new Actor($email, $request->header(Headers::REQUEST_ID)),
        );

        $fingerprint = hash(
            'sha256',
            strtoupper($request->getMethod())."\n".$request->getRequestUri()."\n".$request->getContent(),
        );

        $lock = Cache::lock(self::lockKey($key), self::LOCK_SECONDS);

        if (! $lock->get()) {
            return BridgeException::make(ErrorCode::IdempotencyConflict, self::IN_PROGRESS_MESSAGE)->render();
        }

        try {
            $stored = Cache::get(self::storeKey($key));

            if (is_array($stored)) {
                if (! hash_equals((string) $stored['fingerprint'], $fingerprint)) {
                    return BridgeException::make(ErrorCode::IdempotencyConflict)->render();
                }

                return new Response((string) $stored['content'], (int) $stored['status'], [
                    'Content-Type' => $stored['content_type'] ?? 'application/json',
                    Headers::IDEMPOTENCY_REPLAYED => 'true',
                ]);
            }

            $response = $next($request);

            if ($response->getStatusCode() < 500) {
                Cache::put(self::storeKey($key), [
                    'fingerprint' => $fingerprint,
                    'status' => $response->getStatusCode(),
                    'content' => $response->getContent(),
                    'content_type' => $response->headers->get('Content-Type'),
                ], now()->addHours((int) config('central-bridge.idempotency_ttl_hours', 24)));
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    public static function storeKey(string $key): string
    {
        return 'central-bridge:idempotency:'.hash('sha256', $key);
    }

    public static function lockKey(string $key): string
    {
        return self::storeKey($key).':lock';
    }
}
