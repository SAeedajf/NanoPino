<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Compatibility;
use Pinoox\Component\Package\Pinx\PinxVersion;
final class RuntimeKernelInfoResolver
{
    public function resolve():RuntimeKernelInfo
    {
        if(class_exists(PinxVersion::class)&&method_exists(PinxVersion::class,'kernel')){
            try{
                $kernel=PinxVersion::kernel();
                $code=is_numeric($kernel['code']??null)?(int)$kernel['code']:null;
                $version=trim((string)($kernel['name']??''));
                return new RuntimeKernelInfo($code,$version!==''?$version:null,'Pinoox PinxVersion::kernel');
            }catch(\Throwable){}
        }
        return new RuntimeKernelInfo(null,null,'unbound');
    }
}
