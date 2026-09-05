<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Search;

use App\com_pinoox_cms\Cms\Queue\QueueDispatcher;
use App\com_pinoox_cms\Cms\Queue\QueueDispatchResult;

final readonly class QueuedSearchIndexer
{
    public function __construct(private QueueDispatcher $queue) {}

    public function index(SearchDocument $document,?string $correlationId=null): QueueDispatchResult
    {
        return $this->queue->dispatch(
            'search.index',
            ['document'=>$document->toArray()],
            'search:index:' . hash('sha256',$document->siteId.'|'.$document->type.'|'.$document->locale.'|'.$document->id.'|'.($document->updatedAt??'')),
            $correlationId,
        );
    }

    public function delete(
        int $siteId,string $type,string $id,string $locale,?string $correlationId=null
    ): QueueDispatchResult {
        return $this->queue->dispatch(
            'search.delete',
            ['site_id'=>$siteId,'type'=>$type,'id'=>$id,'locale'=>$locale],
            'search:delete:' . hash('sha256',$siteId.'|'.$type.'|'.$locale.'|'.$id),
            $correlationId,
        );
    }
}
