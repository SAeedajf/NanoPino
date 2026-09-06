<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Controller\Api;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationDeniedException;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Authorization\ScopeType;
use App\com_pinoox_cms\Cms\Theme\CmsThemeProfileFactory;
use App\com_pinoox_cms\Cms\Theme\Pattern\ThemePattern;
use App\com_pinoox_cms\Cms\Theme\Pattern\ThemePatternLoader;
use App\com_pinoox_cms\Cms\Theme\PinooxNativeThemeGateway;
use App\com_pinoox_cms\Cms\Runtime\CmsApiResponse;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeErrorReporter;
use Pinoox\Component\Http\JsonResponse;
use Pinoox\Component\Http\Request;
use Pinoox\Component\Kernel\Controller\ApiController;

final class ThemeRuntimeApiController extends ApiController
{
    public function index():JsonResponse
    {
        try {
            $service=CmsRuntimeServices::themeService();
            $definitions=$service->list(1,CmsRuntimeServices::actorId());
            $gateway=new \App\com_pinoox_cms\Cms\Theme\PinooxNativeThemeGateway();
            $active=[];
            $items=[];
            foreach($definitions as $theme){
                $item=$theme->toArray();
                $package=(string)($item['package']??'');
                if($package!==''&&!isset($active[$package])){
                    try{$active[$package]=$gateway->stack($package)->activeName;}catch(\Throwable){}
                }
                $inspection=$service->inspect($theme);
                $item['compatibility']=$inspection['compatibility'];
                $item['inheritance']=$inspection['inheritance'];
                $item['active']=($package!==''&&($active[$package]??null)===(string)($item['name']??''));
                $items[]=$item;
            }
            $compatible=count(array_filter($items,static fn(array $item):bool=>(bool)($item['compatibility']['compatible']??false)&&(bool)($item['inheritance']['valid']??false)));
            return CmsApiResponse::ok([
                'items'=>$items,
                'active'=>$active,
                'summary'=>['total'=>count($items),'compatible'=>$compatible,'blocked'=>count($items)-$compatible,'active'=>count(array_filter($items,static fn(array $item):bool=>(bool)($item['active']??false)))],
                'api_bound'=>true,
            ]);
        } catch(AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN','Theme access is not permitted.',403);
        } catch(\Throwable $e) {
            return CmsRuntimeErrorReporter::response($e,'THEME_LIST_FAILED','Themes could not be loaded.',500,['operation'=>'themes.list']);
        }
    }

    public function patterns(string $package, string $theme): JsonResponse
    {
        try {
            CmsRuntimeServices::authorization()->authorize(new AuthorizationRequest(
                'themes.read',
                CmsRuntimeServices::actorId(),
                ScopeType::Site,
                1,
                'theme',
                $package . ':' . $theme,
            ));

            CmsRuntimeServices::discoverThemes();
            $definition = CmsRuntimeServices::kernel()->themes->byReference($package, $theme);
            if ($definition === null) {
                return CmsApiResponse::error('THEME_NOT_FOUND', 'Theme not found.', 404);
            }

            $native = new PinooxNativeThemeGateway();
            $stack = $native->stack($package);
            $profile = (new CmsThemeProfileFactory())->fromNativeMeta($definition->raw);
            $patterns = (new ThemePatternLoader())->discover(
                $stack->paths,
                $profile->patternDirectory,
            );

            return CmsApiResponse::ok([
                'items' => array_values(array_map(
                    static fn (ThemePattern $pattern): array => [
                        'id' => $pattern->id,
                        'title' => $pattern->title,
                        'categories' => $pattern->categories,
                        'document' => $pattern->document,
                    ],
                    $patterns,
                )),
                'total' => count($patterns),
                'theme' => ['package' => $package, 'name' => $theme],
            ]);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'Theme patterns are not permitted.', 403);
        } catch (\InvalidArgumentException $e) {
            return CmsApiResponse::error('THEME_PATTERN_INVALID', $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response(
                $e,
                'THEME_PATTERN_LIST_FAILED',
                'Theme patterns could not be loaded.',
                500,
                ['operation' => 'themes.patterns'],
            );
        }
    }

    public function activate(Request $request):JsonResponse
    {
        try {
            $data=$this->requestPayload($request);
            $package=trim((string)($data['package']??''));
            $theme=trim((string)($data['theme']??''));
            $context=isset($data['context'])&&$data['context']!==null?trim((string)$data['context']):null;
            if(
                preg_match('/^[a-z0-9][a-z0-9._-]{1,127}$/',$package)!==1
                ||preg_match('/^[a-z0-9][a-z0-9._-]{0,127}$/',$theme)!==1
            ) return CmsApiResponse::error('THEME_REFERENCE_INVALID','Invalid theme activation reference.',422);

            $definition=CmsRuntimeServices::themeService()->activate(1,$package,$theme,$context===''?null:$context,CmsRuntimeServices::actorId());
            return CmsApiResponse::ok(['theme'=>$definition->toArray(),'active'=>['package'=>$package,'theme'=>$theme,'context'=>$context]]);
        } catch(AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN','Theme activation is not permitted.',403);
        } catch(\RuntimeException $e) {
            return CmsApiResponse::error('THEME_ACTIVATION_FAILED','Theme activation could not be completed.',422,['reason'=>$this->reason($e)]);
        } catch(\Throwable $e) {
            return CmsRuntimeErrorReporter::response($e,'THEME_ACTIVATION_FAILED','Theme activation could not be completed.',500,['operation'=>'themes.activate']);
        }
    }

    private function requestPayload(Request $r):array{try{$d=$r->toArray();}catch(\Throwable){$d=[];}return is_array($d)?$d:[];}
    private function reason(\Throwable $e):string{
        $m=trim($e->getMessage());
        if($m===''||str_contains($m,'/')||str_contains($m,'\\'))return'See CMS audit/logs for internal details.';
        return function_exists('mb_substr')?mb_substr($m,0,500):substr($m,0,500);
    }
}
