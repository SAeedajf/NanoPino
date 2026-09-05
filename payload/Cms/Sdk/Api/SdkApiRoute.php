<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Sdk\Api;

final readonly class SdkApiRoute
{
    /** @param list<string> $flow */
    public function __construct(
        public string $method,
        public string $uri,
        public mixed $action,
        public string $name,
        public array $flow = [],
        public ?string $permission = null,
        public string $version = 'v1',
    ) {
        $method = strtoupper($method);
        if (!in_array($method,['GET','POST','PUT','PATCH','DELETE','OPTIONS'],true)) {
            throw new \InvalidArgumentException('Unsupported SDK API method.');
        }
        if (preg_match('#^/[a-z0-9/_{}.-]*$#',$uri)!==1 || str_contains($uri,'..')) {
            throw new \InvalidArgumentException('Invalid SDK API URI.');
        }
        if (preg_match('/^[a-z0-9][a-z0-9._-]{1,120}$/',$name)!==1) {
            throw new \InvalidArgumentException('Invalid SDK API route name.');
        }
        if (preg_match('/^v[1-9][0-9]*$/',$version)!==1) {
            throw new \InvalidArgumentException('Invalid SDK API version.');
        }
    }

    /** @return array<string,mixed> */
    public function toNativeRoute(): array
    {
        $route=[
            'method'=>strtoupper($this->method),
            'uri'=>$this->uri,
            'action'=>$this->action,
            'name'=>$this->name,
        ];
        if ($this->flow!==[]) $route['flow']=$this->flow;
        if ($this->permission!==null) $route['permission']=$this->permission;
        return $route;
    }

    public static function extensionUri(string $package,string $suffix='/'): string
    {
        if (preg_match('/^[a-z][a-z0-9_.-]{2,100}$/',$package)!==1) {
            throw new \InvalidArgumentException('Invalid Extension package for API URI.');
        }
        $suffix='/' . ltrim($suffix,'/');
        return '/extensions/' . $package . ($suffix==='/' ? '' : $suffix);
    }
}
