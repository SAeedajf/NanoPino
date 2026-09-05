<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Search;

final readonly class SearchQuery
{
    /** @param list<string> $types */
    public function __construct(
        public int $siteId,
        public string $text,
        public array $types = [],
        public ?string $locale = null,
        public int $limit = 20,
        public int $offset = 0,
    ) {
        if ($siteId < 1 || $limit < 1 || $limit > 100 || $offset < 0 || $offset > 100_000) {
            throw new \InvalidArgumentException('Invalid search pagination/scope.');
        }
        if (strlen($text) > 500) {
            throw new \InvalidArgumentException('Search query is too long.');
        }
        foreach ($types as $type) {
            if (!is_string($type) || preg_match('/^[a-z][a-z0-9._-]{0,63}$/', $type) !== 1) {
                throw new \InvalidArgumentException('Invalid search type filter.');
            }
        }
    }
}
