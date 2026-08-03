<?php

namespace App\Support;

class AgentTaskInputSanitizer
{
    private const SENSITIVE_KEYS = [
        '_token',
        'api_key',
        'authorization',
        'cookie',
        'csrf',
        'csrf_token',
        'password',
        'secret',
        'token',
    ];

    public static function sanitize(mixed $input): mixed
    {
        if (! is_array($input)) {
            return $input;
        }

        $sanitized = [];

        foreach ($input as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
                continue;
            }

            $sanitized[$key] = self::sanitize($value);
        }

        return $sanitized;
    }
}
