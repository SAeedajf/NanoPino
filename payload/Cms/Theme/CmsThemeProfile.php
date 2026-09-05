<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme;

final readonly class CmsThemeProfile
{
    /**
     * @param list<string> $templateExtensions
     * @param array<string,mixed> $features
     */
    public function __construct(
        public int $schemaVersion = 1,
        public ?string $minimumCms = null,
        public ?string $maximumCms = null,
        public string $designFile = 'design.json',
        public string $templateDirectory = 'templates',
        public string $partDirectory = 'parts',
        public string $patternDirectory = 'patterns',
        public string $variationDirectory = 'styles/variations',
        public array $templateExtensions = ['twig', 'php', 'html'],
        public array $features = [],
    ) {}
}
