<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Health;
use App\com_pinoox_cms\Cms\Security\Secrets\SensitiveDataRedactor;
final readonly class HealthRunner {
 public function __construct(
  private HealthCheckRegistry $registry,
  private SensitiveDataRedactor $redactor=new SensitiveDataRedactor(),
 ) {}
 /** @return list<HealthResult> */
 public function runAll():array {
  $rows=[];
  foreach($this->registry->all() as $definition){
   if(!$definition instanceof HealthCheckDefinition)continue;
   $start=hrtime(true);
   try{
    $raw=$definition->run();
    $status=HealthStatus::tryFrom((string)($raw['status']??'unknown'))??HealthStatus::Unknown;
    $message=$this->safeMessage((string)($raw['message']??''));
    $details=$this->redactor->redact(is_array($raw['details']??null)?$raw['details']:[]);
    $rows[]=new HealthResult($definition->identifier(),$status,$message,(hrtime(true)-$start)/1_000_000,microtime(true),is_array($details)?$details:[]);
   }catch(\Throwable $e){
    $rows[]=new HealthResult($definition->identifier(),HealthStatus::Error,'Health check failed safely.',(hrtime(true)-$start)/1_000_000,microtime(true),['exception'=>$e::class]);
   }
  }
  return$rows;
 }
 public function overall(array $results):HealthStatus {
  $worst=HealthStatus::Ok;
  foreach($results as $r){if($r instanceof HealthResult&&$r->status->rank()>$worst->rank())$worst=$r->status;}
  return$worst;
 }
 private function safeMessage(string $m):string{
  $m=str_replace(["\0","\r","\n"],['','',' '],$m);
  $safe=$this->redactor->redact($m);
  return substr(is_string($safe)?$safe:'[REDACTED]',0,1000);
 }
}
