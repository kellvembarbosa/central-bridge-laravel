<?php

namespace Applu\CentralBridge\Support;

use DateTimeInterface;
use stdClass;

/**
 * Small helpers that keep wire shapes stable.
 */
final class Wire
{
    /**
     * ISO-8601 timestamp (or null). Accepts DateTimeInterface or string.
     */
    public static function dateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof DateTimeInterface ? $value->format(DATE_ATOM) : (string) $value;
    }

    /**
     * Calendar date YYYY-MM-DD (or null).
     */
    public static function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
    }

    /**
     * Key/value map that must serialize as a JSON object, even when empty.
     *
     * @param  array<string, mixed>  $map
     * @return array<string, mixed>|stdClass
     */
    public static function map(array $map): array|stdClass
    {
        return $map === [] ? new stdClass : $map;
    }

    public static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
