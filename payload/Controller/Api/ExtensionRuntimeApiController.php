<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Controller\Api;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationDeniedException;
use App\com_pinoox_cms\Cms\ExtensionCenter\Operation\ExtensionOperationType;
use App\com_pinoox_cms\Cms\Recovery\SafeModeManager;
use App\com_pinoox_cms\Cms\Runtime\CmsApiResponse;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeErrorReporter;
use Pinoox\Component\Http\JsonResponse;
use Pinoox\Component\Http\Request;
use Pinoox\Component\Kernel\Controller\ApiController;
use Pinoox\Portal\Pinx;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ExtensionRuntimeApiController extends ApiController
{
    public function index():JsonResponse
    {
        try {
            $items=CmsRuntimeServices::extensionCenter()->list(
                CmsRuntimeServices::actorId(),
                (new SafeModeManager(CmsRuntimeServices::storageRoot().'/recovery/safe-mode.json'))->state(),
            );
            return CmsApiResponse::ok(['items'=>$items,'api_bound'=>true]);
        } catch(AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN','Extension Center access is not permitted.',403);
        } catch(\Throwable $e) {
            return CmsRuntimeErrorReporter::response($e,'EXTENSION_LIST_FAILED','Installed extensions could not be loaded.',500,['operation'=>'extensions.list']);
        }
    }

    public function inspect(Request $request):JsonResponse
    {
        $file=$request->files->get('file');
        if(!$file instanceof UploadedFile)return CmsApiResponse::error('EXTENSION_FILE_REQUIRED','A multipart file field named "file" is required.',422);
        if(!$file->isValid())return CmsApiResponse::error('EXTENSION_UPLOAD_FAILED','Uploaded package failed HTTP validation.',422);

        try {
            $stage=CmsRuntimeServices::extensionStaging()->stage(
                $file->getPathname(),$file->getClientOriginalName(),(int)$file->getSize()
            );
            $native=Pinx::manifest($stage->path);
            $mode=Pinx::resolveMode($native,false);
            $projection=CmsRuntimeServices::extensionCenter()->inspectAndReview(
                $stage->path,$stage->displayName,$stage->size,CmsRuntimeServices::actorId(),
                [],false,$mode==='update',false,
            );
            return CmsApiResponse::ok(['stage'=>$stage->publicData(),'mode'=>$mode,'review'=>$projection->toArray()]);
        } catch(AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN','Extension inspection is not permitted.',403);
        } catch(\Throwable $e) {
            return CmsApiResponse::error('EXTENSION_INSPECTION_FAILED','Extension package inspection failed.',422,['reason'=>$this->reason($e)]);
        }
    }

    public function reviewTicket(Request $request):JsonResponse
    {
        try {
            $payload=$this->requestPayload($request);
            $stage=CmsRuntimeServices::extensionStaging()->get(trim((string)($payload['stage_id']??'')));
            $native=Pinx::manifest($stage->path);
            $mode=Pinx::resolveMode($native,false);
            $ticket=CmsRuntimeServices::extensionCenter()->issueReviewTicket(
                $stage->path,$stage->displayName,$stage->size,(bool)($payload['approved']??false),
                CmsRuntimeServices::actorId(),[],false,$mode==='update',false,
            );
            return CmsApiResponse::ok([
                'stage'=>$stage->publicData(),'mode'=>$mode,'token'=>$ticket['token'],
                'expires_at'=>$ticket['expires_at'],'review'=>$ticket['review'],
            ],201);
        } catch(AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN','Extension review ticket is not permitted.',403);
        } catch(\RuntimeException $e) {
            if(str_contains(strtolower($e->getMessage()),'approval')){
                return CmsApiResponse::error('EXTENSION_APPROVAL_REQUIRED','Explicit approval is required before execution.',409);
            }
            return CmsApiResponse::error('EXTENSION_REVIEW_FAILED','Extension review ticket could not be issued.',422,['reason'=>$this->reason($e)]);
        } catch(\Throwable $e) {
            return CmsRuntimeErrorReporter::response($e,'EXTENSION_REVIEW_FAILED','Extension review ticket could not be issued.',500,['operation'=>'extensions.review']);
        }
    }

    public function install(Request $request):JsonResponse
    {
        try {
            $payload=$this->requestPayload($request);
            $stage=CmsRuntimeServices::extensionStaging()->get(trim((string)($payload['stage_id']??'')));
            $native=Pinx::manifest($stage->path);
            $mode=Pinx::resolveMode($native,false);
            $type=$mode==='update'?ExtensionOperationType::Update:ExtensionOperationType::Install;

            $ticket=CmsRuntimeServices::extensionCenter()->issueReviewTicket(
                $stage->path,$stage->displayName,$stage->size,(bool)($payload['approved']??false),
                CmsRuntimeServices::actorId(),[],false,$mode==='update',false,
            );
            $operation=CmsRuntimeServices::extensionCenter()->executePackageOperation(
                $type,$stage->path,$stage->displayName,$stage->size,$ticket['token'],
                CmsRuntimeServices::actorId(),[],false,false,
            );

            if($operation->status->value==='succeeded'){
                CmsRuntimeServices::extensionStaging()->remove($stage->id);
                CmsRuntimeServices::syncInstalledExtensions();
                return CmsApiResponse::ok(['operation'=>$operation->publicData(),'mode'=>$mode]);
            }
            if($operation->status->value==='recovery_required'){
                return CmsApiResponse::error('EXTENSION_RECOVERY_REQUIRED','Extension operation requires recovery.',409,['operation'=>$operation->publicData(),'mode'=>$mode]);
            }
            return CmsApiResponse::error('EXTENSION_OPERATION_FAILED','Extension install/update failed.',422,['operation'=>$operation->publicData(),'mode'=>$mode]);
        } catch(AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN','Extension install/update is not permitted.',403);
        } catch(\RuntimeException $e) {
            if(str_contains(strtolower($e->getMessage()),'approval')){
                return CmsApiResponse::error('EXTENSION_APPROVAL_REQUIRED','Explicit approval is required.',409);
            }
            return CmsApiResponse::error('EXTENSION_OPERATION_FAILED','Extension install/update could not be completed.',422,['reason'=>$this->reason($e)]);
        } catch(\Throwable $e) {
            return CmsApiResponse::error('EXTENSION_OPERATION_FAILED','Extension install/update could not be completed.',500,['reason'=>$this->reason($e)]);
        }
    }

    public function update(Request $request,string $id):JsonResponse
    {
        try {
            if(preg_match('/^com_[a-z0-9][a-z0-9_]{1,126}$/',$id)!==1){
                return CmsApiResponse::error('EXTENSION_ID_INVALID','Invalid extension id.',422);
            }
            $payload=$this->requestPayload($request);
            $stage=CmsRuntimeServices::extensionStaging()->get(trim((string)($payload['stage_id']??'')));
            $native=Pinx::manifest($stage->path);
            if(Pinx::resolveMode($native,false)!=='update'){
                return CmsApiResponse::error('EXTENSION_NOT_INSTALLED','Use the install endpoint for a new extension.',409);
            }

            $projection=CmsRuntimeServices::extensionCenter()->inspectAndReview(
                $stage->path,$stage->displayName,$stage->size,CmsRuntimeServices::actorId(),
                [],false,true,false,
            );
            if($projection->inspection->manifest->identifier()!==$id){
                return CmsApiResponse::error('EXTENSION_ID_MISMATCH','Uploaded update package belongs to another extension.',409);
            }

            $ticket=CmsRuntimeServices::extensionCenter()->issueReviewTicket(
                $stage->path,$stage->displayName,$stage->size,(bool)($payload['approved']??false),
                CmsRuntimeServices::actorId(),[],false,true,false,
            );
            $op=CmsRuntimeServices::extensionCenter()->executePackageOperation(
                ExtensionOperationType::Update,$stage->path,$stage->displayName,$stage->size,$ticket['token'],
                CmsRuntimeServices::actorId(),[],false,false,
            );

            if($op->status->value==='succeeded'){
                CmsRuntimeServices::extensionStaging()->remove($stage->id);
                CmsRuntimeServices::syncInstalledExtensions();
                return CmsApiResponse::ok(['operation'=>$op->publicData(),'mode'=>'update']);
            }
            if($op->status->value==='recovery_required'){
                return CmsApiResponse::error('EXTENSION_RECOVERY_REQUIRED','Extension update requires recovery.',409,['operation'=>$op->publicData()]);
            }
            return CmsApiResponse::error('EXTENSION_OPERATION_FAILED','Extension update failed.',422,['operation'=>$op->publicData()]);
        } catch(AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN','Extension update is not permitted.',403);
        } catch(\RuntimeException $e) {
            if(str_contains(strtolower($e->getMessage()),'approval')){
                return CmsApiResponse::error('EXTENSION_APPROVAL_REQUIRED','Explicit approval is required.',409);
            }
            return CmsApiResponse::error('EXTENSION_UPDATE_FAILED','Extension update could not be completed.',422,['reason'=>$this->reason($e)]);
        } catch(\Throwable $e) {
            return CmsRuntimeErrorReporter::response($e,'EXTENSION_UPDATE_FAILED','Extension update could not be completed.',500,['operation'=>'extensions.update']);
        }
    }

    public function activate(string $id):JsonResponse{return $this->installed(ExtensionOperationType::Activate,$id);}
    public function deactivate(string $id):JsonResponse{return $this->installed(ExtensionOperationType::Deactivate,$id);}
    public function repair(string $id):JsonResponse{return $this->installed(ExtensionOperationType::Repair,$id);}
    public function uninstall(string $id):JsonResponse{return $this->installed(ExtensionOperationType::Uninstall,$id);}

    public function rollback(Request $request,string $id):JsonResponse
    {
        $data=$this->requestPayload($request);
        return $this->installed(ExtensionOperationType::Rollback,$id,trim((string)($data['recovery_point_id']??'')));
    }

    private function installed(ExtensionOperationType $type,string $id,?string $recoveryId=null):JsonResponse
    {
        try {
            if(preg_match('/^com_[a-z0-9][a-z0-9_]{1,126}$/',$id)!==1)return CmsApiResponse::error('EXTENSION_ID_INVALID','Invalid extension id.',422);
            CmsRuntimeServices::syncInstalledExtensions();
            $op=CmsRuntimeServices::extensionCenter()->executeInstalledOperation($type,$id,$recoveryId,CmsRuntimeServices::actorId());
            if($op->status->value==='succeeded')return CmsApiResponse::ok(['operation'=>$op->publicData()]);
            if($op->status->value==='recovery_required')return CmsApiResponse::error('EXTENSION_RECOVERY_REQUIRED','Extension operation requires recovery.',409,['operation'=>$op->publicData()]);
            return CmsApiResponse::error('EXTENSION_OPERATION_FAILED','Extension operation failed.',422,['operation'=>$op->publicData()]);
        } catch(AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN','Extension operation is not permitted.',403);
        } catch(\Throwable $e) {
            return CmsApiResponse::error('EXTENSION_OPERATION_FAILED','Extension operation could not be completed.',422,['reason'=>$this->reason($e)]);
        }
    }

    private function requestPayload(Request $r):array{try{$d=$r->toArray();}catch(\Throwable){$d=[];}return is_array($d)?$d:[];}
    private function reason(\Throwable $e):string{
        $m=trim($e->getMessage());
        if($m===''||str_contains($m,'/')||str_contains($m,'\\'))return'See CMS operation journal for internal details.';
        return function_exists('mb_substr')?mb_substr($m,0,500):substr($m,0,500);
    }
}
