<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Controller\Api;
use App\com_pinoox_cms\Cms\Runtime\{CmsApiResponse,CmsRuntimeServices,RuntimeBindingState};
use App\com_pinoox_cms\Cms\Security\Http\CoreApiSecurityMatrix;
use App\com_pinoox_cms\Cms\Security\Access\PlatformSuperTransitionReadiness;
use App\com_pinoox_cms\Cms\Security\Posture\{SecurityPostureService,SecurityRuntimeState};
use App\com_pinoox_cms\Cms\Security\RateLimit\CoreRateLimitProfiles;
use Pinoox\Component\Kernel\Controller\ApiController;
use Pinoox\Component\Http\JsonResponse;
final class SecurityRuntimeApiController extends ApiController
{
 public function index():JsonResponse
 {
  CmsRuntimeServices::searchDriver();
  $platformSuper=(new PlatformSuperTransitionReadiness())->inspect();
  $runtime=new SecurityRuntimeState(
   RuntimeBindingState::csrf(),
   RuntimeBindingState::rateLimits(),
   RuntimeBindingState::headers(),
   RuntimeBindingState::ssrf(),
   RuntimeBindingState::api(),
   RuntimeBindingState::csp(),
   (bool)$platformSuper['platform_super'],
   (bool)$platformSuper['ready'],
  );
  return CmsApiResponse::ok([
   'posture'=>(new SecurityPostureService($runtime))->report()->toArray(),
   'runtime'=>['csrf'=>$runtime->csrfVerifierBound,'rate_limits'=>$runtime->rateLimitsRegistered,'security_headers'=>$runtime->securityHeadersBound,'ssrf_transport'=>$runtime->ssrfTransportBound,'public_api_security'=>$runtime->publicApiSecurityBound,'csp_enforced'=>$runtime->cspEnforced,'csp_mode'=>$runtime->cspEnforced?'enforce':'report-only'],
   'platform_super_transition'=>$platformSuper,
   'rate_limits'=>array_map(static fn($p)=>['name'=>$p->name,'maxAttempts'=>$p->maxAttempts,'decaySeconds'=>$p->decaySeconds,'keyStrategy'=>$p->keyStrategy],CoreRateLimitProfiles::all()),
   'api_matrix'=>array_map(static fn($r)=>['scope'=>$r->scope,'rateLimit'=>$r->rateLimit,'sessionMutationRequiresCsrf'=>$r->sessionMutationRequiresCsrf,'siteScopeRequired'=>$r->siteScopeRequired],CoreApiSecurityMatrix::all()),
  ]);
 }
}
