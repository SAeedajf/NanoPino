<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\UpdateHistory;

final readonly class UpdateHistoryRecorder
{
    public function __construct(private UpdateHistoryRepositoryInterface $repository) {}

    /** @param array<string,mixed> $metadata */
    public function record(
        string $extensionId,
        string $fromVersion,
        int $fromVersionCode,
        string $toVersion,
        int $toVersionCode,
        UpdateHistoryStatus $status,
        ?string $recoveryPointId,
        ?int $actorId,
        array $metadata = [],
    ): UpdateHistoryRecord {
        $record = new UpdateHistoryRecord(
            'uph-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(5)),
            $extensionId,
            $fromVersion,
            $fromVersionCode,
            $toVersion,
            $toVersionCode,
            $status,
            $recoveryPointId,
            $actorId,
            microtime(true),
            $metadata,
        );
        $this->repository->append($record);
        return $record;
    }
}
