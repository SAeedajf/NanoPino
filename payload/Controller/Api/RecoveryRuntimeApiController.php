<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Controller\Api;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;
use Pinoox\Component\Http\JsonResponse;
use Pinoox\Component\Kernel\Controller\ApiController;
final class RecoveryRuntimeApiController extends ApiController
{
    public function restore(string $id):JsonResponse{$r=CmsRuntimeServices::recoveryApi()->restore($id,CmsRuntimeServices::actorId());return new JsonResponse($r->body,$r->status);}
    public function disableSafeMode():JsonResponse{$r=CmsRuntimeServices::recoveryApi()->disableSafeMode(CmsRuntimeServices::actorId());return new JsonResponse($r->body,$r->status);}
}
