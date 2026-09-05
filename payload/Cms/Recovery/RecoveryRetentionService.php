<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

final readonly class RecoveryRetentionService
{
    public function __construct(
        private RecoveryPointRepositoryInterface $repository,
        private RecoveryManager $manager,
        private RecoveryRetentionPolicy $policy,
    ) {}

    /** @return list<string> deleted Recovery Point IDs */
    public function prune(string $extensionId, ?float $now = null): array
    {
        $now ??= microtime(true);
        $points = $this->repository->forExtension($extensionId);
        $readyKept = 0;
        $kept = 0;
        $deleted = [];

        foreach ($points as $point) {
            $isReady = $point->status === RecoveryPointStatus::Ready;
            $ageSeconds = max(0.0, $now - $point->createdAt);
            $expired = $ageSeconds > ($this->policy->maxAgeDays * 86400);

            $mustKeepReady = $isReady && $readyKept < $this->policy->minimumReadyPoints;
            $overCount = $kept >= $this->policy->maxPerExtension;

            if (!$mustKeepReady && ($expired || $overCount)) {
                $this->manager->delete($point->id);
                $deleted[] = $point->id;
                continue;
            }

            ++$kept;
            if ($isReady) ++$readyKept;
        }

        return $deleted;
    }
}
