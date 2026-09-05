<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Package;

use App\com_pinoox_cms\Cms\Installer\PackageFilePlan;
use App\com_pinoox_cms\Cms\Installer\PackagePayloadReaderInterface;

final class ExtensionStaticRiskScanner
{
    /** @return list<StaticRiskFinding> */
    public function scan(
        PackageFilePlan $plan,
        PackagePayloadReaderInterface $payload,
        int $maxScanBytesPerFile = 1_048_576,
    ): array {
        $findings = [];

        foreach ($plan->files() as $entry) {
            $extension = strtolower((string)pathinfo($entry->path, PATHINFO_EXTENSION));
            if (!in_array($extension, ['php','phtml','inc','js','mjs'], true)) continue;

            $content = $payload->read($entry->path);
            if (strlen($content) > $maxScanBytesPerFile) {
                $content = substr($content, 0, $maxScanBytesPerFile);
                $findings[] = new StaticRiskFinding(
                    $entry->path,
                    'scan.truncated',
                    StaticRiskSeverity::Info,
                    'Static security scan was truncated for this file.',
                );
            }

            foreach ($this->rulesFor($extension) as [$rule,$severity,$pattern,$message]) {
                if (preg_match($pattern, $content) === 1) {
                    $findings[] = new StaticRiskFinding(
                        $entry->path,
                        $rule,
                        $severity,
                        $message,
                    );
                }
            }
        }

        return $findings;
    }

    /** @return list<array{string,StaticRiskSeverity,string,string}> */
    private function rulesFor(string $extension): array
    {
        if (in_array($extension, ['php', 'phtml', 'inc'], true)) {
            return [
                ['php.dynamic_eval', StaticRiskSeverity::High, '/\beval\s*\(/i', 'Dynamic PHP evaluation detected.'],
                ['php.process_exec', StaticRiskSeverity::High, '/\b(?:exec|shell_exec|system|passthru|proc_open|popen)\s*\(/i', 'Operating-system process execution primitive detected.'],
                ['php.deserialize', StaticRiskSeverity::Warning, '/\bunserialize\s*\(/i', 'PHP unserialize call detected; object injection review required.'],
                ['php.raw_network', StaticRiskSeverity::Warning, '/\b(?:curl_exec|fsockopen|stream_socket_client)\s*\(/i', 'Raw outbound network primitive detected.'],
                ['php.dynamic_include', StaticRiskSeverity::Warning, '/\b(?:include|require)(?:_once)?\s*\(\s*\$/i', 'Dynamic include/require target detected.'],
            ];
        }

        if (in_array($extension, ['js', 'mjs'], true)) {
            return [
                ['js.dynamic_code', StaticRiskSeverity::High, '/\b(?:eval|Function)\s*\(/', 'Dynamic JavaScript code execution primitive detected.'],
            ];
        }

        return [];
    }

}
