<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Network;

final readonly class InMemoryHostResolver implements HostResolverInterface
{
    /** @param array<string,list<string>> $records */
    public function __construct(private array $records) {}

    public function resolve(string $host): array
    {
        return $this->records[strtolower($host)] ?? [];
    }
}
