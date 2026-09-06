<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Search;

use App\com_pinoox_cms\Cms\Settings\PinooxSettingsRepository;
use App\com_pinoox_cms\Cms\Settings\SettingScope;

final readonly class RemoteSearchConfiguration
{
    public function __construct(
        public string $driver,
        public string $endpoint,
        public string $index,
        public string $apiKey,
    ) {}

    public static function fromRepository(PinooxSettingsRepository $repository): ?self
    {
        $driver = self::value($repository, 'search.remote.driver');
        if (!in_array($driver, ['meilisearch', 'typesense'], true)) {
            return null;
        }

        $endpoint = rtrim(self::value($repository, 'search.remote.endpoint'), '/');
        $index = self::value($repository, 'search.remote.index');
        $apiKey = self::value($repository, 'search.remote.api_key');

        if (
            $endpoint === ''
            || $index === ''
            || preg_match('/^[A-Za-z0-9._-]{1,128}$/', $index) !== 1
        ) {
            return null;
        }

        return new self($driver, $endpoint, $index, $apiKey);
    }

    private static function value(PinooxSettingsRepository $repository, string $key): string
    {
        try {
            $record = $repository->find($key, SettingScope::global());
            $value = $record?->value;
            return is_scalar($value) ? trim((string)$value) : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
