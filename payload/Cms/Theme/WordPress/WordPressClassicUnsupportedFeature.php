<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

final readonly class WordPressClassicUnsupportedFeature
{
    public function __construct(
        public string $code,
        public string $category,
        public string $severity,
        public string $message,
        public string $path,
        public int $occurrences = 1,
        public string $recommendation = '',
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'category' => $this->category,
            'severity' => $this->severity,
            'message' => $this->message,
            'path' => $this->path,
            'occurrences' => $this->occurrences,
            'recommendation' => $this->recommendation,
        ];
    }
}
