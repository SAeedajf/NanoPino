<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

final readonly class RecoveryCatalogService
{
    public function __construct(
        private RecoveryPointRepositoryInterface $repository,
        private SafeModeManager $safeMode,
    ) {}

    /** @return list<RecoveryCatalogItem> */
    public function forExtension(string $extensionId): array
    {
        $safe = $this->safeMode->state();
        return array_map(
            static fn (RecoveryPoint $point): RecoveryCatalogItem => new RecoveryCatalogItem(
                $point,
                in_array($point->status, [RecoveryPointStatus::Ready, RecoveryPointStatus::Failed], true),
                $safe->recoveryPointId === $point->id,
            ),
            $this->repository->forExtension($extensionId),
        );
    }
}
