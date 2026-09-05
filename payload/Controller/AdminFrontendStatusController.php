<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Controller;

use App\com_pinoox_cms\Cms\Admin\Frontend\AdminFrontendAssetProbe;
use App\com_pinoox_cms\Cms\Support\CmsRelease;
use Pinoox\Component\Kernel\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class AdminFrontendStatusController extends Controller
{
    public function index():JsonResponse
    {
        $status=(new AdminFrontendAssetProbe())->probe(
            dirname(__DIR__).'/theme/cms-admin'
        );

        $response=new JsonResponse([
            'ok'=>$status->ready,
            'release'=>[
                'version'=>CmsRelease::version(),
                'versionCode'=>CmsRelease::versionCode(),
                'minimumKernelCode'=>CmsRelease::minKernelCode(),
            ],
            'frontend'=>$status->toArray(),
        ],$status->ready ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);

        $response->headers->set('Cache-Control','no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma','no-cache');
        $response->headers->set('X-Robots-Tag','noindex, nofollow');
        $response->headers->set(
            'X-Pinoox-CMS-Admin-Frontend',
            $status->ready ? $status->mode : $status->code,
        );

        if(!$status->ready){
            $response->headers->set('Retry-After','60');
        }
        if($status->buildId!==null){
            $response->headers->set('X-Pinoox-CMS-Frontend-Build',$status->buildId);
        }

        return $response;
    }
}
