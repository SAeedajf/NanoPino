<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Output;

interface RichTextSanitizerInterface
{
    public function sanitize(string $source): SanitizedHtml;
}
