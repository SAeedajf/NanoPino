<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Compatibility;

final class CoreKernelCompatibilityProfile
{
    public const MIN_VERSION_NAME='3.14.0';
    public const MIN_VERSION_CODE=232;

    /** @return list<KernelPrimitiveRequirement> */
    public static function requirements(): array
    {
        return [
            new KernelPrimitiveRequirement(
                'app.register',
                'Pinoox\\Component\\AppEvent\\AppRegister',
                ['package','route','api','schedule','listen','when'],
                true,
                'extension-runtime',
            ),
            new KernelPrimitiveRequirement(
                'app.bootstrap',
                'Pinoox\\Component\\AppEvent\\AppBootstrap',
                ['ensure','integrate','applyRoutes'],
                true,
                'extension-runtime',
            ),
            new KernelPrimitiveRequirement(
                'app.response_event',
                'Pinoox\\Component\\AppEvent\\AppResponseEvent',
                [],
                false,
                'security-headers',
            ),
            new KernelPrimitiveRequirement(
                'rate_limiter',
                'Pinoox\\Component\\RateLimiter\\RateLimiter',
                ['define','limiter'],
                false,
                'rate-limit',
            ),
            new KernelPrimitiveRequirement(
                'schedule',
                'Pinoox\\Cron\\Schedule',
                ['call'],
                false,
                'queue-scheduler',
            ),
            new KernelPrimitiveRequirement(
                'scheduled_task',
                'Pinoox\\Cron\\ScheduledTask',
                ['everyMinute','withoutOverlapping'],
                false,
                'queue-scheduler',
            ),
            new KernelPrimitiveRequirement(
                'storage',
                'Pinoox\\Portal\\Storage',
                [],
                true,
                'storage',
            ),
            new KernelPrimitiveRequirement(
                'cache',
                'Pinoox\\Portal\\Cache',
                [],
                true,
                'cache',
            ),
            new KernelPrimitiveRequirement(
                'file',
                'Pinoox\\Portal\\File',
                [],
                true,
                'media',
            ),
            new KernelPrimitiveRequirement(
                'app_engine',
                'Pinoox\\Component\\Package\\Engine\\AppEngine',
                ['path','config','packagePaths'],
                true,
                'runtime',
            ),
            new KernelPrimitiveRequirement(
                'controller',
                'Pinoox\\Component\\Kernel\\Controller\\Controller',
                [],
                true,
                'http',
            ),
        ];
    }
}
