<?php

namespace Applu\CentralBridge\Support;

use Illuminate\Support\Str;

/**
 * Last line of defence (contract §5): no 200 response carries a key whose
 * name contains a forbidden segment, except for exact allow-listed paths.
 */
final class ResponseSanitizer
{
    public const FORBIDDEN_SEGMENTS = [
        'password',
        'senha',
        'hash',
        'token',
        'secret',
        'segredo',
        'document',
        'documento',
        'cpf',
        'cnpj',
        'remember',
        'recovery',
    ];

    /**
     * @param  array<mixed>  $data
     * @param  list<string>  $allow  exact dot paths (list indices ignored), e.g. "taker.document"
     * @return array<mixed>
     */
    public static function sanitize(array $data, array $allow = [], string $prefix = ''): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if (is_int($key)) {
                $clean[$key] = is_array($value) ? self::sanitize($value, $allow, $prefix) : $value;

                continue;
            }

            $path = $prefix === '' ? $key : $prefix.'.'.$key;

            if (self::isForbidden($key) && ! in_array($path, $allow, true)) {
                continue;
            }

            $clean[$key] = is_array($value) ? self::sanitize($value, $allow, $path) : $value;
        }

        return $clean;
    }

    public static function isForbidden(string $key): bool
    {
        $snake = Str::snake(self::normalizeSeparators($key));
        $lower = self::normalizeSeparators(strtolower($key));

        $segments = array_merge(explode('_', strtolower($snake)), explode('_', $lower));

        return array_intersect($segments, self::FORBIDDEN_SEGMENTS) !== [];
    }

    private static function normalizeSeparators(string $key): string
    {
        return str_replace(['-', ' '], '_', $key);
    }
}
