<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\RateLimit;

final class CoreRateLimitProfiles
{
    /** @return list<RateLimitProfile> */
    public static function all(): array
    {
        return [
            new RateLimitProfile('cms.api.read', 300, 60, 'subject_or_ip'),
            new RateLimitProfile('cms.api.write', 120, 60, 'subject_or_ip'),
            new RateLimitProfile('cms.auth.sensitive', 10, 60, 'ip_and_subject'),
            new RateLimitProfile('cms.upload.media', 30, 60, 'subject_or_ip'),
            new RateLimitProfile('cms.upload.extension', 5, 300, 'ip_and_subject'),
            new RateLimitProfile('cms.search', 120, 60, 'subject_or_ip'),
            new RateLimitProfile('cms.recovery', 10, 300, 'ip_and_subject'),
        ];
    }

    public static function get(string $name): RateLimitProfile
    {
        foreach (self::all() as $profile) {
            if ($profile->name === $name) return $profile;
        }
        throw new \RuntimeException('Rate-limit profile not found: ' . $name);
    }
}
