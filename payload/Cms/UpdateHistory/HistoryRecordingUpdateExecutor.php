<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\UpdateHistory;

use App\com_pinoox_cms\Cms\ExtensionCenter\Operation\ExtensionExecutionResult;
use App\com_pinoox_cms\Cms\ExtensionCenter\Operation\ExtensionOperationExecutorInterface;
use App\com_pinoox_cms\Cms\ExtensionCenter\Operation\ExtensionOperationRequest;
use App\com_pinoox_cms\Cms\ExtensionCenter\Operation\ExtensionOperationType;

final readonly class HistoryRecordingUpdateExecutor implements ExtensionOperationExecutorInterface
{
    public function __construct(
        private ExtensionOperationExecutorInterface $inner,
        private UpdateHistoryRecorder $history,
    ) {}

    public function execute(
        ExtensionOperationRequest $request,
        callable $progress,
    ): ExtensionExecutionResult {
        if ($request->type !== ExtensionOperationType::Update) {
            return $this->inner->execute($request, $progress);
        }

        $target = $request->inspection?->manifest;
        if ($target === null) {
            throw new \LogicException('Update history requires inspected target manifest.');
        }

        $fromVersion = (string)($request->options['from_version'] ?? '');
        $fromVersionCode = (int)($request->options['from_version_code'] ?? 0);
        $actorId = isset($request->options['actor_id']) ? (int)$request->options['actor_id'] : null;

        $this->history->record(
            $request->extensionId,
            $fromVersion,
            $fromVersionCode,
            $target->version(),
            $target->versionCode(),
            UpdateHistoryStatus::Started,
            null,
            $actorId,
        );

        $result = $this->inner->execute($request, $progress);

        $status = $result->success
            ? UpdateHistoryStatus::Succeeded
            : ($result->recoveryRequired
                ? UpdateHistoryStatus::RecoveryRequired
                : UpdateHistoryStatus::Failed);

        $this->history->record(
            $request->extensionId,
            $fromVersion,
            $fromVersionCode,
            $target->version(),
            $target->versionCode(),
            $status,
            $result->recoveryPointId,
            $actorId,
        );

        return $result;
    }
}
