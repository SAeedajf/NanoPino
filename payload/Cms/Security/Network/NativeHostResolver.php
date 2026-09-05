<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Network;

final class NativeHostResolver implements HostResolverInterface
{
    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) return [$host];

        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ip']) && is_string($record['ip'])) $ips[] = $record['ip'];
                if (isset($record['ipv6']) && is_string($record['ipv6'])) $ips[] = $record['ipv6'];
            }
        }

        if ($ips === []) {
            $fallback = @gethostbynamel($host);
            if (is_array($fallback)) $ips = array_merge($ips, $fallback);
        }

        return array_values(array_unique($ips));
    }
}
