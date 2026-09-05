<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Search;

final class UnboundRemoteSearchTransport implements RemoteSearchTransportInterface
{
    public function request(string $driver,string $operation,array $payload): array
    {
        throw new \RuntimeException($driver . ' remote search transport is not configured.');
    }
}
