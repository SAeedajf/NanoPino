<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Sdk\Package;
final readonly class ScaffoldResult {
 /** @param list<string> $files */
 public function __construct(public string $directory,public array $files,public array $manifest){}
}
