<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Http;

final readonly class InMemoryCsrfTokenVerifier implements CsrfTokenVerifierInterface
{
    /** @param array<int,string> $tokens */
    public function __construct(private array $tokens) {}

    public function verify(?string $token, ?int $subjectId): bool
    {
        if ($token === null || $subjectId === null || !isset($this->tokens[$subjectId])) return false;
        return hash_equals($this->tokens[$subjectId], $token);
    }
}
