<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\ExtensionCenter\Review;
final readonly class FileExtensionReviewTicketRepository implements ExtensionReviewTicketRepositoryInterface
{
 public function __construct(private string $directory){}
 public function save(ExtensionReviewTicket $ticket):void{$this->dir();$f=$this->path($ticket->tokenHash);$tmp=$f.'.tmp-'.bin2hex(random_bytes(4));$json=json_encode(['id'=>$ticket->id,'token_hash'=>$ticket->tokenHash,'extension_id'=>$ticket->extensionId,'package_sha256'=>$ticket->packageSha256,'decision'=>$ticket->decision->value,'expires_at'=>$ticket->expiresAt,'approved'=>$ticket->approved,'consumed'=>$ticket->consumed],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);if(file_put_contents($tmp,$json,LOCK_EX)===false||!@rename($tmp,$f)){@unlink($tmp);throw new \RuntimeException('Unable to persist extension review ticket.');}@chmod($f,0600);}
 public function findByTokenHash(string $hash):?ExtensionReviewTicket{if(preg_match('/^[a-f0-9]{64}$/',$hash)!==1)return null;$f=$this->path($hash);if(!is_file($f))return null;$d=json_decode((string)file_get_contents($f),true);if(!is_array($d))return null;try{return new ExtensionReviewTicket((string)$d['id'],(string)$d['token_hash'],(string)$d['extension_id'],(string)$d['package_sha256'],ExtensionReviewDecision::from((string)$d['decision']),(float)$d['expires_at'],(bool)($d['approved']??false),(bool)($d['consumed']??false));}catch(\Throwable){return null;}}
 public function purgeExpired():int{if(!is_dir($this->directory))return 0;$n=0;foreach(glob(rtrim($this->directory,'/\\').'/*.json')?:[] as$f){$d=json_decode((string)@file_get_contents($f),true);if(!is_array($d)||(float)($d['expires_at']??0)<=microtime(true)){if(@unlink($f))$n++;}}return$n;}
 private function dir():void{if(!is_dir($this->directory)&&!mkdir($this->directory,0700,true)&&!is_dir($this->directory))throw new \RuntimeException('Unable to create extension ticket directory.');}
 private function path(string $h):string{if(preg_match('/^[a-f0-9]{64}$/',$h)!==1)throw new \RuntimeException('Invalid ticket hash.');return rtrim($this->directory,'/\\').'/'.$h.'.json';}
}
