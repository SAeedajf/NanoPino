<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Http;

use App\com_pinoox_cms\Cms\Runtime\CmsApiResponse;
use Closure;
use Pinoox\Component\Flow\Flow;
use Pinoox\Component\Http\Request;
use Pinoox\Portal\Access;
use Pinoox\Portal\Auth;

/**
 * CMS-owned adapter for the native permission flow.
 *
 * Pincore's PermissionFlow detects JSON requests only when the path starts
 * with /api. A mounted CMS path starts with its mount (for example /nano),
 * which made denied API requests fall back to an HTML 403 response. Keep the
 * native authorization decision, but make the response mount-aware.
 */
final class CmsPermissionFlow extends Flow
{
    protected function handle(Request $request, Closure $next): mixed
    {
        $permission = Access::routePermission(
            $request->attributes->get('_router'),
            $request->attributes->all(),
        );

        if ($permission === null || $permission === '') {
            return $next($request);
        }

        Auth::boot();
        if (Access::can($permission)) {
            return $next($request);
        }

        return self::denialResponse($permission, Auth::check(), self::isApiRequest($request));
    }

    /**
     * Keep authentication and authorization failures distinguishable for both
     * the browser shell and API clients. The native Access::can decision still
     * remains the source of truth; this method only formats its denial.
     */
    public static function denialResponse(string $permission, bool $authenticated, bool $api): mixed
    {
        $status = $authenticated ? 403 : 401;
        $code = $authenticated ? 'ACCESS_DENIED' : 'AUTHENTICATION_REQUIRED';
        $message = $authenticated
            ? 'You do not have permission to perform this action.'
            : 'Authentication is required.';
        $details = [
            'permission' => $permission,
            'source' => 'cms_permission_flow',
            'authenticated' => $authenticated,
        ];

        if ($api) {
            return CmsApiResponse::error($code, $message, $status, $details);
        }

        $response = response($message, $status);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-CMS-Auth-State', $authenticated ? 'authenticated' : 'anonymous');

        return $response;
    }

    public static function isApiRequest(Request $request): bool
    {
        $path = $request->getPathInfo();

        return preg_match('#(?:^|/)api(?:/|$)#', $path) === 1
            || str_contains(strtolower((string) $request->headers->get('Accept', '')), 'json');
    }
}
