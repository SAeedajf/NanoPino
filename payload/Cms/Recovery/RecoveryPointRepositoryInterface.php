<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

interface RecoveryPointRepositoryInterface
{
    public function save(RecoveryPoint $point): void;
    public function find(string $id): ?RecoveryPoint;

    /** @return list<RecoveryPoint> */
    public function forExtension(string $extensionId): array;

    public function delete(string $id): void;
}
