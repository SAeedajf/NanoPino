<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme;

final class InMemoryNativeThemeGateway implements NativeThemeGatewayInterface
{
    /** @var array<string,NativeThemeDescriptor> */
    private array $themes = [];

    /**
     * @param list<NativeThemeDescriptor> $themes
     */
    public function __construct(array $themes = [])
    {
        foreach ($themes as $theme) {
            $this->themes[$theme->package . ':' . $theme->name] = $theme;
        }
    }

    public function add(NativeThemeDescriptor $theme): void
    {
        $this->themes[$theme->package . ':' . $theme->name] = $theme;
    }

    public function discover(string $package): array
    {
        return array_values(array_filter(
            $this->themes,
            static fn (NativeThemeDescriptor $theme): bool => $theme->package === $package,
        ));
    }

    public function find(string $package, string $themeName): ?NativeThemeDescriptor
    {
        return $this->themes[$package . ':' . $themeName] ?? null;
    }

    public function stack(string $package, ?string $context = null): NativeThemeStack
    {
        $themes = $this->discover($package);
        if ($themes === []) {
            return new NativeThemeStack($package, 'default', $context, 'theme', [], []);
        }

        $active = $themes[0];
        $names = [$active->name];
        $paths = [$active->path];
        $visited = [$active->package . ':' . $active->name => true];

        foreach ($active->extends as $ref) {
            [$parentPackage, $parentName] = $this->parseRef($ref, $package);
            while (true) {
                $key = $parentPackage . ':' . $parentName;
                if (isset($visited[$key])) {
                    throw new \RuntimeException('Circular theme inheritance detected at ' . $key);
                }
                $visited[$key] = true;

                $parent = $this->find($parentPackage, $parentName);
                if ($parent === null) {
                    break;
                }
                $names[] = $parent->name;
                $paths[] = $parent->path;

                if ($parent->extends === []) {
                    break;
                }
                [$parentPackage, $parentName] = $this->parseRef($parent->extends[0], $parent->package);
            }
        }

        return new NativeThemeStack($package, $active->name, $context, 'theme', $names, $paths);
    }

    /** @return array{string,string} */
    private function parseRef(string $reference, string $defaultPackage): array
    {
        $reference = ltrim(trim($reference), '@');
        if (str_contains($reference, ':')) {
            [$package, $name] = explode(':', $reference, 2);
            return [trim($package), trim($name)];
        }
        if (str_contains($reference, '/')) {
            [$package, $name] = explode('/', $reference, 2);
            return [trim($package), trim($name)];
        }
        return [$defaultPackage, $reference];
    }
}
