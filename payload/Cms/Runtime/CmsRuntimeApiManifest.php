<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Runtime;

use App\com_pinoox_cms\Controller\Api\BuilderRuntimeApiController;
use App\com_pinoox_cms\Controller\Api\ContentRuntimeApiController;
use App\com_pinoox_cms\Controller\Api\ExtensionRuntimeApiController;
use App\com_pinoox_cms\Controller\Api\GlobalBlockRuntimeApiController;
use App\com_pinoox_cms\Controller\Api\MediaApiController;
use App\com_pinoox_cms\Controller\Api\SearchRuntimeApiController;
use App\com_pinoox_cms\Controller\Api\InfrastructureRuntimeApiController;
use App\com_pinoox_cms\Controller\Api\PerformanceRuntimeApiController;
use App\com_pinoox_cms\Controller\Api\UpdateRuntimeApiController;
use App\com_pinoox_cms\Controller\Api\RecoveryRuntimeApiController;
use App\com_pinoox_cms\Controller\Api\SecurityRuntimeApiController;
use App\com_pinoox_cms\Controller\Api\SettingsApiController;
use App\com_pinoox_cms\Controller\Api\SystemRuntimeApiController;
use App\com_pinoox_cms\Controller\Api\TaxonomyRuntimeApiController;
use App\com_pinoox_cms\Controller\Api\ThemeRuntimeApiController;
use App\com_pinoox_cms\Controller\Api\UserRuntimeApiController;

