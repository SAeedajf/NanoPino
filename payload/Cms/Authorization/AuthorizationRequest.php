<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Authorization;

use InvalidArgumentException;

final readonly class AuthorizationRequest
{
    /** @param array<string,mixed> $attributes */
    public function __construct(
        public string $capability,
        public ?int $subjectId = null,
        public ScopeType $scopeType = ScopeType::Global,
        public string|int|null $scopeId = null,
        public ?string $resourceType = null,
        public string|int|null $resourceId = null,
        public ?int $resourceOwnerId = null,
        public array $attributes = [],
    ) {
        if ($capability === '' || str_contains($capability, '*')) {
            throw new InvalidArgumentException('Authorization requests require an exact capability key.');
        }

        if ($scopeType !== ScopeType::Global && $scopeId === null) {
            throw new InvalidArgumentException('Non-global authorization scope requires a scope id.');
        }
    }
}
