<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Ability;

use App\com_pinoox_cms\Cms\Authorization\ScopeType;

final readonly class AbilityExecutionContext
{
    public function __construct(
        public ?int $actorId = null,
        public AbilitySource $source = AbilitySource::Internal,
        public ScopeType $scopeType = ScopeType::Global,
        public string|int|null $scopeId = null,
        public ?string $correlationId = null,
        public ?string $idempotencyKey = null,
    ) {}

    public function correlationIdOrCreate(): string
    {
        return $this->correlationId ?: 'corr-' . bin2hex(random_bytes(10));
    }
}
