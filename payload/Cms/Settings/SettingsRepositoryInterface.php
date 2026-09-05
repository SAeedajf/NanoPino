<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Settings;

interface SettingsRepositoryInterface
{
    public function find(string $key, SettingScope $scope): ?SettingRecord;

    /**
     * @throws SettingConcurrencyException
     */
    public function put(
        string $key,
        SettingScope $scope,
        SettingType $type,
        mixed $value,
        ?int $updatedBy,
        ?int $expectedVersion = null,
    ): SettingRecord;

    public function delete(string $key, SettingScope $scope, ?int $expectedVersion = null): bool;

    /** @return list<SettingRecord> */
    public function forScope(SettingScope $scope): array;
}
