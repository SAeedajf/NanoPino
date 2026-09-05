<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Extension;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationDeniedException;
use App\com_pinoox_cms\Cms\ExtensionCenter\ExtensionCenterService;
use App\com_pinoox_cms\Cms\ExtensionCenter\Operation\ExtensionOperationConflictException;
use App\com_pinoox_cms\Cms\ExtensionCenter\Operation\ExtensionOperationRecord;
use App\com_pinoox_cms\Cms\ExtensionCenter\Operation\ExtensionOperationStatus;
use App\com_pinoox_cms\Cms\ExtensionCenter\Operation\ExtensionOperationType;
use App\com_pinoox_cms\Cms\Recovery\SafeModeState;
use InvalidArgumentException;
use Throwable;

final readonly class ExtensionApiFacade
{
    public function __construct(private ExtensionCenterService $center) {}

    public function list(?int $actorId = null, ?SafeModeState $safeMode = null): ExtensionApiResponse
    {
        return $this->guard(fn (): ExtensionApiResponse =>
            new ExtensionApiResponse(200, ['data' => $this->center->list($actorId, $safeMode)])
        );
    }

    /**
     * Controller supplies server-side uploaded temporary file metadata.
     *
     * @param list<string> $currentPermissions
     */
    public function inspect(
        string $localPath,
        string $displayName,
        int $size,
        ?int $actorId = null,
        array $currentPermissions = [],
        bool $requireSignature = false,
        bool $isUpdate = false,
        bool $isDowngrade = false,
    ): ExtensionApiResponse {
        return $this->guard(function () use (
            $localPath, $displayName, $size, $actorId, $currentPermissions,
            $requireSignature, $isUpdate, $isDowngrade
        ): ExtensionApiResponse {
            $projection = $this->center->inspectAndReview(
                $localPath,
                $displayName,
                $size,
                $actorId,
                $currentPermissions,
                $requireSignature,
                $isUpdate,
                $isDowngrade,
            );

            return new ExtensionApiResponse(200, ['data' => $projection->toArray()]);
        });
    }

    /**
     * @param list<string> $currentPermissions
     */
    public function issueReviewTicket(
        string $localPath,
        string $displayName,
        int $size,
        bool $approved,
        ?int $actorId = null,
        array $currentPermissions = [],
        bool $requireSignature = false,
        bool $isUpdate = false,
        bool $isDowngrade = false,
    ): ExtensionApiResponse {
        return $this->guard(function () use (
            $localPath, $displayName, $size, $approved, $actorId,
            $currentPermissions, $requireSignature, $isUpdate, $isDowngrade
        ): ExtensionApiResponse {
            $data = $this->center->issueReviewTicket(
                $localPath,
                $displayName,
                $size,
                $approved,
                $actorId,
                $currentPermissions,
                $requireSignature,
                $isUpdate,
                $isDowngrade,
            );

            return new ExtensionApiResponse(201, ['data' => $data]);
        });
    }

    /**
     * @param list<string> $currentPermissions
     */
    public function packageOperation(
        ExtensionOperationType $type,
        string $localPath,
        string $displayName,
        int $size,
        string $reviewToken,
        ?int $actorId = null,
        array $currentPermissions = [],
        bool $requireSignature = false,
        bool $isDowngrade = false,
    ): ExtensionApiResponse {
        return $this->guard(function () use (
            $type, $localPath, $displayName, $size, $reviewToken,
            $actorId, $currentPermissions, $requireSignature, $isDowngrade
        ): ExtensionApiResponse {
            $operation = $this->center->executePackageOperation(
                $type,
                $localPath,
                $displayName,
                $size,
                $reviewToken,
                $actorId,
                $currentPermissions,
                $requireSignature,
                $isDowngrade,
            );

            return $this->operationResponse($operation);
        });
    }

    public function installedOperation(
        ExtensionOperationType $type,
        string $extensionId,
        ?string $recoveryPointId = null,
        ?int $actorId = null,
    ): ExtensionApiResponse {
        return $this->guard(function () use ($type, $extensionId, $recoveryPointId, $actorId): ExtensionApiResponse {
            $operation = $this->center->executeInstalledOperation(
                $type,
                $extensionId,
                $recoveryPointId,
                $actorId,
            );

            return $this->operationResponse($operation);
        });
    }

    private function operationResponse(ExtensionOperationRecord $operation): ExtensionApiResponse
    {
        $status = match ($operation->status) {
            ExtensionOperationStatus::Succeeded => 200,
            ExtensionOperationStatus::RecoveryRequired => 409,
            ExtensionOperationStatus::Failed => 422,
            default => 202,
        };

        if ($operation->status === ExtensionOperationStatus::RecoveryRequired) {
            return new ExtensionApiResponse($status, [
                'error' => [
                    'code' => ExtensionApiErrorCode::RecoveryRequired->value,
                    'message' => 'Extension operation requires recovery.',
                    'details' => ['operation' => $operation->publicData()],
                ],
            ]);
        }

        if ($operation->status === ExtensionOperationStatus::Failed) {
            return new ExtensionApiResponse($status, [
                'error' => [
                    'code' => ExtensionApiErrorCode::OperationFailed->value,
                    'message' => 'Extension operation failed.',
                    'details' => ['operation' => $operation->publicData()],
                ],
            ]);
        }

        return new ExtensionApiResponse($status, ['data' => $operation->publicData()]);
    }

    private function guard(callable $callback): ExtensionApiResponse
    {
        try {
            return $callback();
        } catch (ExtensionOperationConflictException) {
            return $this->error(409, ExtensionApiErrorCode::Conflict, 'Another extension operation is already running.');
        } catch (AuthorizationDeniedException) {
            return $this->error(403, ExtensionApiErrorCode::Forbidden, 'Extension operation is not permitted.');
        } catch (InvalidArgumentException $error) {
            return $this->error(422, ExtensionApiErrorCode::InvalidRequest, $error->getMessage());
        } catch (\RuntimeException $error) {
            $message = strtolower($error->getMessage());

            if (str_contains($message, 'review') || str_contains($message, 'approval')) {
                return $this->error(409, ExtensionApiErrorCode::ReviewRequired, 'Extension review or explicit approval is required.');
            }
            if (str_contains($message, 'trust') || str_contains($message, 'signature') || str_contains($message, 'checksum')) {
                return $this->error(422, ExtensionApiErrorCode::TrustFailed, 'Extension package trust verification failed.');
            }
            if (str_contains($message, 'depend')) {
                return $this->error(422, ExtensionApiErrorCode::DependencyFailed, 'Extension dependencies are not satisfied.');
            }
            if (str_contains($message, 'not found')) {
                return $this->error(404, ExtensionApiErrorCode::NotFound, 'Extension resource not found.');
            }

            return $this->error(422, ExtensionApiErrorCode::OperationFailed, 'Extension operation could not be completed.');
        } catch (Throwable) {
            return $this->error(500, ExtensionApiErrorCode::InternalError, 'Internal Extension Center error.');
        }
    }

    private function error(
        int $status,
        ExtensionApiErrorCode $code,
        string $message,
    ): ExtensionApiResponse {
        return new ExtensionApiResponse($status, [
            'error' => [
                'code' => $code->value,
                'message' => $message,
                'details' => [],
            ],
        ]);
    }
}
