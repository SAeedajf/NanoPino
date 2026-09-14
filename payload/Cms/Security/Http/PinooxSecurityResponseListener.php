<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Http;

use App\com_pinoox_cms\Cms\Runtime\CmsApiResponse;
use Pinoox\Component\AppEvent\AppResponseEvent;

final readonly class PinooxSecurityResponseListener
{
    private SecurityHeadersPolicy $policy;

    public function __construct(?SecurityHeadersPolicy $policy = null)
    {
        $this->policy = $policy ?? new SecurityHeadersPolicy();
    }

    public function __invoke(AppResponseEvent $event): void
    {
        $this->normalizeApiDenial($event);

        $nonce = (string)$event->request->attributes->get('cms_csp_nonce', '');
        if ($nonce === '') {
            $nonce = CspPolicy::fromEnvironment()->nonce();
            $event->request->attributes->set('cms_csp_nonce', $nonce);
        }

        $https = $event->request->isSecure();
        foreach ($this->policy->headers($nonce, $https) as $name => $value) {
            if (!$event->response->headers->has($name)) {
                $event->response->headers->set($name, $value);
            }
        }
    }

    private function normalizeApiDenial(AppResponseEvent $event): void
    {
        $path = $event->request->getPathInfo();
        if (preg_match('#/(?:api/v[0-9]+/cms)(?:/|$)#', $path) !== 1) {
            return;
        }

        $status = $event->response->getStatusCode();
        if (!in_array($status, [401, 403], true)) {
            return;
        }

        $body = json_decode((string)$event->response->getContent(), true);
        if (is_array($body) && array_key_exists('success', $body) && array_key_exists('error', $body)) {
            return;
        }

        $normalized = CmsApiResponse::error(
            $status === 401 ? 'AUTHENTICATION_REQUIRED' : 'ACCESS_DENIED',
            $status === 401 ? 'Authentication is required.' : 'You do not have permission to perform this action.',
            $status,
            ['source' => 'native_permission_flow'],
        );

        $event->response->setContent($normalized->getContent());
        $event->response->headers->set('Content-Type', 'application/json');
        $event->response->headers->set('Cache-Control', 'no-store');
        $event->response->headers->set('X-CMS-API-ERROR', 'normalized');
    }
}
