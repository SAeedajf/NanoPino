<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme;

final class ThemeInheritanceResolver
{
    public function __construct(private readonly NativeThemeGatewayInterface $native) {}

    public function resolve(string $package, string $themeName, ?string $context = null): NativeThemeStack
    {
        $active = $this->native->find($package, $themeName);
        if ($active === null) {
            throw new ThemeInheritanceException('Theme not found: ' . $package . ':' . $themeName);
        }

        $names = [];
        $paths = [];
        $visiting = [];
        $added = [];

        $this->collect($active, $names, $paths, $visiting, $added);

        return new NativeThemeStack(
            $package,
            $themeName,
            $context,
            'theme',
            $names,
            $paths,
        );
    }

    /**
     * @param list<string> $names
     * @param list<string> $paths
     * @param array<string,true> $visiting
     * @param array<string,true> $added
     */
    private function collect(
        NativeThemeDescriptor $theme,
        array &$names,
        array &$paths,
        array &$visiting,
        array &$added,
    ): void {
        $key = $theme->package . ':' . $theme->name;
        if (isset($visiting[$key])) {
            throw new ThemeInheritanceException('Circular theme inheritance detected at ' . $key);
        }

        if (!isset($added[$key])) {
            $names[] = $theme->name;
            $paths[] = $theme->path;
            $added[$key] = true;
        }

        $visiting[$key] = true;
        foreach ($theme->extends as $reference) {
            [$package, $name] = $this->parse($reference, $theme->package);
            $parent = $this->native->find($package, $name);
            if ($parent === null) {
                throw new ThemeInheritanceException('Missing parent theme: ' . $package . ':' . $name);
            }
            $this->collect($parent, $names, $paths, $visiting, $added);
        }
        unset($visiting[$key]);
    }

    /** @return array{string,string} */
    private function parse(string $reference, string $defaultPackage): array
    {
        $reference = ltrim(trim($reference), '@');
        if ($reference === '') {
            throw new ThemeInheritanceException('Empty theme inheritance reference.');
        }

        if (str_contains($reference, ':')) {
            [$package, $name] = explode(':', $reference, 2);
        } elseif (str_contains($reference, '/')) {
            [$package, $name] = explode('/', $reference, 2);
        } else {
            $package = $defaultPackage;
            $name = $reference;
        }

        $package = trim($package);
        $name = trim($name);

        if (
            preg_match('/^[a-z0-9][a-z0-9._-]{1,127}$/', $package) !== 1
            || preg_match('/^[a-z0-9][a-z0-9._-]{0,127}$/', $name) !== 1
        ) {
            throw new ThemeInheritanceException('Invalid theme inheritance reference.');
        }

        return [$package, $name];
    }
}
