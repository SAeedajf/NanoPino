<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Http;

final class CoreApiSecurityMatrix
{
    /** @return array<string,ApiSecurityRequirement> */
    public static function all(): array
    {
        return [
            'search'=>new ApiSecurityRequirement('content.read','cms.search',false,true),
            'builder'=>new ApiSecurityRequirement('builder.read/edit/publish','cms.api.write',true,true),
            'extensions'=>new ApiSecurityRequirement('extensions.*','cms.upload.extension',true,false),
            'updates'=>new ApiSecurityRequirement('extensions.update/system.recovery','cms.api.write',true,false),
            'recovery'=>new ApiSecurityRequirement('system.recovery','cms.recovery',true,false),
            'infrastructure'=>new ApiSecurityRequirement('system.health/cache/queue','cms.api.write',true,false),
            'performance'=>new ApiSecurityRequirement('system.performance.view','cms.api.read',false,false),
            'security'=>new ApiSecurityRequirement('system.security.view','cms.api.read',false,false),
            'health'=>new ApiSecurityRequirement('system.health.view','cms.api.read',false,false),
            'logs'=>new ApiSecurityRequirement('system.logs.view','cms.api.read',false,false),
            'support-bundle'=>new ApiSecurityRequirement('system.support.export','cms.recovery',true,false),
            'media-upload'=>new ApiSecurityRequirement('media.upload','cms.upload.media',true,true),
        ];
    }
}
