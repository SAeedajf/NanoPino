<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\RateLimit;

use Pinoox\Component\Http\Request;
use Pinoox\Component\RateLimiter\Limit;
use Pinoox\Component\RateLimiter\RateLimiter;
use Pinoox\Portal\Auth;

/**
 * Thin adapter over native Pincore RateLimiter/ThrottleFlow.
 * It does not implement counters or 429 response handling itself.
 */
final class PinooxRateLimiterRegistrar
{
    public function register(RateLimiter $rates): void
    {
        foreach (CoreRateLimitProfiles::all() as $profile) {
            $rates->define($profile->name, static function (Request $request) use ($profile): Limit {
                $ip = (string)($request->getClientIp() ?: 'unknown');
                $subject = self::subjectFromRequest($request);
                $key = match ($profile->keyStrategy) {
                    'ip' => $ip,
                    'subject_or_ip' => $subject !== null ? 'u:' . $subject : 'ip:' . $ip,
                    'ip_and_subject' => 'ip:' . $ip . '|u:' . ($subject ?? 'anon'),
                };

                return (new Limit($profile->maxAttempts, $profile->decaySeconds))
                    ->by($key)
                    ->message('Too Many Requests.');
            });
        }
    }

    private static function subjectFromRequest(Request $request): ?string
    {
        $value = $request->attributes->get('cms_subject_id');
        if ($value !== null && $value !== '') return (string)$value;
        try{Auth::boot();$id=Auth::id();return $id!==null?(string)$id:null;}catch(\Throwable){return null;}
    }
}
