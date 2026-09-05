<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Sdk\Native;

final class InMemoryNativeAppGateway implements NativeAppGatewayInterface
{
    /** @var list<array<string,mixed>> */
    public array $calls = [];

    public function __construct(private readonly string $packageId) {}

    public function package(): string
    {
        return $this->packageId;
    }

    public function apiRoute(array $route, ?string $version = null): void
    {
        $this->calls[] = ['kind'=>'apiRoute','route'=>$route,'version'=>$version];
    }

    public function action(string $name, array|string|\Closure $handler): void
    {
        $this->calls[] = ['kind'=>'action','name'=>$name,'handler'=>$handler];
    }

    public function listen(string $event, callable|array $listener, int $priority = 0): void
    {
        $this->calls[] = ['kind'=>'listen','event'=>$event,'listener'=>$listener,'priority'=>$priority];
    }

    public function schedule(callable $callback): void
    {
        $this->calls[] = ['kind'=>'schedule','callback'=>$callback];
    }

    public function when(string $targetPackage, callable $callback): void
    {
        $this->calls[] = ['kind'=>'when','target'=>$targetPackage,'callback'=>$callback];
    }

    public function onRoute(string|array $name, callable $handler, ?string $package = null): void
    {
        $this->watch('route',$name,$handler,$package);
    }

    public function onApi(string|array $name, callable $handler, ?string $package = null): void
    {
        $this->watch('api',$name,$handler,$package);
    }

    public function onPath(string|array $pattern, callable $handler, ?string $package = null): void
    {
        $this->watch('path',$pattern,$handler,$package);
    }

    public function onAction(string|array $name, callable $handler, ?string $package = null): void
    {
        $this->watch('action',$name,$handler,$package);
    }

    public function onController(string|array $target, callable $handler, ?string $package = null): void
    {
        $this->watch('controller',$target,$handler,$package);
    }

    public function onModel(string $model, string $event, callable $handler): void
    {
        $this->calls[] = ['kind'=>'model','model'=>$model,'event'=>$event,'handler'=>$handler];
    }

    public function onTheme(string|array $name, callable $handler, ?string $package = null): void
    {
        $this->watch('theme',$name,$handler,$package);
    }

    private function watch(string $kind,mixed $match,callable $handler,?string $package):void
    {
        $this->calls[]=['kind'=>$kind,'match'=>$match,'handler'=>$handler,'package'=>$package];
    }
}
