<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Settings;

final readonly class SettingRecord
{
    public function __construct(
        public string $key,
        public SettingScope $scope,
        public mixed $value,
        public int $version,
        public ?int $updatedBy,
        public float $updatedAt,
    ) {}
}
