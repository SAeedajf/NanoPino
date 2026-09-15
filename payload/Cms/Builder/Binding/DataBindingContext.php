<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Binding;

use App\com_pinoox_cms\Cms\Content\ContentRecord;
use InvalidArgumentException;

final readonly class DataBindingContext
{
    /** @param array<string,int|bool|string|null> $pagination */
    public function __construct(
        public int $siteId = 1,
        public string $locale = 'fa',
        public ?ContentRecord $currentContent = null,
        public array $pagination = [],
        public bool $publicOnly = true,
    ) {
        if ($siteId < 1) throw new InvalidArgumentException('Data binding site ID must be positive.');
        if (preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale) !== 1) {
            throw new InvalidArgumentException('Data binding locale is invalid.');
        }
        if (count($pagination) > 32) throw new InvalidArgumentException('Data binding pagination metadata is too large.');
    }

    /** @param array<string,int|bool|string|null> $pagination */
    public function withPagination(array $pagination): self
    {
        return new self($this->siteId, $this->locale, $this->currentContent, $pagination, $this->publicOnly);
    }
}
