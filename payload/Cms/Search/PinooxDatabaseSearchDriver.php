<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Search;

use App\com_pinoox_cms\Cms\Database\CmsDatabase;

final class PinooxDatabaseSearchDriver implements SearchDriverInterface
{
    private const TABLE = 'search_documents';

    /** @var list<string> Columns required to materialize a search hit. */
    private const HIT_COLUMNS = [
        'document_id',
        'document_type',
        'title',
        'url',
        'metadata_json',
    ];

    public function id(): string { return 'database'; }

    public function index(SearchDocument $document): void
    {
        CmsDatabase::transaction(function () use ($document): void {
            $payload=[
                'site_id'=>$document->siteId,
                'document_type'=>$document->type,
                'document_id'=>$document->id,
                'locale'=>$document->locale,
                'title'=>$document->title,
                'search_text'=>$document->normalizedText(),
                'url'=>$document->url,
                'metadata_json'=>json_encode(
                    $document->metadata,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),
                'source_updated_at'=>$document->updatedAt !== null
                    ? gmdate('Y-m-d H:i:s',(int)$document->updatedAt)
                    : null,
                'updated_at'=>gmdate('Y-m-d H:i:s'),
            ];
            $payload['created_at']=gmdate('Y-m-d H:i:s');
            // The unique key is the final authority for an index document.
            // An atomic upsert avoids the exists-then-insert race that could
            // otherwise turn concurrent indexing into a duplicate-key error.
            CmsDatabase::table(self::TABLE)->upsert(
                [$payload],
                ['site_id','document_type','document_id','locale'],
                ['title','search_text','url','metadata_json','source_updated_at','updated_at'],
            );
        });
    }

    public function delete(int $siteId,string $type,string $id,string $locale): void
    {
        CmsDatabase::table(self::TABLE)
            ->where('site_id',$siteId)
            ->where('document_type',$type)
            ->where('document_id',$id)
            ->where('locale',$locale)
            ->delete();
    }

    public function search(SearchQuery $query): SearchResult
    {
        $builder=CmsDatabase::table(self::TABLE)->where('site_id',$query->siteId);

        if ($query->locale !== null) {
            $builder->where('locale',$query->locale);
        }
        if ($query->types !== []) {
            $builder->whereIn('document_type',$query->types);
        }

        $needle=$this->lower(trim($query->text));
        if ($needle !== '') {
            $like='%' . $this->escapeLike($needle) . '%';
            $builder->where(function($nested) use ($like): void {
                $nested
                    ->where('title','like',$like)
                    ->orWhere('search_text','like',$like);
            });
        }

        $total=(clone $builder)->count();

        $rows=$builder
            // Search documents can contain large normalized text and metadata.
            // Keep the result projection narrow so list/search requests do not
            // transfer the full index row for every hit.
            ->select(self::HIT_COLUMNS)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $hits=[];
        foreach ($rows as $row) {
            $title=(string)$row->title;
            $score=$needle !== '' && str_contains($this->lower($title),$needle) ? 10.0 : 5.0;
            try {
                $metadata=json_decode((string)$row->metadata_json,true,512,JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $metadata=[];
            }

            $hits[]=new SearchHit(
                (string)$row->document_id,
                (string)$row->document_type,
                $title,
                $row->url !== null ? (string)$row->url : null,
                $score,
                '',
                is_array($metadata) ? $metadata : [],
            );
        }

        return new SearchResult($hits,(int)$total,$this->id());
    }

    public function health(): array
    {
        try {
            CmsDatabase::table(self::TABLE)->limit(1)->get();
            return ['status'=>'ok','message'=>'Database search index is reachable.'];
        } catch (\Throwable $error) {
            return [
                'status'=>'error',
                'message'=>'Database search index is unavailable.',
                'details'=>['error_class'=>$error::class],
            ];
        }
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$value);
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value,'UTF-8') : strtolower($value);
    }
}
