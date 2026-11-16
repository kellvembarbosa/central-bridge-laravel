<?php

namespace Applu\CentralBridge\Http\Middleware;

use Applu\CentralBridge\ErrorCode;
use Applu\CentralBridge\Exceptions\BridgeException;
use Applu\CentralBridge\Headers;
use Applu\CentralBridge\Signature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contract §2. Check order: disabled (404) -> IP (403) -> missing token /
 * signature / invalid timestamp (401 unauthorized) -> clock (401
 * timestamp_skew) -> signature (401 signature_invalid).
 */
class VerifyCentralSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('central-bridge.enabled')) {
            return $this->fail(ErrorCode::NotFound);
        }

        $allowedIps = array_values(array_filter((array) config('central-bridge.allowed_ips', [])));

        if ($allowedIps !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowedIps)) {
            return $this->fail(ErrorCode::IpNotAllowed);
        }

        $token = (string) config('central-bridge.token', '');
        $signature = (string) $request->header(Headers::SIGNATURE, '');
        $timestamp = $request->header(Headers::TIMESTAMP);

        if ($token === '' || $signature === '' || ! Signature::isValidTimestamp($timestamp)) {
            return $this->fail(ErrorCode::Unauthorized);
        }

        $maxSkew = (int) config('central-bridge.max_skew_seconds', 300);

        if (! Signature::isFresh((int) $timestamp, Carbon::now()->getTimestamp(), $maxSkew)) {
            return $this->fail(ErrorCode::TimestampSkew);
        }

        $valid = Signature::verify(
            $token,
            $request->getMethod(),
            $request->getRequestUri(),
            $timestamp,
            $request->getContent(),
            $signature,
        );

        if (! $valid) {
            return $this->fail(ErrorCode::SignatureInvalid);
        }

        return $next($request);
    }

    private function fail(ErrorCode $code): Response
    {
        return BridgeException::make($code)->render();
    }
}
