<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Preview;

final readonly class ThemePreviewTicket
{
    public function __construct(
        public string $token,
        public ThemePreviewSession $session,
    ) {}
}
