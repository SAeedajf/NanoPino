<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Http;

final readonly class RequestSecurityContext
{
    public function __construct(
        public string $method,
        public AuthMechanism $auth,
        public ?int $subjectId = null,
        public ?string $csrfToken = null,
        public bool $sameOrigin = true,
        public ?string $contentType = null,
    ) {}

    public function method(): string { return strtoupper(trim($this->method)); }
}
