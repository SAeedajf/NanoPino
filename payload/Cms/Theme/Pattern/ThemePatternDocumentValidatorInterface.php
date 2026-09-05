<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Pattern;

interface ThemePatternDocumentValidatorInterface
{
    /** @param array<string,mixed> $document */
    public function validate(array $document): void;
}
