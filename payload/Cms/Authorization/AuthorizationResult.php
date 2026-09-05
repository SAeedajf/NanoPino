<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Authorization;

final readonly class AuthorizationResult
{
    /**
     * @param list<array{id:string,owner:string,decision:string}> $policies
     */
    public function __construct(
        public bool $allowed,
        public string $reason,
        public bool $capabilityGranted,
        public bool $scopeGranted,
        public array $policies = [],
    ) {}
}
