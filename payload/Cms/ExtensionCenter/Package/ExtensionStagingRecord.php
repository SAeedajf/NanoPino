<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\ExtensionCenter\Package;
final readonly class ExtensionStagingRecord
{
 public function __construct(public string $id,public string $displayName,public string $path,public int $size,public string $sha256,public float $createdAt,public float $expiresAt){}
 public function active():bool{return $this->expiresAt>microtime(true);}
 public function reference():ExtensionPackageReference{return new ExtensionPackageReference($this->path,$this->displayName,$this->size);}
 public function publicData():array{return['id'=>$this->id,'display_name'=>$this->displayName,'size'=>$this->size,'sha256'=>$this->sha256,'expires_at'=>$this->expiresAt];}
}
