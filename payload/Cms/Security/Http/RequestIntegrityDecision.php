<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Http;

final readonly class RequestIntegrityDecision
{
    public function __construct(public bool $allowed, public string $reason) {}
}
