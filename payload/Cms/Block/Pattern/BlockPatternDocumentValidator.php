<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Pattern;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentLoader;
use App\com_pinoox_cms\Cms\Theme\Pattern\ThemePatternDocumentValidatorInterface;

final readonly class BlockPatternDocumentValidator implements ThemePatternDocumentValidatorInterface
{
    public function __construct(private BlockDocumentLoader $loader) {}

    public function validate(array $document): void
    {
        $this->loader->fromArray($document);
    }
}
