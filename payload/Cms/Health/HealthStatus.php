<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Health;
enum HealthStatus:string {
 case Ok='ok'; case Warning='warning'; case Error='error'; case Unknown='unknown';
 public function rank():int { return match($this){self::Ok=>10,self::Unknown=>15,self::Warning=>20,self::Error=>30};}
}
