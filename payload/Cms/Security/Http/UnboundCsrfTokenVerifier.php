<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Http;

final class UnboundCsrfTokenVerifier implements CsrfTokenVerifierInterface
{
    public function verify(?string $token, ?int $subjectId): bool
    {
        return false;
    }
}
