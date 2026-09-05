<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin\Frontend;

final readonly class AdminFrontendAssetStatus
{
    /**
     * @param list<string> $assets
     */
    public function __construct(
        public bool $ready,
        public string $mode,
        public string $code,
        public string $message,
        public string $entry = 'src/main.js',
        public string $manifest = 'dist/.vite/manifest.json',
        public array $assets = [],
        public ?string $manifestChecksum = null,
        public ?string $buildId = null,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'ready' => $this->ready,
            'mode' => $this->mode,
            'code' => $this->code,
            'message' => $this->message,
            'entry' => $this->entry,
            'manifest' => $this->manifest,
            'assets' => $this->assets,
            'assetCount' => count($this->assets),
            'manifestChecksum' => $this->manifestChecksum,
            'buildId' => $this->buildId,
        ];
    }
}
