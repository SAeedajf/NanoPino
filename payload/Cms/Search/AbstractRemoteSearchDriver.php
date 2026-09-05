<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Search;

abstract class AbstractRemoteSearchDriver implements SearchDriverInterface
{
    public function __construct(
        protected readonly RemoteSearchTransportInterface $transport,
        private readonly string $driverId,
    ) {}

    final public function id(): string { return $this->driverId; }

    final public function index(SearchDocument $document): void
    {
        $this->transport->request($this->id(),'index',[
            'id'=>$document->id,
            'site_id'=>$document->siteId,
            'type'=>$document->type,
            'locale'=>$document->locale,
            'title'=>$document->title,
            'body'=>$document->body,
            'url'=>$document->url,
            'metadata'=>$document->metadata,
            'updated_at'=>$document->updatedAt,
        ]);
    }

    final public function delete(int $siteId,string $type,string $id,string $locale): void
    {
        $this->transport->request($this->id(),'delete',[
            'id'=>$id,'site_id'=>$siteId,'type'=>$type,'locale'=>$locale,
        ]);
    }

    final public function search(SearchQuery $query): SearchResult
    {
        $response=$this->transport->request($this->id(),'search',[
            'site_id'=>$query->siteId,
            'text'=>$query->text,
            'types'=>$query->types,
            'locale'=>$query->locale,
            'limit'=>$query->limit,
            'offset'=>$query->offset,
        ]);

        $hits=[];
        foreach (is_array($response['hits']??null) ? $response['hits'] : [] as $hit) {
            if (!is_array($hit)) continue;
            $hits[]=new SearchHit(
                (string)($hit['id']??''),
                (string)($hit['type']??''),
                (string)($hit['title']??''),
                isset($hit['url']) ? (string)$hit['url'] : null,
                (float)($hit['score']??0),
                (string)($hit['excerpt']??''),
                is_array($hit['metadata']??null) ? $hit['metadata'] : [],
            );
        }

        return new SearchResult(
            $hits,
            max(count($hits),(int)($response['total']??count($hits))),
            $this->id(),
        );
    }

    final public function health(): array
    {
        try {
            $response=$this->transport->request($this->id(),'health',[]);
            return [
                'status'=>(string)($response['status']??'ok'),
                'message'=>(string)($response['message']??($this->id().' search driver is reachable.')),
            ];
        } catch (\Throwable $error) {
            return [
                'status'=>'error',
                'message'=>$this->id().' search driver is unavailable.',
                'details'=>['error_class'=>$error::class],
            ];
        }
    }
}
