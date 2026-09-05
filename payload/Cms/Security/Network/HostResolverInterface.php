<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Network;

interface HostResolverInterface
{
    /** @return list<string> */
    public function resolve(string $host): array;
}
