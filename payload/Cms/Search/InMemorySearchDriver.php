<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Search;

final class InMemorySearchDriver implements SearchDriverInterface
{
    /** @var array<string,SearchDocument> */
    private array $documents = [];

    public function id(): string { return 'memory'; }

    public function index(SearchDocument $document): void
    {
        $this->documents[$this->key($document->siteId,$document->type,$document->id,$document->locale)] = $document;
    }

    public function delete(int $siteId, string $type, string $id, string $locale): void
    {
        unset($this->documents[$this->key($siteId,$type,$id,$locale)]);
    }

    public function search(SearchQuery $query): SearchResult
    {
        $needle = $this->lower(trim($query->text));
        $hits = [];

        foreach ($this->documents as $document) {
            if ($document->siteId !== $query->siteId) continue;
            if ($query->locale !== null && $document->locale !== $query->locale) continue;
            if ($query->types !== [] && !in_array($document->type,$query->types,true)) continue;

            $haystack = $document->normalizedText();
            if ($needle !== '' && !str_contains($haystack,$needle)) continue;

            $title = $this->lower($document->title);
            $score = $needle === '' ? 1.0 : (
                str_contains($title,$needle) ? 10.0 : 5.0
            );

            $hits[] = new SearchHit(
                $document->id,
                $document->type,
                $document->title,
                $document->url,
                $score,
                $this->excerpt($document->body,$needle),
                $document->metadata,
            );
        }

        usort($hits,static fn(SearchHit $a,SearchHit $b):int =>
            $b->score <=> $a->score ?: strcmp($a->id,$b->id)
        );

        $total=count($hits);
        return new SearchResult(
            array_slice($hits,$query->offset,$query->limit),
            $total,
            $this->id(),
        );
    }

    public function health(): array
    {
        return ['status'=>'ok','message'=>'In-memory search driver is available.','details'=>['documents'=>count($this->documents)]];
    }

    private function key(int $siteId,string $type,string $id,string $locale): string
    {
        return $siteId . '|' . $type . '|' . $locale . '|' . $id;
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value,'UTF-8') : strtolower($value);
    }

    private function excerpt(string $body,string $needle): string
    {
        $body=trim(preg_replace('/\s+/u',' ',$body) ?? $body);
        if ($body==='') return '';
        if ($needle==='') return $this->substr($body,0,180);

        $lower=$this->lower($body);
        $position=strpos($lower,$needle);
        $start=$position===false ? 0 : max(0,$position-60);
        return $this->substr($body,$start,180);
    }

    private function substr(string $value,int $start,int $length): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value,$start,$length,'UTF-8')
            : substr($value,$start,$length);
    }
}
