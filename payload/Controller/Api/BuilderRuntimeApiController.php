<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Controller\Api;
use App\com_pinoox_cms\Cms\Runtime\{CmsApiResponse,CmsRuntimeServices};
use Pinoox\Component\Kernel\Controller\ApiController;
use Pinoox\Component\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
final class BuilderRuntimeApiController extends ApiController
{
 private function out($r):JsonResponse{return CmsApiResponse::fromFacade($r->status,$r->body,$r->headers);}
 public function index(Request $q):JsonResponse{return $this->out(CmsRuntimeServices::builderApi()->index($q->query->all(),CmsRuntimeServices::actorId()));}
 public function open(Request $q):JsonResponse{return $this->out(CmsRuntimeServices::builderApi()->open($this->requestPayload($q),CmsRuntimeServices::actorId()));}
 public function create(Request $q):JsonResponse{return $this->out(CmsRuntimeServices::builderApi()->create($this->requestPayload($q),CmsRuntimeServices::actorId()));}
 public function read(int $id):JsonResponse{return $this->out(CmsRuntimeServices::builderApi()->read($id,CmsRuntimeServices::actorId()));}
 public function save(Request $q,int $id):JsonResponse{return $this->out(CmsRuntimeServices::builderApi()->save($id,$this->requestPayload($q),CmsRuntimeServices::actorId()));}
 public function autosave(Request $q,int $id):JsonResponse{return $this->out(CmsRuntimeServices::builderApi()->autosave($id,$this->requestPayload($q),CmsRuntimeServices::actorId()));}
 public function publish(Request $q,int $id):JsonResponse{return $this->out(CmsRuntimeServices::builderApi()->publish($id,$this->requestPayload($q),CmsRuntimeServices::actorId()));}
 public function revisions(Request $q,int $id):JsonResponse{return $this->out(CmsRuntimeServices::builderApi()->revisions($id,max(1,min(200,(int)$q->query->get('limit',100))),CmsRuntimeServices::actorId()));}
 public function restore(Request $q,int $id,int $revisionId):JsonResponse{return $this->out(CmsRuntimeServices::builderApi()->restore($id,$revisionId,$this->requestPayload($q),CmsRuntimeServices::actorId()));}
 public function preview(Request $q):JsonResponse{return $this->out(CmsRuntimeServices::builderApi()->preview($this->requestPayload($q),CmsRuntimeServices::actorId()));}
 private function requestPayload(Request $r):array{try{$d=$r->toArray();}catch(\Throwable){$d=[];}return is_array($d)?$d:[];}
}
