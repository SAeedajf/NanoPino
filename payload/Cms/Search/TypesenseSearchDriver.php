<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Search;

final class TypesenseSearchDriver extends AbstractRemoteSearchDriver
{
    public function __construct(RemoteSearchTransportInterface $transport)
    {
        parent::__construct($transport,'typesense');
    }
}
