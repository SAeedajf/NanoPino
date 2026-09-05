<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Security\Http;
use Closure;
use Pinoox\Component\Flow\Flow;
use Pinoox\Component\Http\Request;
use Pinoox\Portal\Auth;
use Symfony\Component\HttpFoundation\JsonResponse;
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
            return new JsonResponse(['success'=>false,'error'=>['code'=>'REQUEST_INTEGRITY_DENIED','message'=>'Request integrity validation failed.','details'=>['reason'=>$decision->reason]]],$decision->reason==='AUTH_REQUIRED'?401:403);
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
