<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Search;

final readonly class SearchDocument
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public string $id,
        public int $siteId,
        public string $type,
        public string $locale,
        public string $title,
        public string $body,
        public ?string $url = null,
        public array $metadata = [],
        public ?float $updatedAt = null,
    ) {
        if ($id === '' || strlen($id) > 96 || $siteId < 1) {
            throw new \InvalidArgumentException('Invalid search document identity.');
        }
        if (preg_match('/^[a-z][a-z0-9._-]{0,63}$/', $type) !== 1) {
            throw new \InvalidArgumentException('Invalid search document type.');
        }
        if ($locale === '' || strlen($locale) > 16) {
            throw new \InvalidArgumentException('Invalid search document locale.');
        }
        if (strlen($title) > 1000 || strlen($body) > 2_000_000) {
            throw new \InvalidArgumentException('Search document exceeds size limits.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id'=>$this->id,
            'site_id'=>$this->siteId,
            'type'=>$this->type,
            'locale'=>$this->locale,
            'title'=>$this->title,
            'body'=>$this->body,
            'url'=>$this->url,
            'metadata'=>$this->metadata,
            'updated_at'=>$this->updatedAt,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['id']??''),
            (int)($data['site_id']??0),
            (string)($data['type']??''),
            (string)($data['locale']??''),
            (string)($data['title']??''),
            (string)($data['body']??''),
            isset($data['url']) ? (string)$data['url'] : null,
            is_array($data['metadata']??null) ? $data['metadata'] : [],
            isset($data['updated_at']) ? (float)$data['updated_at'] : null,
        );
    }

    public function normalizedText(): string
    {
        $value = trim($this->title . "\n" . $this->body);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}
