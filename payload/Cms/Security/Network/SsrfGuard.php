<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Network;

final readonly class SsrfGuard
{
    public function __construct(private HostResolverInterface $resolver) {}

    /** @return array{url:string,host:string,port:int,addresses:list<string>} */
    public function assertAllowed(string $url, NetworkEndpointPolicy $policy): array
    {
        if ($url === '' || strlen($url) > 4096 || preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            throw new \InvalidArgumentException('Outbound URL is invalid.');
        }

        $parts = parse_url($url);
        if ($parts === false) throw new \InvalidArgumentException('Outbound URL could not be parsed.');

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if ($scheme === '' || ($policy->requireHttps && $scheme !== 'https')) {
            throw new \InvalidArgumentException('Outbound URL scheme is not allowed.');
        }
        if (!in_array($scheme, ['https','http'], true)) {
            throw new \InvalidArgumentException('Outbound URL scheme is not allowed.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('Outbound URL userinfo is forbidden.');
        }

        $host = strtolower(rtrim((string)($parts['host'] ?? ''), '.'));
        if ($host === '' || $this->looksObfuscatedNumericHost($host)) {
            throw new \InvalidArgumentException('Outbound host is invalid.');
        }

        $allowed = array_map(static fn(string $h):string => strtolower(rtrim($h, '.')), $policy->allowedHosts);
        if (!in_array($host, $allowed, true)) {
            throw new \InvalidArgumentException('Outbound host is not allowlisted.');
        }

        $port = isset($parts['port']) ? (int)$parts['port'] : ($scheme === 'https' ? 443 : 80);
        if (!in_array($port, $policy->allowedPorts, true)) {
            throw new \InvalidArgumentException('Outbound port is not allowlisted.');
        }

        $addresses = $this->resolver->resolve($host);
        if ($addresses === []) {
            throw new \RuntimeException('Outbound host did not resolve.');
        }

        foreach ($addresses as $ip) {
            if (!$this->isPublicAddress($ip)) {
                throw new \RuntimeException('Outbound host resolves to a private/reserved address.');
            }
        }

        return ['url'=>$url,'host'=>$host,'port'=>$port,'addresses'=>$addresses];
    }

    private function isPublicAddress(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    private function looksObfuscatedNumericHost(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) return true;
        if (preg_match('/^\d+$/', $host) === 1) return true;
        if (preg_match('/^0x[0-9a-f]+$/i', $host) === 1) return true;
        if (preg_match('/^[0-9.]+$/', $host) === 1 && !filter_var($host, FILTER_VALIDATE_IP)) return true;
        return false;
    }
}
