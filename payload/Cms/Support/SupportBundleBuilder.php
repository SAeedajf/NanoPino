<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Support;
use App\com_pinoox_cms\Cms\Compatibility\KernelCompatibilityChecker;
use App\com_pinoox_cms\Cms\Compatibility\RuntimeKernelInfoResolver;
use App\com_pinoox_cms\Cms\Health\HealthRunner;
use App\com_pinoox_cms\Cms\Logging\CmsLoggerInterface;
use App\com_pinoox_cms\Cms\Recovery\SafeModeManager;
use App\com_pinoox_cms\Cms\Security\Secrets\SensitiveDataRedactor;
final readonly class SupportBundleBuilder {
 public function __construct(
  private HealthRunner $health, private CmsLoggerInterface $logs,
  private SafeModeManager $safeMode, private SensitiveDataRedactor $redactor=new SensitiveDataRedactor()
 ) {}
 public function build(?int $kernelCode=null,?string $kernelVersion=null):SupportBundle{
  if($kernelCode===null||$kernelVersion===null||trim((string)$kernelVersion)===''){
   $runtime=(new RuntimeKernelInfoResolver())->resolve();
   $kernelCode??=$runtime->code;
   if($kernelVersion===null||trim((string)$kernelVersion)==='')$kernelVersion=$runtime->version;
  }
  $results=$this->health->runAll();$compat=(new KernelCompatibilityChecker())->check($kernelCode,$kernelVersion);
  $logRows=array_map(static fn($r):array=>$r->toArray(),$this->logs->tail(100));
  $data=[
   'runtime'=>['php'=>PHP_VERSION,'sapi'=>PHP_SAPI,'os'=>PHP_OS_FAMILY],
   'kernel'=>$compat->toArray(),
   'health'=>array_map(static fn($r):array=>$r->toArray(),$results),
   'safe_mode'=>$this->safeMode->state()->toArray(),
   'logs'=>$logRows,
  ];
  $safe=$this->redactor->redact($data);
  return new SupportBundle('support-'.bin2hex(random_bytes(8)),microtime(true),is_array($safe)?$safe:[]);
 }
 public function writeJson(SupportBundle $bundle,string $file):void{
  $dir=dirname($file);if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new \RuntimeException('Unable to create support bundle directory.');
  $tmp=$file.'.tmp-'.bin2hex(random_bytes(4));if(file_put_contents($tmp,$bundle->json(),LOCK_EX)===false||!@rename($tmp,$file)){@unlink($tmp);throw new \RuntimeException('Unable to persist support bundle.');}
 }
}
