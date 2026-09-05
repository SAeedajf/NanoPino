<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme;

use Pinoox\Component\Template\Theme\ThemeManifest;
use Pinoox\Component\Template\Theme\ThemeStack;

final class PinooxNativeThemeGateway implements NativeThemeGatewayInterface
{
    public function discover(string $package): array
    {
        $result = [];
        foreach (ThemeManifest::discover($package) as $manifest) {
            $result[] = $this->descriptor($manifest);
        }
        return $result;
    }

    public function find(string $package, string $themeName): ?NativeThemeDescriptor
    {
        $manifest = ThemeManifest::load($package, $themeName);
        return $manifest ? $this->descriptor($manifest) : null;
    }

    public function stack(string $package, ?string $context = null): NativeThemeStack
    {
        $stack = ThemeStack::resolve($package, $context);

        return new NativeThemeStack(
            (string)$stack['package'],
            (string)$stack['name'],
            isset($stack['context']) && is_string($stack['context']) ? $stack['context'] : null,
            (string)$stack['path_theme'],
            array_values(array_map('strval', $stack['stack'] ?? [])),
            array_values(array_map('strval', $stack['paths'] ?? [])),
        );
    }

    private function descriptor(ThemeManifest $manifest): NativeThemeDescriptor
    {
        $raw = $manifest->rawMeta();

        return new NativeThemeDescriptor(
            $manifest->hostPackage(),
            $manifest->name(),
            $manifest->title(),
            $manifest->description(),
            $manifest->versionName(),
            $manifest->versionCode(),
            $manifest->extends(),
            $manifest->cover(),
            $manifest->path(),
            $raw,
        );
    }
}
