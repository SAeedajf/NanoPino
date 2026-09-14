<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Runtime;

use App\com_pinoox_cms\Cms\Logging\CorrelationId;
use Pinoox\Component\Http\JsonResponse;
final class CmsApiResponse
{
    public static function ok(array $data=[],int $status=200,array $headers=[]):JsonResponse
    { $r=new JsonResponse(['success'=>true,'data'=>$data],$status,$headers);$r->headers->set('Cache-Control','no-store');return$r; }
    public static function error(string $code,string $message,int $status=400,array $details=[]):JsonResponse
    { $r=new JsonResponse(['success'=>false,'error'=>['code'=>$code,'message'=>$message,'details'=>$details]],$status);self::decorateError($r);return$r; }
    public static function fromFacade(int $status,array $body,array $headers=[]):JsonResponse
    { $success=$status>=200&&$status<400;$payload=$success?(['success'=>true]+$body):(['success'=>false]+$body);$r=new JsonResponse($payload,$status,$headers);if($success)$r->headers->set('Cache-Control','no-store');else self::decorateError($r);return$r; }

    private static function decorateError(JsonResponse $response): void
    {
        $response->headers->set('Cache-Control','no-store');
        if (!$response->headers->has('X-Correlation-ID')) {
            $response->headers->set('X-Correlation-ID', CorrelationId::generate()->value);
        }
    }
}
