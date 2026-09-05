<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Authorization;

interface ScopeGuardInterface
{
    public function allows(AuthorizationRequest $request): bool;
}
