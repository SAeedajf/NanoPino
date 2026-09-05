<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance\Search;

use App\com_pinoox_cms\Cms\Performance\PerformanceMetric;
use App\com_pinoox_cms\Cms\Performance\PerformanceProfiler;
use App\com_pinoox_cms\Cms\Search\SearchDocument;
use App\com_pinoox_cms\Cms\Search\SearchDriverInterface;
use App\com_pinoox_cms\Cms\Search\SearchQuery;
use App\com_pinoox_cms\Cms\Search\SearchResult;

final readonly class ProfilingSearchDriver implements SearchDriverInterface
{
    public function __construct(
        private SearchDriverInterface $inner,
        private PerformanceProfiler $performance,
    ) {}

    public function id(): string { return $this->inner->id(); }
    public function index(SearchDocument $document): void { $this->inner->index($document); }
    public function delete(int $siteId,string $type,string $id,string $locale): void
    {
        $this->inner->delete($siteId,$type,$id,$locale);
    }

    public function search(SearchQuery $query): SearchResult
    {
        return $this->performance->measure(
            'search.query',
            PerformanceMetric::SearchMs,
            fn():SearchResult=>$this->inner->search($query),
            ['driver'=>$this->inner->id(),'site_id'=>$query->siteId],
        );
    }

    public function health(): array { return $this->inner->health(); }
}
