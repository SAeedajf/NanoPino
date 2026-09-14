<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Runtime;

use App\com_pinoox_cms\Cms\Kernel\CmsKernel;
use App\com_pinoox_cms\Cms\Security\Http\{CmsPermissionFlow,CmsRequestIntegrityFlow,PinooxSecurityResponseListener};
use App\com_pinoox_cms\Cms\Security\RateLimit\{PinooxRateLimiterBridge,PinooxRateLimiterRegistrar};
use Pinoox\Component\AppEvent\{AppRegister,AppResponseEvent};

final class CmsRuntimeBinder
{
    public function bind(AppRegister $register): void
    {
        // IMPORTANT: database migrations must never run from app boot.
        // PINX install/update owns the migration lifecycle. Boot must remain
        // side-effect free so installer rebuild/register can complete first.
        CmsKernel::boot($register->package());

        // Attach request-local query telemetry to NanoPino's package connection.
        // Failure is non-fatal and is surfaced as queries.bound=false by the
        // Performance API instead of breaking application boot.
        CmsRuntimeServices::bindQueryProbe();

        // Replace only the CMS route permission adapter. The native decision
        // remains Access::can(); this fixes JSON negotiation for mounted
        // paths such as /nano/api/... without changing platform RBAC rules.
        $register->flowAlias([
            'permission' => CmsPermissionFlow::class,
            'cms_csrf' => CmsRequestIntegrityFlow::class,
        ]);
        RuntimeBindingState::markCsrf();

        (new PinooxRateLimiterRegistrar())->register(PinooxRateLimiterBridge::resolve());
        RuntimeBindingState::markRateLimits();

        $register->listen(AppResponseEvent::class, new PinooxSecurityResponseListener());
        RuntimeBindingState::markHeaders();

        $apiManifest = CmsRuntimeApiManifest::definition();
        CmsRuntimeApiManifest::validate($apiManifest);
        $register->api($apiManifest);
        RuntimeBindingState::markApi();
    }
}
