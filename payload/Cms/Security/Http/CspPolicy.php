<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Http;

final readonly class CspPolicy
{
    /**
     * @param array<string,list<string>> $extraDirectives
     */
    public function __construct(
        public bool $reportOnly = true,
        public array $extraDirectives = [],
    ) {}

    public function nonce(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }

    public function header(string $nonce): string
    {
        if (preg_match('/^[A-Za-z0-9_-]{16,64}$/', $nonce) !== 1) {
            throw new \InvalidArgumentException('Invalid CSP nonce.');
        }

        $directives = [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
            'frame-ancestors' => ["'none'"],
            'form-action' => ["'self'"],
            'script-src' => ["'self'", "'nonce-" . $nonce . "'"],
            'style-src' => ["'self'"],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => ["'self'"],
            'media-src' => ["'self'", 'blob:'],
            'worker-src' => ["'self'", 'blob:'],
            'manifest-src' => ["'self'"],
        ];

        foreach ($this->extraDirectives as $name => $sources) {
            if (preg_match('/^[a-z][a-z0-9-]{1,63}$/', (string)$name) !== 1 || !is_array($sources)) {
                throw new \InvalidArgumentException('Invalid CSP directive.');
            }
            $safe = [];
            foreach ($sources as $source) {
                if (!is_string($source) || $source === '' || preg_match('/[\r\n;]/', $source)) {
                    throw new \InvalidArgumentException('Invalid CSP source.');
                }
                $safe[] = $source;
            }
            $directives[(string)$name] = array_values(array_unique($safe));
        }

        $parts = [];
        foreach ($directives as $name => $sources) {
            $parts[] = $name . ' ' . implode(' ', $sources);
        }
        return implode('; ', $parts);
    }

    public function headerName(): string
    {
        return $this->reportOnly
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';
    }
}
