<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Search;

use App\com_pinoox_cms\Cms\Queue\QueueJobDefinition;
use App\com_pinoox_cms\Cms\Queue\QueueRegistry;

final readonly class SearchQueueRegistrar
{
    public function __construct(private SearchDriverInterface $search) {}

    public function register(QueueRegistry $registry,string $owner='cms.core'): void
    {
        $search=$this->search;

        $registry->register(new QueueJobDefinition(
            'search.index',
            $owner,
            static function(array $payload) use($search):void {
                $document=is_array($payload['document']??null)
                    ? SearchDocument::fromArray($payload['document'])
                    : throw new \InvalidArgumentException('Search index job requires document.');
                $search->index($document);
            },
            maxAttempts:5,
            baseBackoffSeconds:15,
            timeoutSeconds:120,
            maxPayloadBytes:2_500_000,
        ));

        $registry->register(new QueueJobDefinition(
            'search.delete',
            $owner,
            static function(array $payload) use($search):void {
                $search->delete(
                    (int)($payload['site_id']??0),
                    (string)($payload['type']??''),
                    (string)($payload['id']??''),
                    (string)($payload['locale']??''),
                );
            },
            maxAttempts:5,
            baseBackoffSeconds:15,
            timeoutSeconds:60,
            maxPayloadBytes:16384,
        ));
    }
}
