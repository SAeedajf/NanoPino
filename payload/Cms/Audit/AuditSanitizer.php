<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Audit;

final class AuditSanitizer
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'authorization',
        'secret',
        'client_secret',
        'private_key',
        'api_key',
        'cookie',
    ];

    /** @return array<string,mixed> */
    public static function sanitize(array $metadata): array
    {
        return self::walk($metadata, 0);
    }

    /** @return array<string,mixed> */
    private static function walk(array $data, int $depth): array
    {
        if ($depth >= 6) {
            return ['_truncated' => true];
        }

        $result = [];
        foreach ($data as $key => $value) {
            $name = strtolower((string)$key);

            if (self::isSensitive($name)) {
                $result[(string)$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $result[(string)$key] = self::walk($value, $depth + 1);
                continue;
            }

            if (is_object($value)) {
                $result[(string)$key] = '[OBJECT:' . $value::class . ']';
                continue;
            }

            if (is_resource($value)) {
                $result[(string)$key] = '[RESOURCE]';
                continue;
            }

            if (is_string($value) && strlen($value) > 4096) {
                $result[(string)$key] = substr($value, 0, 4096) . '…';
                continue;
            }

            $result[(string)$key] = $value;
        }

        return $result;
    }

    private static function isSensitive(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($key === $sensitive || str_ends_with($key, '_' . $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
