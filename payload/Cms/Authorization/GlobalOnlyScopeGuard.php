<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Authorization;

final class GlobalOnlyScopeGuard implements ScopeGuardInterface
{
    public function allows(AuthorizationRequest $request): bool
    {
        return $request->scopeType === ScopeType::Global;
    }
}
