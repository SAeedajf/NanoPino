<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Security\Http;
use Closure;
use App\com_pinoox_cms\Cms\Logging\LogLevel;
use App\com_pinoox_cms\Cms\Runtime\CmsApiResponse;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;
use Pinoox\Component\Flow\Flow;
use Pinoox\Component\Http\Request;
use Pinoox\Portal\Auth;
final class CmsRequestIntegrityFlow extends Flow
{
    protected function handle(Request $request,Closure $next)
    {
        Auth::boot();$subjectId=Auth::id();
        $context=new RequestSecurityContext(
            $request->getMethod(),
            $subjectId!==null?AuthMechanism::Session:AuthMechanism::Anonymous,
            $subjectId,
            $request->headers->get('X-CSRF-TOKEN'),
            $this->sameOrigin($request),
            $request->headers->get('Content-Type'),
        );
        $decision=(new RequestIntegrityPolicy(new PinooxSessionCsrfTokenManager()))->decide($context);
        if(!$decision->allowed){
            // Keep the client-facing response intentionally generic, but record
            // the non-secret decision inputs needed to diagnose mounted/proxy
            // failures. Never persist the token, Origin, or Referer values.
            try {
                CmsRuntimeServices::logger()->log(
                    LogLevel::Warning,
                    'REQUEST_INTEGRITY_DENIED: CMS mutation was rejected.',
                    [
                        'reason' => $decision->reason,
                        'method' => $context->method(),
                        'path' => $request->getPathInfo(),
                        'subject_id' => $subjectId,
                        'auth' => $context->auth->value,
                        'same_origin' => $context->sameOrigin,
                        'has_csrf_token' => is_string($context->csrfToken) && $context->csrfToken !== '',
                        'origin_present' => trim((string) $request->headers->get('Origin', '')) !== '',
                        'fetch_site' => strtolower(trim((string) $request->headers->get('Sec-Fetch-Site', ''))),
                        'referer_present' => trim((string) $request->headers->get('Referer', '')) !== '',
                        'content_type' => strtolower(trim(explode(';', (string) $context->contentType, 2)[0])),
                    ],
                    null,
                    'cms.security',
                );
            } catch (\Throwable) {
                // Diagnostics must never alter the security decision or response.
            }
            return CmsApiResponse::error(
                'REQUEST_INTEGRITY_DENIED',
                'Request integrity validation failed.',
                $decision->reason==='AUTH_REQUIRED'?401:403,
                ['reason'=>$decision->reason],
            );
        }
        if($subjectId!==null)$request->attributes->set('cms_subject_id',$subjectId);
        return $next($request);
    }
    private function sameOrigin(Request $request):bool
    {
        $expected=rtrim(strtolower($request->getSchemeAndHttpHost()),'/');
        $origin=trim(strtolower((string)$request->headers->get('Origin','')));
        if($origin!=='')return rtrim($origin,'/')===$expected;
        $fetchSite=strtolower((string)$request->headers->get('Sec-Fetch-Site',''));
        if($fetchSite==='same-origin')return true;
        $referer=trim(strtolower((string)$request->headers->get('Referer','')));
        return $referer!==''&&($referer===$expected||str_starts_with($referer,$expected.'/'));
    }
}
