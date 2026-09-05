<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Network;

final readonly class NetworkEndpointPolicy
{
    /**
     * @param list<string> $allowedHosts
     * @param list<int> $allowedPorts
     */
    public function __construct(
        public array $allowedHosts,
        public array $allowedPorts = [443],
        public bool $requireHttps = true,
    ) {
        if ($allowedHosts === []) {
            throw new \InvalidArgumentException('Outbound network policy requires explicit allowed hosts.');
        }
        foreach ($allowedHosts as $host) {
            if (!is_string($host) || !$this->validHostName($host)) {
                throw new \InvalidArgumentException('Invalid outbound allowlisted host.');
            }
        }
        foreach ($allowedPorts as $port) {
            if (!is_int($port) || $port < 1 || $port > 65535) {
                throw new \InvalidArgumentException('Invalid outbound allowlisted port.');
            }
        }
    }

    private function validHostName(string $host): bool
    {
        $host = strtolower(rtrim(trim($host), '.'));
        return $host !== ''
            && strlen($host) <= 253
            && preg_match('/^[a-z0-9.-]+$/', $host) === 1
            && !str_contains($host, '..');
    }
}
