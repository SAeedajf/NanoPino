<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Controller\Api;
use App\com_pinoox_cms\Cms\Runtime\{CmsApiResponse,RuntimeBindingState};
use App\com_pinoox_cms\Cms\Security\Http\CoreApiSecurityMatrix;
use App\com_pinoox_cms\Cms\Security\Posture\{SecurityPostureService,SecurityRuntimeState};
use App\com_pinoox_cms\Cms\Security\RateLimit\CoreRateLimitProfiles;
use Pinoox\Component\Kernel\Controller\ApiController;
use Pinoox\Component\Http\JsonResponse;
final class SecurityRuntimeApiController extends ApiController
{
 public function index():JsonResponse
 {
  $runtime=new SecurityRuntimeState(RuntimeBindingState::csrf(),RuntimeBindingState::rateLimits(),RuntimeBindingState::headers(),false,RuntimeBindingState::api(),false);
  return CmsApiResponse::ok([
   'posture'=>(new SecurityPostureService($runtime))->report()->toArray(),
   'runtime'=>['csrf'=>$runtime->csrfVerifierBound,'rate_limits'=>$runtime->rateLimitsRegistered,'security_headers'=>$runtime->securityHeadersBound,'ssrf_transport'=>$runtime->ssrfTransportBound,'public_api_security'=>$runtime->publicApiSecurityBound,'csp_enforced'=>$runtime->cspEnforced,'csp_mode'=>'report-only'],
   'rate_limits'=>array_map(static fn($p)=>['name'=>$p->name,'maxAttempts'=>$p->maxAttempts,'decaySeconds'=>$p->decaySeconds,'keyStrategy'=>$p->keyStrategy],CoreRateLimitProfiles::all()),
   'api_matrix'=>array_map(static fn($r)=>['scope'=>$r->scope,'rateLimit'=>$r->rateLimit,'sessionMutationRequiresCsrf'=>$r->sessionMutationRequiresCsrf,'siteScopeRequired'=>$r->siteScopeRequired],CoreApiSecurityMatrix::all()),
  ]);
 }
}
