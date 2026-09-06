<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Api\V1\SystemHealth;
final class SystemHealthApiContract {
 public const BASE='/api/v1/cms/system';
 public static function routes():array{return[
  ['method'=>'GET','path'=>self::BASE.'/health','capability'=>'system.health.view','rate_limit'=>'cms.api.read'],
  ['method'=>'GET','path'=>self::BASE.'/health/history','capability'=>'system.health.view','rate_limit'=>'cms.api.read'],
  ['method'=>'GET','path'=>self::BASE.'/logs','capability'=>'system.logs.view','rate_limit'=>'cms.api.read'],
  ['method'=>'POST','path'=>self::BASE.'/support-bundle','capability'=>'system.support.export','rate_limit'=>'cms.recovery'],
 ];}
}
