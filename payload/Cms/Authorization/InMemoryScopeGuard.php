<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Authorization;

final class InMemoryScopeGuard implements ScopeGuardInterface
{
    /** @var array<string,true> */
    private array $grants = [];

    public function grant(int $subjectId, ScopeType $scopeType, string|int $scopeId): void
    {
        $this->grants[$this->key($subjectId, $scopeType, $scopeId)] = true;
    }

    public function allows(AuthorizationRequest $request): bool
    {
        if ($request->scopeType === ScopeType::Global) {
            return true;
        }

        if ($request->subjectId === null || $request->scopeId === null) {
            return false;
        }

        return isset($this->grants[$this->key($request->subjectId, $request->scopeType, $request->scopeId)]);
    }

    private function key(int $subjectId, ScopeType $scopeType, string|int $scopeId): string
    {
        return $subjectId . '|' . $scopeType->value . '|' . (string)$scopeId;
    }
}
