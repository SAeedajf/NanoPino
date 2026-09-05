<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Http;

final readonly class SecurityHeadersPolicy
{
    public function __construct(
        private CspPolicy $csp = new CspPolicy(),
        private bool $hsts = true,
    ) {}

    /** @return array<string,string> */
    public function headers(string $nonce, bool $https): array
    {
        $headers = [
            $this->csp->headerName() => $this->csp->header($nonce),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];

        if ($https && $this->hsts) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $headers;
    }
}
