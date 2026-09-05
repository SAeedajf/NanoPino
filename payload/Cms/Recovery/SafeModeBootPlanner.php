<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Recovery;
final readonly class SafeModeBootPlanner {
 public function __construct(private SafeModeManager $safeMode){}
 /** @param list<string> $packages @param list<string> $corePackages @return array{boot:list<string>,skipped:list<string>,state:array<string,mixed>} */
 public function plan(array $packages,array $corePackages=['com_pinoox_cms']):array{
  $boot=[];$skipped=[];
  foreach(array_values(array_unique($packages)) as $package){
   if(!is_string($package)||$package==='')continue;
   $isCore=in_array($package,$corePackages,true);
   if($this->safeMode->mayBoot($package,$isCore))$boot[]=$package;else$skipped[]=$package;
  }
  return['boot'=>$boot,'skipped'=>$skipped,'state'=>$this->safeMode->state()->toArray()];
 }
}
