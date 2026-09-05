<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Search;

interface SearchDriverInterface
{
    public function id(): string;
    public function index(SearchDocument $document): void;
    public function delete(int $siteId, string $type, string $id, string $locale): void;
    public function search(SearchQuery $query): SearchResult;

    /** @return array{status:string,message:string,details?:array<string,mixed>} */
    public function health(): array;
}
