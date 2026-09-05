<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Authorization;

use App\com_pinoox_cms\Cms\Capability\CapabilityRegistry;

final class AuthorizationManager
{
    public function __construct(
        private readonly CapabilityRegistry $capabilities,
        private readonly PolicyRegistry $policies,
        private readonly AccessGatewayInterface $access,
        private readonly ScopeGuardInterface $scopeGuard = new GlobalOnlyScopeGuard(),
    ) {}

    public function decide(AuthorizationRequest $request): AuthorizationResult
    {
        if (!$this->capabilities->has($request->capability)) {
            return new AuthorizationResult(false, 'CAPABILITY_NOT_REGISTERED', false, false);
        }

        $capabilityGranted = $this->access->can($request->capability, $request->subjectId);
        if (!$capabilityGranted) {
            return new AuthorizationResult(false, 'CAPABILITY_DENIED', false, false);
        }

        $scopeGranted = $this->scopeGuard->allows($request);
        if (!$scopeGranted) {
            return new AuthorizationResult(false, 'SCOPE_DENIED', true, false);
        }

        $rows = [];
        foreach ($this->policies->forCapability($request->capability) as $policy) {
            $decision = $policy->evaluate($request);
            $rows[] = [
                'id' => $policy->identifier(),
                'owner' => $policy->owner(),
                'decision' => $decision->value,
            ];

            if ($decision === PolicyDecision::Deny) {
                return new AuthorizationResult(false, 'POLICY_DENIED', true, true, $rows);
            }
        }

        return new AuthorizationResult(true, 'ALLOWED', true, true, $rows);
    }

    public function can(AuthorizationRequest $request): bool
    {
        return $this->decide($request)->allowed;
    }

    public function authorize(AuthorizationRequest $request): void
    {
        $result = $this->decide($request);
        if (!$result->allowed) {
            throw new AuthorizationDeniedException($result);
        }
    }
}
