<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Http;

final readonly class ApiSecurityRequirement
{
    public function __construct(
        public string $scope,
        public string $rateLimit,
        public bool $sessionMutationRequiresCsrf,
        public bool $siteScopeRequired,
    ) {}
}
