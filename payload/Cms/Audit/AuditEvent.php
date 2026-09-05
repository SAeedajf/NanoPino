<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Audit;

use App\com_pinoox_cms\Cms\Authorization\ScopeType;
use InvalidArgumentException;

final readonly class AuditEvent
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public string $id,
        public string $action,
        public string $owner,
        public AuditOutcome $outcome,
        public float $occurredAt,
        public ?int $actorId = null,
        public ScopeType $scopeType = ScopeType::Global,
        public string|int|null $scopeId = null,
        public ?string $targetType = null,
        public string|int|null $targetId = null,
        public ?string $correlationId = null,
        public array $metadata = [],
    ) {
        if (preg_match('#^[a-z0-9][a-z0-9._/-]{1,190}$#', $action) !== 1) {
            throw new InvalidArgumentException('Invalid audit action.');
        }

        if ($owner === '') {
            throw new InvalidArgumentException('Audit owner is required.');
        }
    }

    /** @param array<string,mixed> $metadata */
    public static function create(
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
    ): self {
        return new self(
            'audit-' . bin2hex(random_bytes(12)),
            $action,
            $owner,
            $outcome,
            microtime(true),
            $actorId,
            $scopeType,
            $scopeId,
            $targetType,
            $targetId,
            $correlationId,
            AuditSanitizer::sanitize($metadata),
        );
    }
}
