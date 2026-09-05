<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Design;

use App\com_pinoox_cms\Cms\Theme\ThemeFileReader;

final class DesignDocumentLoader
{
    public function __construct(
        private readonly ThemeFileReader $files = new ThemeFileReader(),
        private readonly DesignSchemaValidator $validator = new DesignSchemaValidator(),
    ) {}

    public function load(string $themeRoot, string $relativeFile = 'design.json'): ?DesignDocument
    {
        $raw = $this->files->json($themeRoot, $relativeFile);
        return $raw !== null ? $this->validator->validate($raw) : null;
    }
}
