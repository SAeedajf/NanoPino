<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Authorization;

interface AccessGatewayInterface
{
    public function can(string $capability, ?int $subjectId = null): bool;

    /** @return list<string> */
    public function abilities(?int $subjectId = null): array;
}
