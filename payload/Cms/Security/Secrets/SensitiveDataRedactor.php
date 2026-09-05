<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Secrets;

final class SensitiveDataRedactor
{
    private const KEYS = [
        'password','password_confirmation','token','access_token','refresh_token',
        'authorization','secret','client_secret','private_key','api_key',
        'cookie','set_cookie','session','credential','signature',
    ];

    public function redact(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 8) return '[TRUNCATED]';

        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $name = strtolower((string)$key);
                $out[$key] = $this->sensitiveKey($name)
                    ? '[REDACTED]'
                    : $this->redact($item, $depth + 1);
            }
            return $out;
        }

        if (is_object($value)) return '[OBJECT:' . $value::class . ']';
        if (is_resource($value)) return '[RESOURCE]';

        if (!is_string($value)) return $value;

        if (preg_match('/-----BEGIN [A-Z ]*PRIVATE KEY-----/', $value)) {
            return '[REDACTED_PRIVATE_KEY]';
        }
        if (preg_match('/\bBearer\s+[A-Za-z0-9._~+\/=-]{12,}/i', $value)) {
            return preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/=-]{12,}/i', 'Bearer [REDACTED]', $value);
        }
        if (preg_match('/\beyJ[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\b/', $value)) {
            return '[REDACTED_JWT]';
        }

        return strlen($value) > 8192 ? substr($value, 0, 8192) . '…' : $value;
    }

    private function sensitiveKey(string $key): bool
    {
        $normalized = str_replace('-', '_', $key);
        foreach (self::KEYS as $sensitive) {
            if ($normalized === $sensitive || str_ends_with($normalized, '_' . $sensitive)) return true;
        }
        return false;
    }
}
