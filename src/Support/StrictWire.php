<?php

namespace Applu\CentralBridge\Support;

use Applu\CentralBridge\ErrorCode;
use Applu\CentralBridge\Exceptions\BridgeException;

/**
 * Defensive readers for wire bodies that come over the network (checkout,
 * contract §10). Any wrong type throws `validation_failed` (422) naming the field.
 */
final class StrictWire
{
    public static function invalid(string $field): never
    {
        throw BridgeException::make(
            ErrorCode::ValidationFailed,
            sprintf('Dados inválidos: o campo "%s" é inválido.', $field),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function object(mixed $value, string $field): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            self::invalid($field);
        }

        return $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function objectOrNull(mixed $value, string $field): ?array
    {
        return $value === null ? null : self::object($value, $field);
    }

    /** Non-empty string. */
    public static function string(array $data, string $key, string $path = ''): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || $value === '') {
            self::invalid($path.$key);
        }

        return $value;
    }

    public static function stringOrNull(array $data, string $key, string $path = ''): ?string
    {
        $value = $data[$key] ?? null;

        if ($value !== null && ! is_string($value)) {
            self::invalid($path.$key);
        }

        return $value;
    }

    public static function int(array $data, string $key, string $path = ''): int
    {
        $value = $data[$key] ?? null;

        if (! is_int($value)) {
            self::invalid($path.$key);
        }

        return $value;
    }

    public static function intOrNull(array $data, string $key, string $path = ''): ?int
    {
        return ($data[$key] ?? null) === null ? null : self::int($data, $key, $path);
    }

    public static function bool(array $data, string $key, string $path = ''): bool
    {
        $value = $data[$key] ?? null;

        if (! is_bool($value)) {
            self::invalid($path.$key);
        }

        return $value;
    }

    /**
     * One of the allowed values.
     *
     * @param  list<string>  $allowed
     */
    public static function oneOf(array $data, string $key, array $allowed, string $path = ''): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            self::invalid($path.$key);
        }

        return $value;
    }

    /**
     * String-to-string map (absent/null = empty).
     *
     * @return array<string, string>
     */
    public static function stringMap(mixed $value, string $field): array
    {
        if ($value === null) {
            return [];
        }

        $out = [];

        foreach (self::object($value, $field) as $key => $item) {
            if (! is_string($item)) {
                self::invalid($field.'.'.$key);
            }

            $out[(string) $key] = $item;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function stringList(mixed $value, string $field): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        foreach ($value as $i => $item) {
            if (! is_string($item)) {
                self::invalid($field.'.'.$i);
            }
        }

        return $value;
    }
}
