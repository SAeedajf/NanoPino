<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Runtime;
final class RuntimeBindingState
{
    private static bool $csrf=false,$rates=false,$headers=false,$api=false;
    public static function markCsrf():void{self::$csrf=true;} public static function markRateLimits():void{self::$rates=true;}
    public static function markHeaders():void{self::$headers=true;} public static function markApi():void{self::$api=true;}
    public static function csrf():bool{return self::$csrf;} public static function rateLimits():bool{return self::$rates;}
    public static function headers():bool{return self::$headers;} public static function api():bool{return self::$api;}
}
