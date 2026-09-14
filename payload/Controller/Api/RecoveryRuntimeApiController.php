<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Controller\Api;
use App\com_pinoox_cms\Cms\Runtime\{CmsApiControllerResponder,CmsRuntimeServices};
use Pinoox\Component\Http\JsonResponse;
use Pinoox\Component\Kernel\Controller\ApiController;
final class RecoveryRuntimeApiController extends ApiController
{
    use CmsApiControllerResponder;

    public function restore(string $id): JsonResponse
    {
        return $this->facadeResponse(
            fn () => CmsRuntimeServices::recoveryApi()->restore($id, CmsRuntimeServices::actorId()),
            'RECOVERY_RESTORE_FAILED',
            'Recovery point could not be restored.',
            'recovery.restore',
        );
    }

    public function disableSafeMode(): JsonResponse
    {
        return $this->facadeResponse(
            fn () => CmsRuntimeServices::recoveryApi()->disableSafeMode(CmsRuntimeServices::actorId()),
            'SAFE_MODE_DISABLE_FAILED',
            'Safe Mode could not be disabled.',
            'recovery.safe_mode.disable',
        );
    }
}
