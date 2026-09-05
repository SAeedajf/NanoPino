<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin\Frontend;

final class AdminFrontendSourceFingerprint
{
    /** @return array{fingerprint:string,files:int,paths:list<string>} */
    public function calculate(string $themePath):array
    {
        $root=realpath($themePath);
        if($root===false||!is_dir($root)){
            throw new \RuntimeException('Admin theme root is unavailable.');
        }

        $root=str_replace('\\','/',$root);
        $paths=[];

        foreach(['src','public'] as$directory){
            $base=$root.'/'.$directory;
            if(!is_dir($base))continue;

            $it=new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base,\FilesystemIterator::SKIP_DOTS)
            );
            foreach($it as$file){
                if(!$file->isFile())continue;
                $path=str_replace('\\','/',$file->getPathname());
                if(is_link($path)){
                    throw new \RuntimeException('Admin source fingerprint rejects symlinks.');
                }
                if(!str_starts_with($path,$root.'/')){
                    throw new \RuntimeException('Admin source file escaped theme root.');
                }
                $paths[]=substr($path,strlen($root)+1);
            }
        }

        foreach(['package.json','vite.config.js','verify-dist.mjs','source-fingerprint.mjs','runtime-fingerprint.mjs','run-tests.mjs','build-linux.sh','build-windows.ps1'] as$relative){
            $path=$root.'/'.$relative;
            if(is_file($path)&&!is_link($path))$paths[]=$relative;
        }

        $paths=array_values(array_unique($paths));
        sort($paths,SORT_STRING);

        $context=hash_init('sha256');
        foreach($paths as$relative){
            if(!$this->safeRelative($relative)){
                throw new \RuntimeException('Unsafe Admin source fingerprint path.');
            }
            $path=$root.'/'.$relative;
            $fileHash=hash_file('sha256',$path);
            if(!is_string($fileHash)){
                throw new \RuntimeException('Unable to hash Admin source file.');
            }
            hash_update($context,$relative."\0".$fileHash."\n");
        }

        return [
            'fingerprint'=>hash_final($context),
            'files'=>count($paths),
            'paths'=>$paths,
        ];
    }

    private function safeRelative(string $path):bool
    {
        if($path===''||str_contains($path,"\0"))return false;
        $normalized=str_replace('\\','/',$path);
        if(str_starts_with($normalized,'/')||preg_match('/^[A-Za-z]:/',$normalized))return false;
        return !in_array('..',explode('/',$normalized),true);
    }
}
