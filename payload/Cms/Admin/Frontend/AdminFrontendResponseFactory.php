<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin\Frontend;

use App\com_pinoox_cms\Cms\Support\CmsRelease;
use Pinoox\Portal\View;
use Symfony\Component\HttpFoundation\Response;

final class AdminFrontendResponseFactory
{
    public function failure(AdminFrontendAssetStatus $status):Response
    {
        $response=View::response('main',[
            'adminFrontend'=>$status->toArray(),
            'release'=>[
                'version'=>CmsRelease::version(),
                'versionCode'=>CmsRelease::versionCode(),
            ],
            'bootstrap'=>[],
        ],'text/html','UTF-8');

        $response->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE);
        $response->headers->set('Retry-After','60');

        return $this->decorate($response,$status);
    }

    public function decorate(Response $response,AdminFrontendAssetStatus $status):Response
    {
        $response->headers->set(
            'X-Pinoox-CMS-Admin-Frontend',
            $status->ready ? $status->mode : $status->code,
        );
        $response->headers->set('X-Pinoox-CMS-Version',CmsRelease::version());
        $response->headers->set('X-Robots-Tag','noindex, nofollow');
        $response->headers->set('Cache-Control','no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma','no-cache');

        if($status->buildId!==null){
            $response->headers->set('X-Pinoox-CMS-Frontend-Build',$status->buildId);
        }

        return $response;
    }
}
