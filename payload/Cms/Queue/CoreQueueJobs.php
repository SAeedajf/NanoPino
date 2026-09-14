<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Queue;

use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;

final class CoreQueueJobs
{
    public static function register(QueueRegistry $registry, string $owner = 'cms.core'): void
    {
        $registry->register(new QueueJobDefinition(
            'content.publish_due',
            $owner,
            static function (array $payload, QueueJobContext $context): void {
                $limit = max(1, min(100, (int) ($payload['limit'] ?? 50)));
                CmsRuntimeServices::content()->publishDue(
                    $limit,
                    null,
                    $context->correlationId ?? $context->id,
                );
            },
            maxAttempts: 3,
            baseBackoffSeconds: 30,
            timeoutSeconds: 120,
            maxPayloadBytes: 4096,
        ));
    }
}
