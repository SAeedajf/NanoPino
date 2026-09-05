<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Search;

final readonly class SearchManager
{
    public function __construct(
        private SearchDriverInterface $primary,
        private ?SearchDriverInterface $fallback = null,
    ) {}

    public function index(SearchDocument $document): void
    {
        $this->primary->index($document);
    }

    public function delete(int $siteId,string $type,string $id,string $locale): void
    {
        $this->primary->delete($siteId,$type,$id,$locale);
    }

    public function search(SearchQuery $query): SearchResult
    {
        try {
            return $this->primary->search($query);
        } catch (\Throwable) {
            if ($this->fallback === null) throw new \RuntimeException('Primary search driver failed and no fallback is configured.');

            $result=$this->fallback->search($query);
            return new SearchResult($result->hits,$result->total,$result->driver,true);
        }
    }
}
