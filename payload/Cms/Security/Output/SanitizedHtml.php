<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Output;

final readonly class SanitizedHtml
{
    public function __construct(private string $html) {}

    public function value(): string { return $this->html; }
    public function __toString(): string { return $this->html; }
}
