<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

interface SnapshotProviderInterface
{
    public function id(): string;

    /**
     * Create provider-specific snapshot material.
     * @return array<string,mixed> serializable receipt required for restore/delete
     */
    public function create(RecoveryPoint $point): array;

    /** @param array<string,mixed> $receipt */
    public function restore(RecoveryPoint $point, array $receipt): void;

    /** @param array<string,mixed> $receipt */
    public function delete(RecoveryPoint $point, array $receipt): void;
}
