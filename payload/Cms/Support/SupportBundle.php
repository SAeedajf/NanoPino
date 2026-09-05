<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Support;
final readonly class SupportBundle {
 /** @param array<string,mixed> $data */
 public function __construct(public string $id,public float $createdAt,public array $data){}
 public function json():string{return json_encode(['schema'=>1,'id'=>$this->id,'created_at'=>$this->createdAt,'data'=>$this->data],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
}
