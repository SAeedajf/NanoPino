<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Controller\Api;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationDeniedException;
use App\com_pinoox_cms\Cms\Builder\BuilderConcurrencyException;
use App\com_pinoox_cms\Cms\Builder\GlobalBlock\GlobalBlockRecord;
use App\com_pinoox_cms\Cms\Runtime\CmsApiResponse;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeErrorReporter;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;
use Pinoox\Component\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Pinoox\Component\Kernel\Controller\ApiController;

final class GlobalBlockRuntimeApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        try {
            $siteId = max(1, (int)$request->query->get('site_id', 1));
            $items = CmsRuntimeServices::globalBlocks()->list(
                $siteId,
                CmsRuntimeServices::actorId(),
            );
            return CmsApiResponse::ok([
                'items' => array_map(fn (GlobalBlockRecord $item): array => $this->row($item), $items),
                'total' => count($items),
            ]);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'Global Block access is not permitted.', 403);
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response(
                $e,
                'GLOBAL_BLOCK_LIST_FAILED',
                'Global Blocks could not be loaded.',
                500,
                ['operation' => 'builder.global_blocks.list'],
            );
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $record = CmsRuntimeServices::globalBlocks()->find(
                $id,
                CmsRuntimeServices::actorId(),
            );
            return $record
                ? CmsApiResponse::ok($this->row($record))
                : CmsApiResponse::error('GLOBAL_BLOCK_NOT_FOUND', 'Global Block not found.', 404);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'Global Block access is not permitted.', 403);
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response(
                $e,
                'GLOBAL_BLOCK_READ_FAILED',
                'Global Block could not be loaded.',
                500,
                ['operation' => 'builder.global_blocks.read'],
            );
        }
    }

    public function create(Request $request): JsonResponse
    {
        try {
            $data = $this->payload($request);
            $record = CmsRuntimeServices::globalBlocks()->create(
                max(1, (int)($data['site_id'] ?? 1)),
                trim((string)($data['name'] ?? '')),
                is_array($data['document'] ?? null) ? $data['document'] : [],
                CmsRuntimeServices::actorId(),
            );
            return CmsApiResponse::ok($this->row($record), 201);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'Global Block creation is not permitted.', 403);
        } catch (\InvalidArgumentException $e) {
            return CmsApiResponse::error('GLOBAL_BLOCK_INVALID', $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response(
                $e,
                'GLOBAL_BLOCK_CREATE_FAILED',
                'Global Block could not be created.',
                500,
                ['operation' => 'builder.global_blocks.create'],
            );
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $data = $this->payload($request);
            $expected = (int)($data['expected_version'] ?? 0);
            if ($expected < 1) {
                return CmsApiResponse::error(
                    'GLOBAL_BLOCK_VERSION_REQUIRED',
                    'expected_version is required.',
                    422,
                );
            }
            $record = CmsRuntimeServices::globalBlocks()->update(
                $id,
                trim((string)($data['name'] ?? '')),
                is_array($data['document'] ?? null) ? $data['document'] : [],
                $expected,
                CmsRuntimeServices::actorId(),
            );
            return CmsApiResponse::ok($this->row($record));
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'Global Block update is not permitted.', 403);
        } catch (BuilderConcurrencyException) {
            return CmsApiResponse::error(
                'GLOBAL_BLOCK_VERSION_CONFLICT',
                'Global Block was modified by another request.',
                409,
            );
        } catch (\InvalidArgumentException $e) {
            return CmsApiResponse::error('GLOBAL_BLOCK_INVALID', $e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            if (str_contains(strtolower($e->getMessage()), 'not found')) {
                return CmsApiResponse::error('GLOBAL_BLOCK_NOT_FOUND', 'Global Block not found.', 404);
            }
            throw $e;
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response(
                $e,
                'GLOBAL_BLOCK_UPDATE_FAILED',
                'Global Block could not be updated.',
                500,
                ['operation' => 'builder.global_blocks.update'],
            );
        }
    }

    private function row(GlobalBlockRecord $record): array
    {
        return [
            'id' => $record->id,
            'site_id' => $record->siteId,
            'name' => $record->name,
            'document' => $record->document->toArray(),
            'checksum' => $record->checksum,
            'version' => $record->version,
            'actor_id' => $record->actorId,
            'created_at' => $record->createdAt,
            'updated_at' => $record->updatedAt,
        ];
    }

    private function payload(Request $request): array
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            $data = [];
        }
        return is_array($data) ? $data : [];
    }
}
