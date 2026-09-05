<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Sdk\Package;
final readonly class SdkPackageValidationResult {
 /** @param list<string> $errors @param list<string> $warnings */
 public function __construct(public array $errors=[],public array $warnings=[]){}
 public function valid():bool{return $this->errors===[];}
}
