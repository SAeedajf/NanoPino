<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Search;

use App\com_pinoox_cms\Cms\Database\CmsDatabase;

final class PinooxDatabaseSearchDriver implements SearchDriverInterface
{
    private const TABLE = 'search_documents';

    public function id(): string { return 'database'; }

    public function index(SearchDocument $document): void
    {
        CmsDatabase::transaction(function () use ($document): void {
            $query = CmsDatabase::table(self::TABLE)
                ->where('site_id',$document->siteId)
                ->where('document_type',$document->type)
                ->where('document_id',$document->id)
                ->where('locale',$document->locale);

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

            if ($query->exists()) {
                $query->update($payload);
                return;
            }

            $payload['created_at']=gmdate('Y-m-d H:i:s');
            CmsDatabase::table(self::TABLE)->insert($payload);
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
            ->orderByDesc('updated_at')
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
