<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\UpdateHistory;

interface UpdateHistoryRepositoryInterface
{
    public function append(UpdateHistoryRecord $record): void;

    /** @return list<UpdateHistoryRecord> */
    public function forExtension(string $extensionId, int $limit = 100): array;

    /** @return list<UpdateHistoryRecord> */
    public function recent(int $limit = 100): array;
}
