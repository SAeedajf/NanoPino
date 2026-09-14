<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\PublicSite;

use App\com_pinoox_cms\Cms\Admin\AdminRuntimeUrl;

final class PublicSiteUrl
{
    public static function path(?string $mountPath = null): string
    {
        return AdminRuntimeUrl::appPath('/site', $mountPath);
    }
}
