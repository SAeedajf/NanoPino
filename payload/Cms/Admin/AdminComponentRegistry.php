<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Admin;
use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;
final class AdminComponentRegistry extends AbstractOwnedRegistry {
 protected function assertDefinition(OwnedDefinitionInterface $definition):void{
  if(!$definition instanceof AdminComponentDefinition)throw new \InvalidArgumentException('AdminComponentRegistry accepts AdminComponentDefinition only.');
 }
 /** @return list<AdminComponentDefinition> */
 public function definitions():array{return array_values(array_filter(parent::all(),static fn($d)=>$d instanceof AdminComponentDefinition));}
}
