<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Recovery;
use App\com_pinoox_cms\Cms\Logging\CmsLoggerInterface;
final readonly class QuarantineDiagnosticsService {
 public function __construct(private SafeModeManager $safeMode,private CmsLoggerInterface $logs){}
 /** @return array<string,mixed> */
 public function snapshot():array{
  $state=$this->safeMode->state();$recent=[];
  foreach($this->logs->tail(100) as $record){
   $extension=$record->context['extension_id']??null;
   if(is_string($extension)&&in_array($extension,$state->quarantined,true))$recent[]=$record->toArray();
  }
  return['safe_mode'=>$state->toArray(),'quarantined_count'=>count($state->quarantined),'related_logs'=>array_slice($recent,0,30)];
 }
}
