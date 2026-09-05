<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Search;

final class MeilisearchSearchDriver extends AbstractRemoteSearchDriver
{
    public function __construct(RemoteSearchTransportInterface $transport)
    {
        parent::__construct($transport,'meilisearch');
    }
}
