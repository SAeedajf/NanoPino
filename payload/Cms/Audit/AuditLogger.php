<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Audit;

use App\com_pinoox_cms\Cms\Authorization\ScopeType;

final class AuditLogger
{
    public function __construct(private readonly AuditRepositoryInterface $repository) {}

    /** @param array<string,mixed> $metadata */
    public function log(
        string $action,
        string $owner,
        AuditOutcome $outcome,
        ?int $actorId = null,
        ScopeType $scopeType = ScopeType::Global,
        string|int|null $scopeId = null,
        ?string $targetType = null,
        string|int|null $targetId = null,
        ?string $correlationId = null,
        array $metadata = [],
    ): AuditEvent {
        $event = AuditEvent::create(
            $action,
            $owner,
            $outcome,
            $actorId,
            $scopeType,
            $scopeId,
            $targetType,
            $targetId,
            $correlationId,
            $metadata,
        );

        $this->repository->append($event);
        return $event;
    }
}
