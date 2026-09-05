<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Search;

interface RemoteSearchTransportInterface
{
    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function request(string $driver,string $operation,array $payload): array;
}
