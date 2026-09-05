<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\UpdatePolicy;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Recovery\RecoveryCatalogService;
use App\com_pinoox_cms\Cms\UpdateHistory\UpdateHistoryRepositoryInterface;

final readonly class UpdateCenterService
{
    public function __construct(
        private AuthorizationManager $authorization,
        private ExtensionUpdatePolicyRepositoryInterface $policies,
        private UpdateCandidateSelector $selector,
        private UpdateHistoryRepositoryInterface $history,
        private RecoveryCatalogService $recovery,
    ) {}

    public function policy(string $extensionId, ?int $actorId = null): ExtensionUpdatePolicy
    {
        $this->authorization->authorize(new AuthorizationRequest('extensions.read', $actorId));

        return $this->policies->find($extensionId)
            ?? new ExtensionUpdatePolicy($extensionId);
    }

    public function savePolicy(ExtensionUpdatePolicy $policy, ?int $actorId = null): ExtensionUpdatePolicy
    {
        $this->authorization->authorize(new AuthorizationRequest('extensions.update', $actorId));
        $this->policies->save($policy);
        return $policy;
    }

    /**
     * @param list<UpdateCandidate> $candidates
     */
    public function selectCandidate(
        string $extensionId,
        int $installedVersionCode,
        array $candidates,
        ?int $actorId = null,
    ): ?UpdateCandidate {
        $this->authorization->authorize(new AuthorizationRequest('extensions.read', $actorId));
        $policy = $this->policies->find($extensionId) ?? new ExtensionUpdatePolicy($extensionId);

        return $this->selector->select(
            $extensionId,
            $installedVersionCode,
            $policy,
            $candidates,
        );
    }

    /** @return list<array<string,mixed>> */
    public function history(string $extensionId, int $limit = 100, ?int $actorId = null): array
    {
        $this->authorization->authorize(new AuthorizationRequest('extensions.read', $actorId));

        return array_map(
            static fn ($record): array => $record->toArray(),
            $this->history->forExtension($extensionId, $limit),
        );
    }

    /** @return list<array<string,mixed>> */
    public function recoveryPoints(string $extensionId, ?int $actorId = null): array
    {
        $this->authorization->authorize(new AuthorizationRequest('system.recovery', $actorId));

        return array_map(
            static fn ($item): array => $item->toArray(),
            $this->recovery->forExtension($extensionId),
        );
    }
}
