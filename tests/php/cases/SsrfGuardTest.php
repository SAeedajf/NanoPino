<?php
declare(strict_types=1);

use App\com_pinoox_cms\Cms\Security\Network\HostResolverInterface;
use App\com_pinoox_cms\Cms\Security\Network\NetworkEndpointPolicy;
use App\com_pinoox_cms\Cms\Security\Network\SsrfGuard;

final readonly class R13FakeResolver implements HostResolverInterface
{
    /** @param array<string,list<string>> $records */
    public function __construct(private array $records) {}

    public function resolve(string $host): array
    {
        return $this->records[$host] ?? [];
    }
}

return [
    'SSRF guard accepts allowlisted HTTPS host resolving only to public addresses' => static function (): void {
        $guard = new SsrfGuard(new R13FakeResolver([
            'search.example.com' => ['93.184.216.34', '2606:2800:220:1:248:1893:25c8:1946'],
        ]));
        $policy = new NetworkEndpointPolicy(['search.example.com']);

        $approved = $guard->assertAllowed('https://search.example.com/indexes/cms', $policy);
        np_assert_same('search.example.com', $approved['host']);
        np_assert_same(443, $approved['port']);
        np_assert_same(2, count($approved['addresses']));
    },

    'SSRF guard rejects HTTP when HTTPS is required' => static function (): void {
        $guard = new SsrfGuard(new R13FakeResolver(['search.example.com' => ['93.184.216.34']]));
        $policy = new NetworkEndpointPolicy(['search.example.com']);

        np_assert_throws(
            static fn () => $guard->assertAllowed('http://search.example.com/', $policy),
            InvalidArgumentException::class,
            'scheme is not allowed',
        );
    },

    'SSRF guard rejects URL userinfo and non-allowlisted hosts' => static function (): void {
        $guard = new SsrfGuard(new R13FakeResolver([
            'search.example.com' => ['93.184.216.34'],
            'evil.example.com' => ['93.184.216.34'],
        ]));
        $policy = new NetworkEndpointPolicy(['search.example.com']);

        np_assert_throws(
            static fn () => $guard->assertAllowed('https://user:pass@search.example.com/', $policy),
            InvalidArgumentException::class,
            'userinfo',
        );
        np_assert_throws(
            static fn () => $guard->assertAllowed('https://evil.example.com/', $policy),
            InvalidArgumentException::class,
            'not allowlisted',
        );
    },

    'SSRF guard rejects private or reserved DNS results' => static function (): void {
        $guard = new SsrfGuard(new R13FakeResolver([
            'search.example.com' => ['10.0.0.7'],
        ]));
        $policy = new NetworkEndpointPolicy(['search.example.com']);

        np_assert_throws(
            static fn () => $guard->assertAllowed('https://search.example.com/', $policy),
            RuntimeException::class,
            'private/reserved',
        );
    },

    'SSRF guard rejects obfuscated numeric host forms' => static function (): void {
        $guard = new SsrfGuard(new R13FakeResolver([
            '2130706433' => ['93.184.216.34'],
        ]));
        $policy = new NetworkEndpointPolicy(['2130706433']);

        np_assert_throws(
            static fn () => $guard->assertAllowed('https://2130706433/', $policy),
            InvalidArgumentException::class,
            'host is invalid',
        );
    },

    'Network endpoint policy requires explicit valid hosts and ports' => static function (): void {
        np_assert_throws(
            static fn () => new NetworkEndpointPolicy([]),
            InvalidArgumentException::class,
            'explicit allowed hosts',
        );
        np_assert_throws(
            static fn () => new NetworkEndpointPolicy(['search.example.com'], [0]),
            InvalidArgumentException::class,
            'Invalid outbound allowlisted port',
        );
    },
];
