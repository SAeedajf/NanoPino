<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Recovery;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationDeniedException;
use App\com_pinoox_cms\Cms\Recovery\RecoveryActionService;
use Throwable;

final readonly class RecoveryApiFacade
{
    public function __construct(private RecoveryActionService $recovery) {}

    public function restore(string $recoveryPointId, ?int $actorId = null): RecoveryApiResponse
    {
        return $this->guard(function () use ($recoveryPointId, $actorId): RecoveryApiResponse {
            $point = $this->recovery->restore($recoveryPointId, $actorId);
            return new RecoveryApiResponse(200, [
                'data'=>[
                    'id'=>$point->id,
                    'extension_id'=>$point->extensionId,
                    'status'=>$point->status->value,
                ],
            ]);
        });
    }

    public function disableSafeMode(?int $actorId = null): RecoveryApiResponse
    {
        return $this->guard(function () use ($actorId): RecoveryApiResponse {
            $state = $this->recovery->disableSafeMode($actorId);
            return new RecoveryApiResponse(200, ['data'=>$state->toArray()]);
        });
    }

    private function guard(callable $callback): RecoveryApiResponse
    {
        try {
            return $callback();
        } catch (AuthorizationDeniedException) {
            return $this->error(403, RecoveryApiErrorCode::Forbidden, 'Recovery operation is not permitted.');
        } catch (\RuntimeException $e) {
            $message = strtolower($e->getMessage());
            if (str_contains($message, 'not found')) {
                return $this->error(404, RecoveryApiErrorCode::NotFound, 'Recovery point not found.');
            }
            if (str_contains($message, 'safe mode exit denied')) {
                return $this->error(409, RecoveryApiErrorCode::HealthBlocked, 'Safe Mode exit is blocked by health validation.');
            }
            return $this->error(422, RecoveryApiErrorCode::RestoreFailed, 'Recovery operation failed.');
        } catch (Throwable) {
            return $this->error(500, RecoveryApiErrorCode::InternalError, 'Internal recovery error.');
        }
    }

    private function error(int $status, RecoveryApiErrorCode $code, string $message): RecoveryApiResponse
    {
        return new RecoveryApiResponse($status, [
            'error'=>['code'=>$code->value,'message'=>$message,'details'=>[]],
        ]);
    }
}
