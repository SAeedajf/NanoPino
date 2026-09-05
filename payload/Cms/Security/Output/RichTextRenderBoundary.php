<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Output;

final readonly class RichTextRenderBoundary
{
    public function __construct(private RichTextSanitizerInterface $sanitizer) {}

    public function render(mixed $source): SanitizedHtml
    {
        if (!is_string($source)) {
            throw new \InvalidArgumentException('RichText rendering requires a string source.');
        }
        return $this->sanitizer->sanitize($source);
    }
}
