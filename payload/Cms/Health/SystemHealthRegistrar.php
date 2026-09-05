<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Health;

use App\com_pinoox_cms\Cms\Compatibility\KernelCompatibilityChecker;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeSchemaReconciler;

final readonly class SystemHealthRegistrar
{
    public function __construct(
        private string $storagePath,
        private ?int $kernelCode=null,
        private ?string $kernelVersion=null,
        private ?int $extensionCount=null,
    ) {}

    public function register(HealthCheckRegistry $registry,string $owner='cms.core'):void
    {
        $path=$this->storagePath;
        $code=$this->kernelCode;
        $version=$this->kernelVersion;
        $extensionCount=$this->extensionCount;

        $registry->register(new HealthCheckDefinition('system.php',$owner,static function():array{
            return [
                'status'=>version_compare(PHP_VERSION,'8.2.0','>=')?'ok':'error',
                'message'=>'PHP runtime checked.',
                'details'=>['version'=>PHP_VERSION,'sapi'=>PHP_SAPI],
            ];
        }));

        $registry->register(new HealthCheckDefinition('system.memory',$owner,static function():array{
            $limit=ini_get('memory_limit');
            $peak=memory_get_peak_usage(true);
            return [
                'status'=>'ok',
                'message'=>'PHP memory inspected.',
                'details'=>['limit'=>$limit,'peak_bytes'=>$peak],
            ];
        }));

        $registry->register(new HealthCheckDefinition('system.disk',$owner,static function()use($path):array{
            $dir=is_dir($path)?$path:dirname($path);
            $w=is_writable($dir);
            $free=@disk_free_space($dir);
            $status=!$w?'error':(($free!==false&&$free<268_435_456)?'warning':'ok');
            return [
                'status'=>$status,
                'message'=>'CMS storage filesystem inspected.',
                'details'=>['writable'=>$w,'free_bytes'=>$free===false?null:(int)$free],
            ];
        }));

        $registry->register(new HealthCheckDefinition('system.kernel_compatibility',$owner,static function()use($code,$version):array{
            $r=(new KernelCompatibilityChecker())->check($code,$version);
            return [
                'status'=>$r->compatible()?'ok':'error',
                'message'=>$r->compatible()
                    ? 'Kernel compatibility contract satisfied.'
                    : 'Kernel compatibility contract failed.',
                'details'=>[
                    'minimum'=>$r->toArray()['minimum'],
                    'current'=>$r->toArray()['current'],
                    'optional_features_unavailable'=>$r->unavailableOptionalFeatures(),
                ],
            ];
        }));


        $registry->register(new HealthCheckDefinition('system.cms_schema',$owner,static function():array{
            try{
                $missing=CmsRuntimeSchemaReconciler::missingTables();
                return [
                    'status'=>$missing===[]?'ok':'error',
                    'message'=>$missing===[]?'CMS schema contract satisfied.':'CMS schema is incomplete.',
                    'details'=>['required_tables'=>16,'missing_tables'=>$missing,'read_only_probe'=>true],
                ];
            }catch(\Throwable $e){
                return ['status'=>'error','message'=>'CMS schema probe failed.','details'=>['required_tables'=>16,'exception'=>$e::class,'read_only_probe'=>true]];
            }
        }));

        $registry->register(new HealthCheckDefinition('system.media_native_api',$owner,static function():array{
            $class='Pinoox\Component\File\UploadBuilder';
            $methods=['access','package','extensions','maxSize','metadata','thumb','save'];
            $missing=[];
            if(class_exists($class))foreach($methods as $method)if(!method_exists($class,$method))$missing[]=$method;
            $ok=class_exists($class)&&$missing===[];
            return [
                'status'=>$ok?'ok':'error',
                'message'=>$ok?'Native Pinoox media upload contract satisfied.':'Native Pinoox media upload contract is incompatible.',
                'details'=>['class_available'=>class_exists($class),'required_methods'=>$methods,'missing_methods'=>$missing],
            ];
        }));

        $registry->register(new HealthCheckDefinition('system.database',$owner,static function():array{
            $class='Pinoox\\Portal\\Database\\DB';
            if(!class_exists($class))return['status'=>'error','message'=>'Pinoox DB facade is unavailable.','details'=>['facade_available'=>false,'connectivity_probe_bound'=>true]];
            try{
                $connected=$class::hasConnection();
                if(!$connected)return['status'=>'error','message'=>'Pinoox database connection is unavailable.','details'=>['facade_available'=>true,'connectivity_probe_bound'=>true,'connected'=>false]];
                $probe=$class::selectOne('SELECT 1 AS cms_health_probe');$connection=$class::connection();
                return['status'=>$probe!==null?'ok':'warning','message'=>$probe!==null?'Pinoox database connectivity probe passed.':'Pinoox database connected but SELECT probe returned no row.','details'=>['facade_available'=>true,'connectivity_probe_bound'=>true,'connected'=>true,'driver'=>method_exists($connection,'getDriverName')?(string)$connection->getDriverName():null,'database'=>method_exists($connection,'getDatabaseName')?(string)$connection->getDatabaseName():null]];
            }catch(\Throwable $e){return['status'=>'error','message'=>'Pinoox database connectivity probe failed.','details'=>['facade_available'=>true,'connectivity_probe_bound'=>true,'connected'=>false,'exception'=>$e::class]];}
        }));

        $registry->register(new HealthCheckDefinition('system.cache',$owner,static function():array{
            $available=class_exists('Pinoox\\Portal\\Cache');
            return [
                'status'=>$available?'unknown':'error',
                'message'=>$available
                    ? 'Pinoox Cache facade is available; read/write probe is not bound yet.'
                    : 'Pinoox Cache facade is unavailable.',
                'details'=>['facade_available'=>$available,'read_write_probe_bound'=>false],
            ];
        }));

        $registry->register(new HealthCheckDefinition('system.storage',$owner,static function():array{
            $available=class_exists('Pinoox\\Portal\\Storage');
            return [
                'status'=>$available?'unknown':'error',
                'message'=>$available
                    ? 'Pinoox Storage facade is available; configured-disk probe is not bound yet.'
                    : 'Pinoox Storage facade is unavailable.',
                'details'=>['facade_available'=>$available,'disk_probe_bound'=>false],
            ];
        }));

        $registry->register(new HealthCheckDefinition('system.scheduler',$owner,static function():array{
            $schedule=class_exists('Pinoox\\Cron\\Schedule');
            $task=class_exists('Pinoox\\Cron\\ScheduledTask');
            $overlap=$task&&method_exists('Pinoox\\Cron\\ScheduledTask','withoutOverlapping');
            return [
                'status'=>($schedule&&$task&&$overlap)?'ok':'warning',
                'message'=>($schedule&&$task&&$overlap)
                    ? 'Native Pinoox Scheduler primitives are available.'
                    : 'One or more Scheduler primitives are unavailable.',
                'details'=>[
                    'schedule'=>$schedule,
                    'scheduled_task'=>$task,
                    'without_overlapping'=>$overlap,
                    'host_cron_probe_bound'=>false,
                ],
            ];
        }));

        $registry->register(new HealthCheckDefinition('system.queue',$owner,static function():array{
            return [
                'status'=>'unknown',
                'message'=>'CMS Queue contract is available; durable repository/runner health probe must be bound by deployment.',
                'details'=>[
                    'shared_hosting_sync_fallback'=>true,
                    'runtime_monitor_bound'=>false,
                ],
            ];
        }));

        $registry->register(new HealthCheckDefinition('system.extensions',$owner,static function()use($extensionCount):array{
            return [
                'status'=>$extensionCount===null?'unknown':'ok',
                'message'=>$extensionCount===null
                    ? 'Extension registry count is not bound.'
                    : 'Extension registry is available.',
                'details'=>['registered_extensions'=>$extensionCount],
            ];
        }));
    }
}
