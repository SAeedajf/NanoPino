<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Settings;

final class InMemorySettingsRepository implements SettingsRepositoryInterface
{
    /** @var array<string,SettingRecord> */
    private array $records = [];

    public function find(string $key, SettingScope $scope): ?SettingRecord
    {
        return $this->records[$this->storageKey($key, $scope)] ?? null;
    }

    public function put(
        string $key,
        SettingScope $scope,
        SettingType $type,
        mixed $value,
        ?int $updatedBy,
        ?int $expectedVersion = null,
    ): SettingRecord {
        $storageKey = $this->storageKey($key, $scope);
        $current = $this->records[$storageKey] ?? null;

        if ($expectedVersion !== null && ($current?->version ?? 0) !== $expectedVersion) {
            throw new SettingConcurrencyException('Setting version changed before write.');
        }

        $record = new SettingRecord(
            $key,
            $scope,
            SettingCodec::coerce($type, $value),
            ($current?->version ?? 0) + 1,
            $updatedBy,
            microtime(true),
        );

        $this->records[$storageKey] = $record;
        return $record;
    }

    public function delete(string $key, SettingScope $scope, ?int $expectedVersion = null): bool
    {
        $storageKey = $this->storageKey($key, $scope);
        $current = $this->records[$storageKey] ?? null;
        if ($current === null) {
            return false;
        }

        if ($expectedVersion !== null && $current->version !== $expectedVersion) {
            throw new SettingConcurrencyException('Setting version changed before delete.');
        }

        unset($this->records[$storageKey]);
        return true;
    }

    public function forScope(SettingScope $scope): array
    {
        return array_values(array_filter(
            $this->records,
            static fn (SettingRecord $record): bool =>
                $record->scope->type === $scope->type
                && $record->scope->normalizedId() === $scope->normalizedId(),
        ));
    }

    private function storageKey(string $key, SettingScope $scope): string
    {
        return $scope->type->value . '|' . $scope->normalizedId() . '|' . $key;
    }
}
