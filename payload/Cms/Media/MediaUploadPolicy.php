<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

final readonly class MediaUploadPolicy
{
    /**
     * @param array<string,list<string>> $mimeExtensions
     */
    public function __construct(
        public int $maxBytes = 20_971_520,
        public array $mimeExtensions = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
            'image/gif' => ['gif'],
            'video/mp4' => ['mp4'],
            'video/webm' => ['webm'],
            'audio/mpeg' => ['mp3'],
            'audio/wav' => ['wav'],
            'audio/x-wav' => ['wav'],
            'audio/ogg' => ['ogg'],
            'application/pdf' => ['pdf'],
        ],
        public bool $allowDocuments = true,
    ) {}

    /** @return list<string> */
    public function allowedExtensions(): array
    {
        $extensions = [];
        foreach ($this->mimeExtensions as $mime => $items) {
            if (!$this->allowDocuments && $mime === 'application/pdf') {
                continue;
            }
            foreach ($items as $extension) {
                $extensions[] = strtolower($extension);
            }
        }
        return array_values(array_unique($extensions));
    }
    /** @return array{max_bytes:int,max_mb:float,allowed_extensions:list<string>,mime_extensions:array<string,list<string>>,allow_documents:bool} */
    public function toArray(): array
    {
        return [
            'max_bytes' => $this->maxBytes,
            'max_mb' => round($this->maxBytes / 1048576, 2),
            'allowed_extensions' => $this->allowedExtensions(),
            'mime_extensions' => $this->mimeExtensions,
            'allow_documents' => $this->allowDocuments,
        ];
    }

}
