<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Security;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationDeniedException;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Security\Posture\SecurityPostureService;
use Throwable;

final readonly class SecurityApiFacade
{
    public function __construct(
        private AuthorizationManager $authorization,
        private SecurityPostureService $posture,
    ) {}

    public function status(?int $actorId=null): SecurityApiResponse
    {
        try {
            $this->authorization->authorize(new AuthorizationRequest('system.security.view',$actorId));
            return new SecurityApiResponse(200,['data'=>$this->posture->report()->toArray()]);
        } catch (AuthorizationDeniedException) {
            return new SecurityApiResponse(403,[
                'error'=>['code'=>'security.forbidden','message'=>'Security posture is not permitted.','details'=>[]],
            ]);
        } catch (Throwable) {
            return new SecurityApiResponse(500,[
                'error'=>['code'=>'security.internal_error','message'=>'Internal security posture error.','details'=>[]],
            ]);
        }
    }
}
