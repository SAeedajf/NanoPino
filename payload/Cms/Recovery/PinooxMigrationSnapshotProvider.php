<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

use Pinoox\Component\Migration\MigrationQuery;
use Pinoox\Component\Migration\Migrator;

final readonly class PinooxMigrationSnapshotProvider implements SnapshotProviderInterface
{
    public function __construct(private string $package)
    {
        if (preg_match('/^com_[a-z0-9][a-z0-9_]{1,126}$/', $package) !== 1) {
            throw new \InvalidArgumentException('Invalid Pinoox package for migration recovery.');
        }
    }

    public function id(): string { return 'migrations'; }

    public function create(RecoveryPoint $point): array
    {
        return [
            'package' => $this->package,
            'baseline' => $this->state(),
        ];
    }

    public function restore(RecoveryPoint $point, array $receipt): void
    {
        $package = (string)($receipt['package'] ?? '');
        if ($package !== $this->package) {
            throw new \RuntimeException('Migration recovery package mismatch.');
        }

        $baseline = $this->normalizeState($receipt['baseline'] ?? []);
        $current = $this->state();

        $added = array_diff_key($current, $baseline);
        if ($added === []) {
            if ($current !== $baseline) {
                throw new \RuntimeException('Migration state changed outside recoverable additions.');
            }
            return;
        }

        $batches = array_values(array_unique(array_map('intval', array_values($added))));
        sort($batches, SORT_NUMERIC);

        if ($batches === [] || min($batches) < 1) {
            throw new \RuntimeException('Migration recovery found an invalid batch.');
        }

        $currentBatches = array_values(array_unique(array_map('intval', array_values($current))));
        sort($currentBatches, SORT_NUMERIC);
        $maxCurrent = $currentBatches === [] ? 0 : max($currentBatches);
        $expected = range($maxCurrent - count($batches) + 1, $maxCurrent);

        if ($batches !== $expected) {
            throw new \RuntimeException('Refusing migration rollback across unrelated batches.');
        }

        foreach ($current as $migration => $batch) {
            if (isset($baseline[$migration]) && in_array((int)$batch, $batches, true)) {
                throw new \RuntimeException('Refusing migration rollback that includes baseline migrations.');
            }
        }

        (new Migrator(
            $this->package,
            'rollback',
            ['force' => true, 'use_transactions' => true],
        ))->rollback(count($batches));

        if ($this->state() !== $baseline) {
            throw new \RuntimeException('Migration recovery did not return to the captured state.');
        }
    }

    public function delete(RecoveryPoint $point, array $receipt): void
    {
        // Migration receipts contain metadata only; no external material is retained.
    }

    /** @return array<string,int> */
    private function state(): array
    {
        $rows = MigrationQuery::fetchAllByBatch(null, $this->package);
        $state = [];

        foreach (is_iterable($rows) ? $rows : [] as $row) {
            $migration = $this->field($row, 'migration');
            $batch = $this->field($row, 'batch');
            if (!is_string($migration) || $migration === '' || !is_numeric($batch)) continue;
            $state[$migration] = (int)$batch;
        }

        ksort($state, SORT_STRING);
        return $state;
    }

    /** @return array<string,int> */
    private function normalizeState(mixed $raw): array
    {
        if (!is_array($raw)) {
            throw new \RuntimeException('Migration recovery receipt is invalid.');
        }

        $state = [];
        foreach ($raw as $migration => $batch) {
            if (!is_string($migration) || $migration === '' || !is_numeric($batch)) {
                throw new \RuntimeException('Migration recovery receipt contains invalid state.');
            }
            $state[$migration] = (int)$batch;
        }
        ksort($state, SORT_STRING);
        return $state;
    }

    private function field(mixed $row, string $key): mixed
    {
        if (is_array($row)) return $row[$key] ?? null;
        if (is_object($row)) return $row->{$key} ?? null;
        return null;
    }
}
