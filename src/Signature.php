<?php

namespace Applu\CentralBridge;

/**
 * HMAC scheme shared by the Central and every product (contract §2).
 *
 * payload   = UPPER(method) \n pathWithQuery \n timestamp \n sha256_hex(rawBody)
 * signature = hex(hmac_sha256(token, payload))
 */
final class Signature
{
    public static function payload(string $method, string $pathWithQuery, int|string $timestamp, string $body): string
    {
        return strtoupper($method)."\n".$pathWithQuery."\n".$timestamp."\n".hash('sha256', $body);
    }

    public static function make(string $token, string $method, string $pathWithQuery, int|string $timestamp, string $body = ''): string
    {
        return hash_hmac('sha256', self::payload($method, $pathWithQuery, $timestamp, $body), $token);
    }

    public static function verify(
        string $token,
        string $method,
        string $pathWithQuery,
        int|string $timestamp,
        string $body,
        string $signature,
    ): bool {
        if ($token === '' || $signature === '') {
            return false;
        }

        return hash_equals(
            self::make($token, $method, $pathWithQuery, $timestamp, $body),
            strtolower($signature),
        );
    }

    public static function isFresh(int $timestamp, int $now, int $maxSkewSeconds = 300): bool
    {
        return abs($now - $timestamp) <= $maxSkewSeconds;
    }

    /**
     * Header format: digits only, at most 12 of them.
     */
    public static function isValidTimestamp(?string $value): bool
    {
        return $value !== null && preg_match('/^\d{1,12}$/', $value) === 1;
    }
}
