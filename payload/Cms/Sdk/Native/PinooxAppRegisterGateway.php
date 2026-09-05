<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Sdk\Native;

use Pinoox\Component\AppEvent\AppRegister;

final readonly class PinooxAppRegisterGateway implements NativeAppGatewayInterface
{
    public function __construct(private AppRegister $register) {}

    public function package(): string
    {
        return $this->register->package();
    }

    public function apiRoute(array $route, ?string $version = null): void
    {
        $this->register->apiRoute($route, $version);
    }

    public function action(string $name, array|string|\Closure $handler): void
    {
        $this->register->action($name, $handler);
    }

    public function listen(string $event, callable|array $listener, int $priority = 0): void
    {
        $this->register->listen($event, $listener, $priority);
    }

    public function schedule(callable $callback): void
    {
        $this->register->schedule($callback);
    }

    public function when(string $targetPackage, callable $callback): void
    {
        $this->register->when($targetPackage, $callback);
    }

    public function onRoute(string|array $name, callable $handler, ?string $package = null): void
    {
        $this->register->onRoute($name, $handler, $package);
    }

    public function onApi(string|array $name, callable $handler, ?string $package = null): void
    {
        $this->register->onApi($name, $handler, $package);
    }

    public function onPath(string|array $pattern, callable $handler, ?string $package = null): void
    {
        $this->register->onPath($pattern, $handler, $package);
    }

    public function onAction(string|array $name, callable $handler, ?string $package = null): void
    {
        $this->register->onAction($name, $handler, $package);
    }

    public function onController(string|array $target, callable $handler, ?string $package = null): void
    {
        $this->register->onController($target, $handler, $package);
    }

    public function onModel(string $model, string $event, callable $handler): void
    {
        $this->register->onModel($model, $event, $handler);
    }

    public function onTheme(string|array $name, callable $handler, ?string $package = null): void
    {
        $this->register->onTheme($name, $handler, $package);
    }
}
