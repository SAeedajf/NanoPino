<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\UpdateHistory;

use RuntimeException;

final readonly class FileUpdateHistoryRepository implements UpdateHistoryRepositoryInterface
{
    public function __construct(private string $file) {}

    public function append(UpdateHistoryRecord $record): void
    {
        $directory = dirname($this->file);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create update history directory.');
        }

        $line = json_encode($record->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
        if (file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Unable to append update history.');
        }
    }

    public function forExtension(string $extensionId, int $limit = 100): array
    {
        return array_values(array_filter(
            $this->recent(max(1, min(1000, $limit * 10))),
            static fn (UpdateHistoryRecord $record): bool => $record->extensionId === $extensionId,
        ));
    }

    public function recent(int $limit = 100): array
    {
        if (!is_file($this->file)) return [];

        $lines = file($this->file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $records = [];
        foreach (array_reverse($lines) as $line) {
            $data = json_decode($line, true);
            if (is_array($data)) $records[] = UpdateHistoryRecord::fromArray($data);
            if (count($records) >= max(1, min(1000, $limit))) break;
        }
        return $records;
    }
}
