<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Discovery;

/**
 * Projects an installed Pinoox app.php config into a PINX-compatible array so the
 * same CMS manifest validator/factory can be reused for installed-app discovery.
 * It intentionally does not load files or mutate Pinoox state.
 */
final class AppConfigManifestProjector
{
    /** @param array<string,mixed> $appConfig @return array<string,mixed>|null */
    public function project(array $appConfig): ?array
    {
        $cms = $appConfig['cms'] ?? null;
        if (!is_array($cms)) {
            return null;
        }

        $pinx = is_array($appConfig['pinx'] ?? null) ? $appConfig['pinx'] : [];
        $type = (string) ($pinx['type'] ?? 'app');

        return [
            'format' => 'pinx',
            'format_version' => 1,
            'type' => $type,
            'package' => $type === 'theme'
                ? (string) ($pinx['theme_name'] ?? $appConfig['theme'] ?? '')
                : (string) ($appConfig['package'] ?? ''),
            'developer' => (string) ($appConfig['developer'] ?? ''),
            'version_name' => (string) ($appConfig['version-name'] ?? '1.0.0'),
            'version_code' => (int) ($appConfig['version-code'] ?? 1),
            'minpin' => (int) ($pinx['minpin'] ?? $appConfig['minpin'] ?? 0),
            'depends' => is_array($appConfig['depends'] ?? null) ? $appConfig['depends'] : [],
            'target_app' => $type === 'theme' ? (string) ($pinx['target_app'] ?? $appConfig['package'] ?? '') : null,
            'theme_name' => $type === 'theme' ? (string) ($pinx['theme_name'] ?? $appConfig['theme'] ?? '') : null,
            'cms' => $cms,
        ];
    }
}
