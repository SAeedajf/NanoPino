<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\RateLimit;

use Pinoox\Component\RateLimiter\RateLimiter as NativeRateLimiter;
use Pinoox\Portal\Cache;
use Pinoox\Portal\Config;
use Pinoox\Portal\RateLimiter as RateLimiterPortal;

/**
 * Compatibility bridge for the native Pinoox RateLimiter portal.
 *
 * Pincore 3.8.15 through current 3.x releases register the portal by passing
 * a Closure directly to Symfony Definition::setFactory(). Symfony DI 7.2+
 * rejects Closure factories at definition-build time. We preserve the native
 * Pinoox component and portal service id, and only recover from that exact
 * upstream type mismatch. No rate-limit counters or throttle logic are
 * reimplemented here.
 */
final class PinooxRateLimiterBridge
{
    public static function resolve(): NativeRateLimiter
    {
        try {
            $rates = RateLimiterPortal::___();
            if ($rates instanceof NativeRateLimiter) {
                return $rates;
            }
        } catch (\TypeError $error) {
            if (!self::isUnsupportedClosureFactory($error)) {
                throw $error;
            }

            return self::repairNativePortalService();
        }

        throw new \RuntimeException('Native Pinoox RateLimiter portal did not resolve a RateLimiter component.');
    }

    private static function repairNativePortalService(): NativeRateLimiter
    {
        $container = RateLimiterPortal::__container();
        $serviceId = RateLimiterPortal::__id();

        // __register() creates the Definition before Symfony rejects the
        // Closure factory, so remove that incomplete definition first.
        if ($container->hasDefinition($serviceId)) {
            $container->removeDefinition($serviceId);
        }

        // A successfully-created service from another bootstrap path wins.
        if ($container->has($serviceId)) {
            $existing = $container->get($serviceId);
            if ($existing instanceof NativeRateLimiter) {
                return $existing;
            }
        }

        $rates = new NativeRateLimiter(Cache::___(), self::prefix());
        $container->set($serviceId, $rates);

        return $rates;
    }

    private static function prefix(): string
    {
        $prefix = 'pinoox_rate:';

        try {
            $config = Config::name('~rate_limiter')->get() ?? [];
            if (is_array($config) && isset($config['prefix'])) {
                $configured = (string) $config['prefix'];
                if ($configured !== '') {
                    $prefix = $configured;
                }
            }
        } catch (\Throwable) {
            // Match Pincore's native fallback when config is unavailable.
        }

        return $prefix;
    }

    private static function isUnsupportedClosureFactory(\TypeError $error): bool
    {
        $message = $error->getMessage();

        return str_contains($message, 'Symfony\\Component\\DependencyInjection\\Definition::setFactory()')
            && str_contains($message, 'Closure given');
    }
}
