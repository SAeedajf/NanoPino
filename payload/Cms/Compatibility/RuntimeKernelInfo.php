<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Compatibility;
final readonly class RuntimeKernelInfo {
 public function __construct(public ?int $code,public ?string $version,public string $source){}
 /** @return array{code:?int,version:?string,source:string} */
 public function toArray():array{return['code'=>$this->code,'version'=>$this->version,'source'=>$this->source];}
}
