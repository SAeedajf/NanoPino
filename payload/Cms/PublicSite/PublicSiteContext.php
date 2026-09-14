<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\PublicSite;

use App\com_pinoox_cms\Cms\Authorization\ScopeType;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;
use App\com_pinoox_cms\Cms\Settings\SettingScope;
use Pinoox\Portal\App\AppEngine;

final readonly class PublicSiteContext
{
    public const DEFAULT_SITE_ID = 1;
    public const DEFAULT_LOCALE = 'fa';
    public const HOSTS_SETTING = 'site.public_hosts';

    public function __construct(
        public int $siteId = self::DEFAULT_SITE_ID,
        public string $locale = self::DEFAULT_LOCALE,
    ) {
        if ($siteId < 1) {
            throw new \InvalidArgumentException('Public site_id must be positive.');
        }
        if (preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale) !== 1) {
            throw new \InvalidArgumentException('Public locale is invalid.');
        }
    }

    public static function fromRuntime(?string $host = null): self
    {
        $config = AppEngine::config('com_pinoox_cms')->get('public', []);
        $config = is_array($config) ? $config : [];
        $base = $config;
        $base['hosts'] = [];
        $baseContext = self::fromConfig($base);

        $record = CmsRuntimeServices::settingsRepository()->find(
            self::HOSTS_SETTING,
            new SettingScope(ScopeType::Site, $baseContext->siteId),
        );
        if ($record !== null) {
            if (!is_array($record->value)) {
                throw new \InvalidArgumentException('Managed public host registry must be an object.');
            }
            $config['hosts'] = $record->value;
        }

        return self::fromConfig($config, $host);
    }

    /** @param array<string,mixed> $config */
    public static function fromConfig(array $config, ?string $host = null): self
    {
        $selected = $config;
        $hosts = $config['hosts'] ?? [];
        if ($hosts !== [] && !is_array($hosts)) {
            throw new \InvalidArgumentException('Public hosts configuration must be an object.');
        }

        if (is_array($hosts) && $hosts !== []) {
            if ($host === null || trim($host) === '') {
                throw new \InvalidArgumentException('A request host is required when public hosts are configured.');
            }
            $normalizedHost = self::normalizeHost($host);
            $normalizedHosts = [];
            foreach ($hosts as $configuredHost => $hostConfig) {
                if (!is_string($configuredHost) || !is_array($hostConfig)) {
                    throw new \InvalidArgumentException('Each public host must map to a configuration object.');
                }
                $key = self::normalizeHost($configuredHost);
                if (isset($normalizedHosts[$key])) {
                    throw new \InvalidArgumentException('Duplicate normalized public host configuration.');
                }
                $normalizedHosts[$key] = $hostConfig;
            }
            if (!isset($normalizedHosts[$normalizedHost])) {
                throw new \InvalidArgumentException('The request host is not configured for a public site.');
            }
            $selected = array_replace($selected, $normalizedHosts[$normalizedHost]);
        }

        return new self(
            siteId: (int)($selected['site_id'] ?? self::DEFAULT_SITE_ID),
            locale: trim((string)($selected['locale'] ?? self::DEFAULT_LOCALE)) ?: self::DEFAULT_LOCALE,
        );
    }

    public static function validateHostMap(mixed $hosts): bool|string
    {
        if (!is_array($hosts)) {
            return 'Public host registry must be an object.';
        }

        try {
            foreach (self::normalizedHostMap($hosts) as $hostConfig) {
                self::fromConfig(array_replace(['hosts' => []], $hostConfig));
            }
        } catch (\Throwable $error) {
            return $error->getMessage();
        }

        return true;
    }

    /** @param array<string,mixed> $hosts @return array<string,array<string,mixed>> */
    private static function normalizedHostMap(array $hosts): array
    {
        $normalizedHosts = [];
        foreach ($hosts as $configuredHost => $hostConfig) {
            if (!is_string($configuredHost) || !is_array($hostConfig)) {
                throw new \InvalidArgumentException('Each public host must map to a configuration object.');
            }
            $key = self::normalizeHost($configuredHost);
            if (isset($normalizedHosts[$key])) {
                throw new \InvalidArgumentException('Duplicate normalized public host configuration.');
            }
            $normalizedHosts[$key] = $hostConfig;
        }

        return $normalizedHosts;
    }

    private static function normalizeHost(string $host): string
    {
        $normalized = strtolower(rtrim(trim($host), '.'));
        if ($normalized === '' || preg_match('/^[a-z0-9](?:[a-z0-9.-]{0,252}[a-z0-9])?$/', $normalized) !== 1) {
            throw new \InvalidArgumentException('Public host is invalid.');
        }
        return $normalized;
    }
}
