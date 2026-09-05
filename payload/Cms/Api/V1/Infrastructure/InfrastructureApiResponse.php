<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Infrastructure;

final readonly class InfrastructureApiResponse
{
    /** @param array<string,mixed> $body */
    public function __construct(public int $status,public array $body) {}
}
