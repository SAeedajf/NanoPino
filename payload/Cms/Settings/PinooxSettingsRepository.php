<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Settings;

use App\com_pinoox_cms\Cms\Authorization\ScopeType;
use App\com_pinoox_cms\Cms\Database\CmsDatabase;
use RuntimeException;

final class PinooxSettingsRepository implements SettingsRepositoryInterface
{
    public const TABLE = 'settings';

    public function find(string $key, SettingScope $scope): ?SettingRecord
    {
        $row = CmsDatabase::table(self::TABLE)
            ->where('setting_key', $key)
            ->where('scope_type', $scope->type->value)
            ->where('scope_id', $scope->normalizedId())
            ->first();

        return $row ? $this->record($row) : null;
    }

    public function put(
        string $key,
        SettingScope $scope,
        SettingType $type,
        mixed $value,
        ?int $updatedBy,
        ?int $expectedVersion = null,
    ): SettingRecord {
        $encoded = SettingCodec::encode($type, $value);
        $now = date('Y-m-d H:i:s');

        return CmsDatabase::transaction(function () use (
            $key,
            $scope,
            $type,
            $encoded,
            $updatedBy,
            $expectedVersion,
            $now,
        ): SettingRecord {
            $query = CmsDatabase::table(self::TABLE)
                ->where('setting_key', $key)
                ->where('scope_type', $scope->type->value)
                ->where('scope_id', $scope->normalizedId());

            $current = $query->lockForUpdate()->first();
            $currentVersion = $current ? (int)$current->version : 0;

            if ($expectedVersion !== null && $currentVersion !== $expectedVersion) {
                throw new SettingConcurrencyException('Setting version changed before write.');
            }

            $nextVersion = $currentVersion + 1;

            if ($current) {
                $query->update([
                    'value_type' => $type->value,
                    'value_json' => $encoded,
                    'version' => $nextVersion,
                    'updated_by' => $updatedBy,
                    'updated_at' => $now,
                ]);
            } else {
                CmsDatabase::table(self::TABLE)->insert([
                    'setting_key' => $key,
                    'scope_type' => $scope->type->value,
                    'scope_id' => $scope->normalizedId(),
                    'value_type' => $type->value,
                    'value_json' => $encoded,
                    'version' => $nextVersion,
                    'updated_by' => $updatedBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return new SettingRecord(
                $key,
                $scope,
                SettingCodec::decode($type, $encoded),
                $nextVersion,
                $updatedBy,
                strtotime($now) ?: time(),
            );
        });
    }

    public function delete(string $key, SettingScope $scope, ?int $expectedVersion = null): bool
    {
        return CmsDatabase::transaction(function () use ($key, $scope, $expectedVersion): bool {
            $query = CmsDatabase::table(self::TABLE)
                ->where('setting_key', $key)
                ->where('scope_type', $scope->type->value)
                ->where('scope_id', $scope->normalizedId());

            $current = $query->lockForUpdate()->first();
            if (!$current) {
                return false;
            }

            if ($expectedVersion !== null && (int)$current->version !== $expectedVersion) {
                throw new SettingConcurrencyException('Setting version changed before delete.');
            }

            return $query->delete() > 0;
        });
    }

    public function forScope(SettingScope $scope): array
    {
        return CmsDatabase::table(self::TABLE)
            ->where('scope_type', $scope->type->value)
            ->where('scope_id', $scope->normalizedId())
            ->orderBy('setting_key')
            ->get()
            ->map(fn ($row): SettingRecord => $this->record($row))
            ->all();
    }

    private function record(object $row): SettingRecord
    {
        $type = SettingType::from((string)$row->value_type);
        $scopeType = ScopeType::from((string)$row->scope_type);
        $scope = new SettingScope(
            $scopeType,
            $scopeType === ScopeType::Global ? null : (string)$row->scope_id,
        );

        return new SettingRecord(
            (string)$row->setting_key,
            $scope,
            SettingCodec::decode($type, (string)$row->value_json),
            (int)$row->version,
            $row->updated_by !== null ? (int)$row->updated_by : null,
            strtotime((string)$row->updated_at) ?: 0,
        );
    }
}
