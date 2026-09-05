<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Sdk\Package;

use App\com_pinoox_cms\Cms\Manifest\ExtensionManifestValidator;

final class SdkPackageValidator
{
    /** @param array<string,mixed> $pinxManifest */
    public function validate(string $directory,array $pinxManifest):SdkPackageValidationResult
    {
        $errors=[];$warnings=[];
        try{(new ExtensionManifestValidator())->validate($pinxManifest);}
        catch(\Throwable $e){$errors[]='Manifest: '.$e->getMessage();}

        $root=realpath($directory);
        if($root===false||!is_dir($root)){
            return new SdkPackageValidationResult(['Package directory does not exist.']);
        }

        $type=(string)($pinxManifest['type']??'');
        if($type==='app'){
            foreach(['app.php','boot.php'] as $required){
                if(!is_file($root.'/'.$required))$errors[]='Missing required file: '.$required;
            }
        } elseif($type==='theme'){
            $name=(string)($pinxManifest['theme_name']??'');
            foreach(["theme/{$name}/config.php","theme/{$name}/index.twig"] as $required){
                if(!is_file($root.'/'.$required))$errors[]='Missing required theme file: '.$required;
            }
        }

        foreach($this->files($root) as $relative){
            if(str_contains($relative,"\0")||in_array('..',explode('/',$relative),true)){
                $errors[]='Unsafe package path: '.$relative;
            }
            if(str_starts_with($relative,'vendor/pinoox/')||str_starts_with($relative,'pincore/')){
                $errors[]='Extension package must not vendor or modify Pincore/Pinoox core: '.$relative;
            }
        }

        if(!is_file($root.'/tests/extension.php')){
            $warnings[]='Recommended SDK extension test is missing: tests/extension.php';
        }
        if(!is_file($root.'/README.md')){
            $warnings[]='README.md is missing.';
        }

        return new SdkPackageValidationResult(array_values(array_unique($errors)),array_values(array_unique($warnings)));
    }

    /** @return list<string> */
    private function files(string $root):array
    {
        $out=[];
        $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS));
        foreach($it as $file){
            if(!$file->isFile()&&!$file->isLink())continue;
            $path=str_replace('\\','/',$file->getPathname());
            $base=str_replace('\\','/',$root);
            $out[]=ltrim(substr($path,strlen($base)),'/');
        }
        sort($out);
        return$out;
    }
}
