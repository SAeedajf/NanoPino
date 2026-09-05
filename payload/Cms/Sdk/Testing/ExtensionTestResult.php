<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Sdk\Testing;
final readonly class ExtensionTestResult {
 public function __construct(public array $definitions,public array $nativeCalls,public array $diagnostics){}
}
