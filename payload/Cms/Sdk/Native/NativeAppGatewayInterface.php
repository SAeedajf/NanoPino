<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Sdk\Native;

interface NativeAppGatewayInterface
{
    public function package(): string;

    /** @param array<string,mixed> $route */
    public function apiRoute(array $route, ?string $version = null): void;

    public function action(string $name, array|string|\Closure $handler): void;

    public function listen(string $event, callable|array $listener, int $priority = 0): void;

    public function schedule(callable $callback): void;

    public function when(string $targetPackage, callable $callback): void;

    public function onRoute(string|array $name, callable $handler, ?string $package = null): void;

    public function onApi(string|array $name, callable $handler, ?string $package = null): void;

    public function onPath(string|array $pattern, callable $handler, ?string $package = null): void;

    public function onAction(string|array $name, callable $handler, ?string $package = null): void;

    public function onController(string|array $target, callable $handler, ?string $package = null): void;

    public function onModel(string $model, string $event, callable $handler): void;

    public function onTheme(string|array $name, callable $handler, ?string $package = null): void;
}
