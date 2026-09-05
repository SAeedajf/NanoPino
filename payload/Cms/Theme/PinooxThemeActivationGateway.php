<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme;

use Pinoox\Component\Template\Theme\ThemeContextRegistry;
use Pinoox\Component\Template\Theme\ThemeStack;
use Pinoox\Portal\App\App;
use Pinoox\Portal\App\AppEngine;
use RuntimeException;

final class PinooxThemeActivationGateway implements ThemeActivationGatewayInterface
{
    public function __construct(private readonly NativeThemeGatewayInterface $native) {}

    public function active(string $package, ?string $context = null): ?string
    {
        return $this->native->stack($package, $context)->activeName;
    }

    public function activate(string $package, string $themeName, ?string $context = null): void
    {
        if (
            preg_match('/^[a-z0-9][a-z0-9._-]{1,127}$/', $package) !== 1
            || preg_match('/^[a-z0-9][a-z0-9._-]{0,127}$/', $themeName) !== 1
        ) {
            throw new RuntimeException('Invalid theme activation reference.');
        }
        if (!AppEngine::exists($package)) throw new RuntimeException('Theme host app is not installed.');
        if ($this->native->find($package, $themeName) === null) throw new RuntimeException('Theme is not installed in target app.');

        App::meeting($package, function () use ($context, $themeName): void {
            if ($context === null || trim($context) === '') {
                $config = App::set('theme', $themeName);
                if ($config === null) throw new RuntimeException('Pinoox theme configuration is unavailable.');
                $config->save();
                return;
            }

            $context = trim($context);
            $appConfig = App::config()->get();
            if (!is_array($appConfig) || !ThemeContextRegistry::hasContexts($appConfig)) {
                throw new RuntimeException('Theme context is not declared by target app.');
            }
            if (!in_array($context, ThemeContextRegistry::names($appConfig), true)) {
                throw new RuntimeException('Unknown theme context: ' . $context);
            }

            $contexts = $appConfig['theme-contexts'];
            $current = is_array($contexts[$context] ?? null) ? $contexts[$context] : [];
            $current['theme'] = $themeName;
            $contexts[$context] = $current;

            $config = App::set('theme-contexts', $contexts);
            if ($config === null) throw new RuntimeException('Pinoox theme-context configuration is unavailable.');
            $config->save();
        });

        AppEngine::__rebuild();
        $resolved = ThemeStack::resolve($package, $context);
        if (($resolved['name'] ?? null) !== $themeName) {
            throw new RuntimeException('Theme activation persisted but runtime verification failed.');
        }
    }
}
