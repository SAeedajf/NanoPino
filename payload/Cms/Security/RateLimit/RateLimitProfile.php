<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\RateLimit;

final readonly class RateLimitProfile
{
    public function __construct(
        public string $name,
        public int $maxAttempts,
        public int $decaySeconds,
        public string $keyStrategy,
    ) {
        if (
            preg_match('/^[a-z][a-z0-9._-]{1,63}$/', $name) !== 1
            || $maxAttempts < 1
            || $maxAttempts > 100000
            || $decaySeconds < 1
            || $decaySeconds > 86400
            || !in_array($keyStrategy, ['ip','subject_or_ip','ip_and_subject'], true)
        ) {
            throw new \InvalidArgumentException('Invalid rate-limit profile.');
        }
    }
}
