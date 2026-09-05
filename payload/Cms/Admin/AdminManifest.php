<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

final class AdminManifest
{
    /**
     * @param list<array<string,mixed>> $navigation
     * @param list<array<string,mixed>> $routes
     * @param list<array<string,mixed>> $widgets
     * @param list<array<string,mixed>> $panels
     * @param list<array<string,mixed>> $components
     */
    public function __construct(
        public readonly array $navigation,
        public readonly array $routes,
        public readonly array $widgets,
        public readonly array $panels,
        public readonly array $components = [],
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'schema' => 1,
            'navigation' => $this->navigation,
            'routes' => $this->routes,
            'widgets' => $this->widgets,
            'panels' => $this->panels,
            'components' => $this->components,
        ];
    }
}
