<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Controller\Api;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationDeniedException;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Authorization\ScopeType;
use App\com_pinoox_cms\Cms\ExtensionCenter\Operation\ExtensionOperationType;
use App\com_pinoox_cms\Cms\ExtensionCenter\Operation\ExtensionOperationRecord;
use App\com_pinoox_cms\Cms\ExtensionCenter\Operation\ExtensionOperationStatus;
use App\com_pinoox_cms\Cms\UpdateHistory\FileUpdateHistoryRepository;
use App\com_pinoox_cms\Cms\UpdateHistory\UpdateHistoryRecorder;
use App\com_pinoox_cms\Cms\UpdateHistory\UpdateHistoryStatus;
use App\com_pinoox_cms\Cms\Theme\CmsThemeProfileFactory;
use App\com_pinoox_cms\Cms\Theme\Pattern\ThemePattern;
use App\com_pinoox_cms\Cms\Theme\Pattern\ThemePatternLoader;
use App\com_pinoox_cms\Cms\Theme\PinooxNativeThemeGateway;
use App\com_pinoox_cms\Cms\Theme\WordPress\WordPressThemeRelease;
use App\com_pinoox_cms\Cms\Runtime\CmsApiResponse;
use App\com_pinoox_cms\Cms\Runtime\CmsRequestPayload;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeErrorReporter;
use Pinoox\Component\Http\JsonResponse;
use Pinoox\Component\Http\Request;
use Pinoox\Component\Kernel\Controller\ApiController;
use Pinoox\Portal\Pinx;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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
                $context=$package==='com_pinoox_cms'?'site':null;
                if($package!==''&&!isset($active[$package])){
                    try{$active[$package]=$gateway->stack($package,$context)->activeName;}catch(\Throwable){}
                }
                $inspection=$service->inspect($theme,$context);
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
            $stack = $native->stack($package, $package === 'com_pinoox_cms' ? 'site' : null);
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

    /**
     * Inspect a WordPress ZIP through the bounded static conversion boundary.
     * This endpoint never installs, persists or activates the uploaded theme.
     */
    public function wordpressPreview(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return CmsApiResponse::error('THEME_FILE_REQUIRED', 'A multipart file field named "file" is required.', 422);
        }
        if (!$file->isValid()) {
            return CmsApiResponse::error('THEME_UPLOAD_FAILED', 'Uploaded theme failed HTTP validation.', 422);
        }

        try {
            $name = (string)$file->getClientOriginalName();
            if (preg_match('/\.zip$/i', $name) !== 1) {
                return CmsApiResponse::error('THEME_ARCHIVE_REQUIRED', 'WordPress theme preview requires a .zip archive.', 422);
            }
            $preview = CmsRuntimeServices::wordpressThemeImportPreview()->preview($file->getPathname());
            $intake = $preview->intake;
            $metadata = is_array($intake['metadata'] ?? null) ? $intake['metadata'] : [];
            $themeName = WordPressThemeRelease::themeName(
                isset($intake['theme_root']) ? (string)$intake['theme_root'] : null,
                $metadata['text_domain'] ?? null,
                isset($intake['source_sha256']) ? (string)$intake['source_sha256'] : null,
            );
            return CmsApiResponse::ok($preview->withUpdate(
                $this->themeUpdateProjection(
                    $themeName,
                    WordPressThemeRelease::normalizeVersion($metadata['version'] ?? null),
                    isset($intake['source_sha256']) ? (string)$intake['source_sha256'] : null,
                    isset($intake['manifest_sha256']) ? (string)$intake['manifest_sha256'] : null,
                    is_array($intake['files'] ?? null) ? $intake['files'] : [],
                    $preview->readyForReview,
                ),
            )->toArray());
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'WordPress theme preview is not permitted.', 403);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return CmsApiResponse::error('THEME_PREVIEW_FAILED', 'WordPress theme preview could not be completed.', 422, ['reason' => $this->reason($e)]);
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response($e, 'THEME_PREVIEW_FAILED', 'WordPress theme preview could not be completed.', 500, ['operation' => 'themes.wordpress.preview']);
        }
    }

    /** Convert, sign and install a reviewed WordPress archive through NanoShell. */
    public function wordpressInstall(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return CmsApiResponse::error('THEME_FILE_REQUIRED', 'A multipart file field named "file" is required.', 422);
        }
        if (!$file->isValid()) {
            return CmsApiResponse::error('THEME_UPLOAD_FAILED', 'Uploaded theme failed HTTP validation.', 422);
        }
        if ((string)$request->request->get('approved', '') !== 'true'
            || trim((string)$request->request->get('confirmation', '')) !== 'INSTALL') {
            return CmsApiResponse::error('THEME_INSTALL_APPROVAL_REQUIRED', 'Explicit INSTALL confirmation is required before a converted theme can be installed.', 409);
        }

        $package = null;
        $stage = null;
        try {
            $name = (string)$file->getClientOriginalName();
            if (preg_match('/\.zip$/i', $name) !== 1) {
                return CmsApiResponse::error('THEME_ARCHIVE_REQUIRED', 'WordPress theme installation requires a .zip archive.', 422);
            }
            $package = CmsRuntimeServices::wordpressThemeNativePackageBuilder()->build($file->getPathname());
            $size = filesize($package->path);
            if (!is_int($size) || $size < 1) throw new \RuntimeException('Converted native theme package is empty.');
            $stage = CmsRuntimeServices::extensionStaging()->stage($package->path, 'NanoShell-' . $package->manifest['theme_name'] . '.pinx', $size);
            $native = Pinx::manifest($stage->path);
            $mode = Pinx::resolveMode($native, false);
            $metadata = is_array($native->toArray()['cms']['metadata'] ?? null) ? $native->toArray()['cms']['metadata'] : [];
            $update = $this->themeUpdateProjection(
                $native->themeName(),
                $native->versionName(),
                isset($metadata['source_archive_sha256']) ? (string)$metadata['source_archive_sha256'] : null,
                isset($metadata['source_manifest_sha256']) ? (string)$metadata['source_manifest_sha256'] : null,
                is_array($metadata['source_files'] ?? null) ? $metadata['source_files'] : [],
                true,
            );
            if (($update['is_downgrade'] ?? false) === true) {
                return CmsApiResponse::error(
                    'THEME_UPDATE_BLOCKED',
                    'Theme update was blocked because the uploaded version is older than the installed version.',
                    409,
                    ['mode' => $mode, 'theme' => $native->themeName(), 'update' => $update],
                );
            }
            // Reinstalling the exact same source archive is an idempotent no-op.
            // Do not invoke the native mutator for this case: it would create a
            // needless recovery point and can reject an otherwise safe retry.
            if (($update['operation'] ?? '') === 'reinstall' && $this->sameThemeSource($update)) {
                $now = microtime(true);
                $operation = new ExtensionOperationRecord(
                    'op-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(5)),
                    ExtensionOperationType::Update,
                    'com_pinoox_cms',
                    ExtensionOperationStatus::Succeeded,
                    $now,
                    $now,
                    $now,
                );
                $operation->addStep('complete', 'ok', 'Theme is already current; no files changed.');
                $this->recordThemeUpdateHistory('com_pinoox_cms', $update, $operation);
                CmsRuntimeServices::syncInstalledExtensions();
                return CmsApiResponse::ok([
                    'operation' => $operation->publicData(),
                    'mode' => $mode,
                    'theme' => $package->manifest['theme_name'],
                    'source' => $package->source,
                    'signature' => 'local-converter-ed25519',
                    'update' => $update,
                    'no_op' => true,
                ]);
            }
            $type = $mode === 'update' ? ExtensionOperationType::Update : ExtensionOperationType::Install;
            $ticket = CmsRuntimeServices::extensionCenter()->issueReviewTicket($stage->path, $stage->displayName, $stage->size, true, CmsRuntimeServices::actorId(), [], true, $mode === 'update', false);
            $operation = CmsRuntimeServices::extensionCenter()->executePackageOperation($type, $stage->path, $stage->displayName, $stage->size, $ticket['token'], CmsRuntimeServices::actorId(), [], true, false);
            $this->recordThemeUpdateHistory('com_pinoox_cms', $update, $operation);
            $data = [
                'operation' => $operation->publicData(),
                'mode' => $mode,
                'theme' => $package->manifest['theme_name'],
                'source' => $package->source,
                'signature' => 'local-converter-ed25519',
                'update' => $update,
            ];
            if ($operation->status->value === 'succeeded') {
                CmsRuntimeServices::syncInstalledExtensions();
                return CmsApiResponse::ok($data, $mode === 'update' ? 200 : 201);
            }
            if ($operation->status->value === 'recovery_required') return CmsApiResponse::error('THEME_RECOVERY_REQUIRED', 'Theme operation requires recovery.', 409, $data);
            $failureReason = $this->safeOperationReason($operation->internalError);
            if ($failureReason !== null) $data['failure_reason'] = $failureReason;
            return CmsApiResponse::error('THEME_INSTALL_FAILED', 'Converted theme install/update failed.', 422, $data);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'WordPress theme installation is not permitted.', 403);
        } catch (\RuntimeException $e) {
            if (str_contains(strtolower($e->getMessage()), 'approval')) return CmsApiResponse::error('THEME_INSTALL_APPROVAL_REQUIRED', 'Explicit INSTALL confirmation is required.', 409);
            return CmsApiResponse::error('THEME_INSTALL_FAILED', 'WordPress theme conversion/install could not be completed.', 422, ['reason' => $this->reason($e)]);
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response($e, 'THEME_INSTALL_FAILED', 'WordPress theme conversion/install could not be completed.', 500, ['operation' => 'themes.wordpress.install']);
        } finally {
            if ($stage !== null) try { CmsRuntimeServices::extensionStaging()->remove($stage->id); } catch (\Throwable) {}
            if ($package !== null) try { CmsRuntimeServices::wordpressThemeNativePackageBuilder()->cleanup($package); } catch (\Throwable) {}
        }
    }

    public function activate(Request $request):JsonResponse
    {
        try {
            $data=$this->requestPayload($request);
            $package=trim((string)($data['package']??''));
            $theme=trim((string)($data['theme']??''));
            $context=isset($data['context'])&&$data['context']!==null?trim((string)$data['context']):null;
            if(($context===null||$context==='')&&$package==='com_pinoox_cms')$context='site';
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

    private function requestPayload(Request $r):array{return CmsRequestPayload::read($r);}

    /** Record the completed theme update without allowing observability to break the mutation result. */
    private function recordThemeUpdateHistory(string $extensionId, array $update, ExtensionOperationRecord $operation): void
    {
        if (!in_array((string)($update['operation'] ?? ''), ['update', 'reinstall'], true)) return;

        try {
            $current = is_array($update['current'] ?? null) ? $update['current'] : [];
            $incoming = is_array($update['incoming'] ?? null) ? $update['incoming'] : [];
            $status = match ($operation->status->value) {
                'succeeded' => UpdateHistoryStatus::Succeeded,
                'recovery_required' => UpdateHistoryStatus::RecoveryRequired,
                default => UpdateHistoryStatus::Failed,
            };
            (new UpdateHistoryRecorder(
                new FileUpdateHistoryRepository(CmsRuntimeServices::storageRoot() . '/updates/history.jsonl'),
            ))->record(
                $extensionId,
                (string)($current['version'] ?? ''),
                (int)($current['version_code'] ?? 0),
                (string)($incoming['version'] ?? ''),
                (int)($incoming['version_code'] ?? 0),
                $status,
                $operation->recoveryPointId,
                CmsRuntimeServices::actorId(),
                [
                    'source' => 'wordpress-theme-import',
                    'theme_name' => (string)($update['theme_name'] ?? ''),
                    'operation' => (string)($update['operation'] ?? ''),
                    'changes' => is_array($update['changes'] ?? null) ? $update['changes'] : [],
                ],
            );
        } catch (\Throwable) {
            // History is best effort; the signed native operation remains authoritative.
        }
    }

    /** @param list<array{path:string,size:int,sha256:string}> $incomingFiles @return array<string,mixed> */
    private function themeUpdateProjection(string $themeName, string $incomingVersion, ?string $incomingArchive, ?string $incomingManifest, array $incomingFiles, bool $ready): array
    {
        $current = (new PinooxNativeThemeGateway())->find('com_pinoox_cms', $themeName);
        $incomingCode = WordPressThemeRelease::versionCode($incomingVersion);
        $base = [
            'theme_name' => $themeName,
            'operation' => $current === null ? 'install' : 'update',
            'allowed' => $ready,
            'is_downgrade' => false,
            'comparison' => 'new',
            'current' => null,
            'incoming' => [
                'version' => WordPressThemeRelease::normalizeVersion($incomingVersion),
                'version_code' => $incomingCode,
                'source_archive_sha256' => $incomingArchive,
                'source_manifest_sha256' => $incomingManifest,
            ],
            'changes' => ['added' => 0, 'modified' => 0, 'removed' => 0, 'total' => 0, 'details' => []],
            'security' => [
                'signature_required' => true,
                'source_php_executed' => false,
                'snapshot_before_mutation' => true,
                'rollback_on_failure' => true,
            ],
        ];
        if ($current === null) return $base;

        $currentFiles = [];
        $conversion = rtrim($current->path, '/\\') . '/conversion.json';
        if (is_file($conversion)) {
            $decoded = json_decode((string)file_get_contents($conversion), true);
            if (is_array($decoded) && is_array($decoded['source_files'] ?? null)) $currentFiles = $decoded['source_files'];
        }
        $currentByPath = $this->themeFilesByPath($currentFiles);
        $incomingByPath = $this->themeFilesByPath($incomingFiles);
        $detailsComplete = $currentFiles !== [];
        $added = $modified = $removed = 0;
        $details = [];
        foreach ($incomingByPath as $path => $file) {
            if (!isset($currentByPath[$path])) { $added++; $details[] = ['type' => 'added', 'path' => $path]; continue; }
            if (!hash_equals((string)$currentByPath[$path]['sha256'], (string)$file['sha256'])) { $modified++; $details[] = ['type' => 'modified', 'path' => $path]; }
        }
        foreach ($currentByPath as $path => $_file) {
            if (!isset($incomingByPath[$path])) { $removed++; $details[] = ['type' => 'removed', 'path' => $path]; }
        }
        $sourceChanged = ($incomingArchive !== null && $incomingArchive !== '' && $incomingArchive !== (string)($this->conversionValue($conversion, 'source_archive_sha256'))) || $added > 0 || $modified > 0 || $removed > 0;
        if (!$detailsComplete) {
            $added = $modified = $removed = 0;
            if ($sourceChanged) $details[] = ['type' => 'modified', 'path' => '[source archive]'];
        }
        $comparison = $incomingCode <=> $current->versionCode;
        $base['operation'] = $comparison === 0 && !$sourceChanged ? 'reinstall' : 'update';
        $base['comparison'] = $comparison < 0 ? 'older' : ($comparison > 0 ? 'newer' : 'same');
        $base['is_downgrade'] = $comparison < 0;
        $base['allowed'] = $ready && !$base['is_downgrade'];
        $base['current'] = [
            'version' => $current->version,
            'version_code' => $current->versionCode,
            'source_archive_sha256' => $this->conversionValue($conversion, 'source_archive_sha256'),
            'source_manifest_sha256' => $this->conversionValue($conversion, 'source_manifest_sha256'),
        ];
        $changeTotal = $detailsComplete ? $added + $modified + $removed : ($sourceChanged ? 1 : 0);
        $base['changes'] = [
            'added' => $added,
            'modified' => $modified,
            'removed' => $removed,
            'total' => $changeTotal,
            'details' => array_slice($details, 0, 100),
            'details_truncated' => count($details) > 100,
            'details_complete' => $detailsComplete,
        ];
        return $base;
    }

    /** @param list<array{path:string,size:int,sha256:string}> $files @return array<string,array{path:string,size:int,sha256:string}> */
    private function themeFilesByPath(array $files): array
    {
        $result = [];
        foreach ($files as $file) {
            if (!is_array($file) || !isset($file['path'], $file['sha256'])) continue;
            $path = (string)$file['path'];
            if ($path !== '') $result[$path] = ['path' => $path, 'size' => (int)($file['size'] ?? 0), 'sha256' => (string)$file['sha256']];
        }
        return $result;
    }

    private function conversionValue(string $path, string $key): ?string
    {
        if (!is_file($path)) return null;
        $data = json_decode((string)file_get_contents($path), true);
        $value = is_array($data) ? ($data[$key] ?? null) : null;
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string,mixed> $update */
    private function sameThemeSource(array $update): bool
    {
        $current = is_array($update['current'] ?? null) ? $update['current'] : [];
        $incoming = is_array($update['incoming'] ?? null) ? $update['incoming'] : [];
        $currentArchive = strtolower(trim((string)($current['source_archive_sha256'] ?? '')));
        $incomingArchive = strtolower(trim((string)($incoming['source_archive_sha256'] ?? '')));
        if (preg_match('/^[a-f0-9]{64}$/', $currentArchive) !== 1
            || preg_match('/^[a-f0-9]{64}$/', $incomingArchive) !== 1
            || !hash_equals($currentArchive, $incomingArchive)) {
            return false;
        }

        $currentManifest = strtolower(trim((string)($current['source_manifest_sha256'] ?? '')));
        $incomingManifest = strtolower(trim((string)($incoming['source_manifest_sha256'] ?? '')));
        if ($currentManifest === '' || $incomingManifest === '') return true;
        return preg_match('/^[a-f0-9]{64}$/', $currentManifest) === 1
            && preg_match('/^[a-f0-9]{64}$/', $incomingManifest) === 1
            && hash_equals($currentManifest, $incomingManifest);
    }

    private function reason(\Throwable $e):string{
        $m=trim($e->getMessage());
        if($m===''||str_contains($m,'/')||str_contains($m,'\\'))return'See CMS audit/logs for internal details.';
        return function_exists('mb_substr')?mb_substr($m,0,500):substr($m,0,500);
    }

    private function safeOperationReason(?string $message): ?string
    {
        $message = trim((string)$message);
        if ($message === '') return null;
        if (str_contains($message, '/') || str_contains($message, '\\')) return 'See CMS audit/logs for internal details.';
        return function_exists('mb_substr') ? mb_substr($message, 0, 500) : substr($message, 0, 500);
    }
}
