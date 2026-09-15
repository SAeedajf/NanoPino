<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

/**
 * Temporary, signed native NanoShell theme package produced from a reviewed
 * WordPress archive. The package is deleted by the request boundary after the
 * native installer has consumed it.
 */
final readonly class WordPressThemeNativePackage
{
    /** @param array<string,mixed> $manifest @param array<string,mixed> $source */
    public function __construct(
        public string $path,
        public array $manifest,
        public array $source,
    ) {}
}
