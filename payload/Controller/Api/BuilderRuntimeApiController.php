<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Controller\Api;
use App\com_pinoox_cms\Cms\Runtime\{CmsApiControllerResponder,CmsRequestPayload,CmsRuntimeServices};
use Pinoox\Component\Kernel\Controller\ApiController;
use Pinoox\Component\Http\JsonResponse;
use Pinoox\Component\Http\Request;
final class BuilderRuntimeApiController extends ApiController
{
 use CmsApiControllerResponder;
 public function index(Request $request):JsonResponse{return $this->facadeResponse(fn()=>CmsRuntimeServices::builderApi()->index($request->query->all(),CmsRuntimeServices::actorId()),'BUILDER_INDEX_FAILED','Builder documents could not be loaded.','builder.index');}
 public function open(Request $request):JsonResponse{return $this->facadeResponse(fn()=>CmsRuntimeServices::builderApi()->open($this->requestPayload($request),CmsRuntimeServices::actorId()),'BUILDER_OPEN_FAILED','The builder document could not be opened.','builder.open');}
 public function create(Request $request):JsonResponse{return $this->facadeResponse(fn()=>CmsRuntimeServices::builderApi()->create($this->requestPayload($request),CmsRuntimeServices::actorId()),'BUILDER_CREATE_FAILED','The builder document could not be created.','builder.create');}
 public function read(int $id):JsonResponse{return $this->facadeResponse(fn()=>CmsRuntimeServices::builderApi()->read($id,CmsRuntimeServices::actorId()),'BUILDER_READ_FAILED','The builder document could not be loaded.','builder.read');}
 public function save(Request $request,int $id):JsonResponse{return $this->facadeResponse(fn()=>CmsRuntimeServices::builderApi()->save($id,$this->requestPayload($request),CmsRuntimeServices::actorId()),'BUILDER_SAVE_FAILED','The builder document could not be saved.','builder.save');}
 public function autosave(Request $request,int $id):JsonResponse{return $this->facadeResponse(fn()=>CmsRuntimeServices::builderApi()->autosave($id,$this->requestPayload($request),CmsRuntimeServices::actorId()),'BUILDER_AUTOSAVE_FAILED','The builder document could not be autosaved.','builder.autosave');}
 public function publish(Request $request,int $id):JsonResponse{return $this->facadeResponse(fn()=>CmsRuntimeServices::builderApi()->publish($id,$this->requestPayload($request),CmsRuntimeServices::actorId()),'BUILDER_PUBLISH_FAILED','The builder document could not be published.','builder.publish');}
 public function revisions(Request $request,int $id):JsonResponse{return $this->facadeResponse(fn()=>CmsRuntimeServices::builderApi()->revisions($id,max(1,min(200,(int)$request->query->get('limit',100))),CmsRuntimeServices::actorId()),'BUILDER_REVISIONS_FAILED','Builder revisions could not be loaded.','builder.revisions');}
 public function restore(Request $request,int $id,int $revisionId):JsonResponse{return $this->facadeResponse(fn()=>CmsRuntimeServices::builderApi()->restore($id,$revisionId,$this->requestPayload($request),CmsRuntimeServices::actorId()),'BUILDER_RESTORE_FAILED','The builder revision could not be restored.','builder.restore');}
 public function preview(Request $request):JsonResponse{return $this->facadeResponse(fn()=>CmsRuntimeServices::builderApi()->preview($this->requestPayload($request),CmsRuntimeServices::actorId()),'BUILDER_PREVIEW_FAILED','The builder preview could not be generated.','builder.preview');}
 private function requestPayload(Request $r):array{return CmsRequestPayload::read($r);}
}
