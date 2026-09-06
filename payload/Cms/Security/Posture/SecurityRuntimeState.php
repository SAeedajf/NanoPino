<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Posture;

final readonly class SecurityRuntimeState
{
    public function __construct(
        public bool $csrfVerifierBound = false,
        public bool $rateLimitsRegistered = false,
        public bool $securityHeadersBound = false,
        public bool $ssrfTransportBound = false,
        public bool $publicApiSecurityBound = false,
        public bool $cspEnforced = false,
        public bool $implicitPlatformSuperEnabled = true,
        public bool $explicitPlatformSuperReady = false,
    ) {}
}
