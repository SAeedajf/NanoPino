<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Http;

use Pinoox\Component\AppEvent\AppResponseEvent;

final readonly class PinooxSecurityResponseListener
{
    public function __construct(private SecurityHeadersPolicy $policy = new SecurityHeadersPolicy()) {}

    public function __invoke(AppResponseEvent $event): void
    {
        $nonce = (string)$event->request->attributes->get('cms_csp_nonce', '');
        if ($nonce === '') {
            $nonce = (new CspPolicy())->nonce();
            $event->request->attributes->set('cms_csp_nonce', $nonce);
        }

        $https = $event->request->isSecure();
        foreach ($this->policy->headers($nonce, $https) as $name => $value) {
            if (!$event->response->headers->has($name)) {
                $event->response->headers->set($name, $value);
            }
        }
    }
}
