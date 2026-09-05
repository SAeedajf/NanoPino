<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

final class AdminAssetUrl
{
    public static function module(string $package,string $module):string
    {
        return AdminRuntimeUrl::extensionModule($package, $module);
    }
}