final class CmsRuntimeApiManifest
{
    /** @return array<string,mixed> */
    public static function definition(): array
    {
        return [
            'version' => 'v1',
            'prefix' => 'cms',
            'routes' => [
                // Content
                self::route('GET','/content',[ContentRuntimeApiController::class,'index'],'cms.content.index','content.read','cms.api.read'),
                self::route('POST','/content',[ContentRuntimeApiController::class,'create'],'cms.content.create','content.create','cms.api.write',true),
                self::route('GET','/content/{id}',[ContentRuntimeApiController::class,'show'],'cms.content.show','content.read','cms.api.read',false,['id'=>'\d+']),
                self::route('PUT','/content/{id}',[ContentRuntimeApiController::class,'update'],'cms.content.update','content.update','cms.api.write',true,['id'=>'\d+']),
                self::route('POST','/content/{id}/publish',[ContentRuntimeApiController::class,'publish'],'cms.content.publish','content.publish','cms.api.write',true,['id'=>'\d+']),
                self::route('POST','/content/{id}/schedule',[ContentRuntimeApiController::class,'schedule'],'cms.content.schedule','content.publish','cms.api.write',true,['id'=>'\d+']),
                self::route('DELETE','/content/{id}',[ContentRuntimeApiController::class,'trash'],'cms.content.trash','content.delete','cms.api.write',true,['id'=>'\d+']),
                self::route('POST','/content/{id}/restore',[ContentRuntimeApiController::class,'restoreDraft'],'cms.content.restore','content.update','cms.api.write',true,['id'=>'\d+']),
                self::route('GET','/content/{id}/revisions',[ContentRuntimeApiController::class,'revisions'],'cms.content.revisions','content.read','cms.api.read',false,['id'=>'\d+']),
                self::route('GET','/content/{id}/revisions/{revisionId}',[ContentRuntimeApiController::class,'revision'],'cms.content.revision.show','content.read','cms.api.read',false,['id'=>'\d+','revisionId'=>'\d+']),
                self::route('POST','/content/{id}/revisions/{revisionId}/restore',[ContentRuntimeApiController::class,'restoreRevision'],'cms.content.revision.restore','content.update','cms.api.write',true,['id'=>'\d+','revisionId'=>'\d+']),

                // Taxonomy
                self::route('GET','/taxonomies/{key}/terms',[TaxonomyRuntimeApiController::class,'terms'],'cms.taxonomy.terms','taxonomy.read','cms.api.read',false,['key'=>'[a-z][a-z0-9_-]{1,63}']),

                // Media
                self::route('GET','/media',[MediaApiController::class,'index'],'cms.media.index','media.read','cms.api.read'),
                self::route('POST','/media',[MediaApiController::class,'upload'],'cms.media.upload','media.upload','cms.upload.media',true),
                self::route('GET','/media/{id}',[MediaApiController::class,'show'],'cms.media.show','media.read','cms.api.read',false,['id'=>'\d+']),
                self::route('PATCH','/media/{id}',[MediaApiController::class,'update'],'cms.media.update','media.update','cms.api.write',true,['id'=>'\d+']),
                self::route('DELETE','/media/{id}',[MediaApiController::class,'delete'],'cms.media.delete','media.delete','cms.api.write',true,['id'=>'\d+']),


                // Users / native Pinoox Identity
                self::route('GET','/users',[UserRuntimeApiController::class,'index'],'cms.users.index','users.read','cms.api.read'),
                self::route('POST','/users',[UserRuntimeApiController::class,'create'],'cms.users.create','users.create','cms.api.write',true),
                self::route('PATCH','/users/{id}',[UserRuntimeApiController::class,'update'],'cms.users.update','users.update','cms.api.write',true,['id'=>'\d+']),
                self::route('PATCH','/users/{id}/status',[UserRuntimeApiController::class,'status'],'cms.users.status','users.update','cms.api.write',true,['id'=>'\d+']),
                self::route('POST','/users/{id}/roles/{role}',[UserRuntimeApiController::class,'assignRole'],'cms.users.roles.assign','users.roles.manage','cms.api.write',true,['id'=>'\d+','role'=>'[a-z][a-z0-9._-]{0,126}']),
                self::route('DELETE','/users/{id}/roles/{role}',[UserRuntimeApiController::class,'detachRole'],'cms.users.roles.detach','users.roles.manage','cms.api.write',true,['id'=>'\d+','role'=>'[a-z][a-z0-9._-]{0,126}']),
                self::route('POST','/users/{id}/sessions/revoke',[UserRuntimeApiController::class,'revokeSessions'],'cms.users.sessions.revoke','users.sessions.revoke','cms.api.write',true,['id'=>'\d+']),
                self::route('DELETE','/users/{id}',[UserRuntimeApiController::class,'delete'],'cms.users.delete','users.delete','cms.api.write',true,['id'=>'\d+']),

                // Settings
                self::route('GET','/settings',[SettingsApiController::class,'index'],'cms.settings.index','settings.read','cms.api.read'),
                self::route('GET','/settings/{key}',[SettingsApiController::class,'show'],'cms.settings.show','settings.read','cms.api.read',false,['key'=>'[a-z0-9][a-z0-9._-]{1,190}']),
                self::route('PUT','/settings/{key}',[SettingsApiController::class,'update'],'cms.settings.update','settings.manage','cms.api.write',true,['key'=>'[a-z0-9][a-z0-9._-]{1,190}']),
                self::route('DELETE','/settings/{key}',[SettingsApiController::class,'reset'],'cms.settings.reset','settings.manage','cms.api.write',true,['key'=>'[a-z0-9][a-z0-9._-]{1,190}']),

                // Themes
                self::route('GET','/themes',[ThemeRuntimeApiController::class,'index'],'cms.themes.index','themes.read','cms.api.read'),
                self::route('GET','/themes/{package}/{theme}/patterns',[ThemeRuntimeApiController::class,'patterns'],'cms.themes.patterns','themes.read','cms.api.read',false,['package'=>'[a-z0-9][a-z0-9._-]{1,127}','theme'=>'[a-z0-9][a-z0-9._-]{0,127}']),
                self::route('POST','/themes/activate',[ThemeRuntimeApiController::class,'activate'],'cms.themes.activate','themes.activate','cms.api.write',true),

                // Builder / FSE
                self::route('GET','/builder/global-blocks',[GlobalBlockRuntimeApiController::class,'index'],'cms.builder.global-blocks.index','builder.read','cms.api.read'),
                self::route('POST','/builder/global-blocks',[GlobalBlockRuntimeApiController::class,'create'],'cms.builder.global-blocks.create','builder.edit','cms.api.write',true),
                self::route('GET','/builder/global-blocks/{id}',[GlobalBlockRuntimeApiController::class,'show'],'cms.builder.global-blocks.show','builder.read','cms.api.read',false,['id'=>'\d+']),
                self::route('PUT','/builder/global-blocks/{id}',[GlobalBlockRuntimeApiController::class,'update'],'cms.builder.global-blocks.update','builder.edit','cms.api.write',true,['id'=>'\d+']),
                self::route('GET','/builder',[BuilderRuntimeApiController::class,'index'],'cms.builder.index','builder.read','cms.api.read'),
                self::route('POST','/builder/open',[BuilderRuntimeApiController::class,'open'],'cms.builder.open','builder.edit','cms.api.write',true),
                self::route('POST','/builder',[BuilderRuntimeApiController::class,'create'],'cms.builder.create','builder.edit','cms.api.write',true),
                self::route('GET','/builder/{id}',[BuilderRuntimeApiController::class,'read'],'cms.builder.read','builder.read','cms.api.read',false,['id'=>'\d+']),
                self::route('PUT','/builder/{id}',[BuilderRuntimeApiController::class,'save'],'cms.builder.save','builder.edit','cms.api.write',true,['id'=>'\d+']),
                self::route('POST','/builder/{id}/autosave',[BuilderRuntimeApiController::class,'autosave'],'cms.builder.autosave','builder.edit','cms.api.write',true,['id'=>'\d+']),
                self::route('POST','/builder/{id}/publish',[BuilderRuntimeApiController::class,'publish'],'cms.builder.publish','builder.publish','cms.api.write',true,['id'=>'\d+']),
                self::route('GET','/builder/{id}/revisions',[BuilderRuntimeApiController::class,'revisions'],'cms.builder.revisions','builder.read','cms.api.read',false,['id'=>'\d+']),
                self::route('POST','/builder/{id}/revisions/{revisionId}/restore',[BuilderRuntimeApiController::class,'restore'],'cms.builder.restore','builder.edit','cms.api.write',true,['id'=>'\d+','revisionId'=>'\d+']),
                self::route('POST','/builder/preview',[BuilderRuntimeApiController::class,'preview'],'cms.builder.preview','builder.preview','cms.api.write',true),

                // Extensions / PINX
                self::route('GET','/extensions',[ExtensionRuntimeApiController::class,'index'],'cms.extensions.index','extensions.read','cms.api.read'),
                self::route('POST','/extensions/inspect',[ExtensionRuntimeApiController::class,'inspect'],'cms.extensions.inspect','extensions.install','cms.upload.extension',true),
                self::route('POST','/extensions/review-ticket',[ExtensionRuntimeApiController::class,'reviewTicket'],'cms.extensions.review-ticket','extensions.install','cms.upload.extension',true),
                self::route('POST','/extensions/install',[ExtensionRuntimeApiController::class,'install'],'cms.extensions.install','extensions.install','cms.upload.extension',true),
                self::route('POST','/extensions/{id}/activate',[ExtensionRuntimeApiController::class,'activate'],'cms.extensions.activate','extensions.activate','cms.api.write',true,['id'=>'com_[a-z0-9][a-z0-9_]{1,126}']),
                self::route('POST','/extensions/{id}/deactivate',[ExtensionRuntimeApiController::class,'deactivate'],'cms.extensions.deactivate','extensions.deactivate','cms.api.write',true,['id'=>'com_[a-z0-9][a-z0-9_]{1,126}']),
                self::route('POST','/extensions/{id}/update',[ExtensionRuntimeApiController::class,'update'],'cms.extensions.update','extensions.update','cms.upload.extension',true,['id'=>'com_[a-z0-9][a-z0-9_]{1,126}']),
                self::route('POST','/extensions/{id}/rollback',[ExtensionRuntimeApiController::class,'rollback'],'cms.extensions.rollback','extensions.repair','cms.recovery',true,['id'=>'com_[a-z0-9][a-z0-9_]{1,126}']),
                self::route('POST','/extensions/{id}/repair',[ExtensionRuntimeApiController::class,'repair'],'cms.extensions.repair','extensions.repair','cms.api.write',true,['id'=>'com_[a-z0-9][a-z0-9_]{1,126}']),
                self::route('DELETE','/extensions/{id}',[ExtensionRuntimeApiController::class,'uninstall'],'cms.extensions.uninstall','extensions.uninstall','cms.upload.extension',true,['id'=>'com_[a-z0-9][a-z0-9_]{1,126}']),


                // Search
                self::route('GET','/search',[SearchRuntimeApiController::class,'index'],'cms.search.index','content.read','cms.search'),

                // Infrastructure
                self::route('GET','/system/infrastructure',[InfrastructureRuntimeApiController::class,'status'],'cms.infrastructure.status','system.health.view','cms.api.read'),
                self::route('GET','/system/infrastructure/queue',[InfrastructureRuntimeApiController::class,'queue'],'cms.infrastructure.queue','system.health.view','cms.api.read'),
                self::route('POST','/system/infrastructure/queue/{id}/retry',[InfrastructureRuntimeApiController::class,'retryQueue'],'cms.infrastructure.queue.retry','system.queue.manage','cms.api.write',true,['id'=>'[A-Za-z0-9][A-Za-z0-9._-]{0,190}']),
                self::route('POST','/system/infrastructure/cache/invalidate-tag',[InfrastructureRuntimeApiController::class,'invalidateTag'],'cms.infrastructure.cache.invalidate-tag','system.cache.manage','cms.api.write',true),
                self::route('POST','/system/infrastructure/cache/invalidate-layer',[InfrastructureRuntimeApiController::class,'invalidateLayer'],'cms.infrastructure.cache.invalidate-layer','system.cache.manage','cms.api.write',true),

                // Performance
                self::route('GET','/system/performance',[PerformanceRuntimeApiController::class,'index'],'cms.performance.index','system.performance.view','cms.api.read'),

                // Update policy/history/recovery catalog
                self::route('GET','/updates/{id}/policy',[UpdateRuntimeApiController::class,'policy'],'cms.updates.policy','extensions.read','cms.api.read',false,['id'=>'[A-Za-z0-9][A-Za-z0-9._:-]{0,189}']),
                self::route('PUT','/updates/{id}/policy',[UpdateRuntimeApiController::class,'savePolicy'],'cms.updates.policy.save','extensions.update','cms.api.write',true,['id'=>'[A-Za-z0-9][A-Za-z0-9._:-]{0,189}']),
                self::route('GET','/updates/{id}/history',[UpdateRuntimeApiController::class,'history'],'cms.updates.history','extensions.read','cms.api.read',false,['id'=>'[A-Za-z0-9][A-Za-z0-9._:-]{0,189}']),
                self::route('GET','/updates/{id}/recovery-points',[UpdateRuntimeApiController::class,'recoveryPoints'],'cms.updates.recovery-points','system.recovery','cms.api.read',false,['id'=>'[A-Za-z0-9][A-Za-z0-9._:-]{0,189}']),

                // Recovery / System
                self::route('POST','/recovery/points/{id}/restore',[RecoveryRuntimeApiController::class,'restore'],'cms.recovery.restore','system.recovery','cms.recovery',true,['id'=>'rp-[A-Za-z0-9._-]+']),
                self::route('POST','/recovery/safe-mode/disable',[RecoveryRuntimeApiController::class,'disableSafeMode'],'cms.recovery.safe-mode.disable','system.recovery','cms.recovery',true),
                self::route('GET','/system/health',[SystemRuntimeApiController::class,'health'],'cms.system.health','system.health.view','cms.api.read'),
                self::route('GET','/system/health/history',[SystemRuntimeApiController::class,'healthHistory'],'cms.system.health.history','system.health.view','cms.api.read'),
                self::route('GET','/system/logs',[SystemRuntimeApiController::class,'logs'],'cms.system.logs','system.logs.view','cms.api.read'),
                self::route('POST','/system/support-bundle',[SystemRuntimeApiController::class,'supportBundle'],'cms.system.support-bundle','system.support.export','cms.recovery',true),

                // Security
                self::route('GET','/system/security',[SecurityRuntimeApiController::class,'index'],'cms.security.index','system.security.view','cms.api.read'),
            ],
        ];
    }

    /** @param array<string,string> $filters @return array<string,mixed> */
    private static function route(
        string $method,string $path,array $action,string $name,string $permission,string $rate,
        bool $mutation=false,array $filters=[]
    ):array {
        $flow=['throttle:'.$rate];
        if($mutation)$flow[]='cms_csrf';
        return [
            'method'=>$method,'path'=>$path,'action'=>$action,'name'=>$name,
            'permission'=>$permission,'rate_limit'=>$rate,'flow'=>$flow,'filters'=>$filters,
        ];
    }
}
