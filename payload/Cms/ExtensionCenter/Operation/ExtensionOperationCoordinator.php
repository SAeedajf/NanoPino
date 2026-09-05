<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Operation;

use App\com_pinoox_cms\Cms\ExtensionCenter\Review\ExtensionInstallReview;
use App\com_pinoox_cms\Cms\Recovery\SafeModeManager;
use Throwable;

final readonly class ExtensionOperationCoordinator
{
    public function __construct(
        private ExtensionOperationRepositoryInterface $operations,
        private ExtensionOperationLockInterface $lock,
        private ExtensionOperationExecutorInterface $executor,
        private ?SafeModeManager $safeMode = null,
    ) {}

    public function run(
        ExtensionOperationRequest $request,
        ?ExtensionInstallReview $review = null,
        bool $approved = false,
    ): ExtensionOperationRecord {
        if (
            in_array($request->type, [ExtensionOperationType::Install, ExtensionOperationType::Update], true)
            && ($review === null || !$review->canExecute($approved))
        ) {
            throw new \RuntimeException('Extension install/update review has not been approved.');
        }

        if (
            $request->type === ExtensionOperationType::Activate
            && $this->safeMode?->isQuarantined($request->extensionId)
        ) {
            throw new \RuntimeException('Quarantined extension cannot be activated.');
        }

        return $this->lock->synchronized($request->extensionId, function () use ($request): ExtensionOperationRecord {
            $active = $this->operations->activeForExtension($request->extensionId);
            if ($active !== null) {
                throw new ExtensionOperationConflictException(
                    'Another extension operation is already running.'
                );
            }

            $record = new ExtensionOperationRecord(
                'extop-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(5)),
                $request->type,
                $request->extensionId,
                ExtensionOperationStatus::Pending,
                microtime(true),
            );
            $this->operations->save($record);

            $record->status = ExtensionOperationStatus::Running;
            $record->startedAt = microtime(true);
            $this->operations->save($record);

            try {
                $result = $this->executor->execute(
                    $request,
                    function (string $step, string $status, string $message) use ($record): void {
                        $record->addStep(
                            $step,
                            $status,
                            $this->sanitizeMessage($message),
                        );
                        $this->operations->save($record);
                    },
                );

                $record->recoveryPointId = $result->recoveryPointId;
                $record->internalError = $result->error;
                $record->status = $result->success
                    ? ExtensionOperationStatus::Succeeded
                    : ($result->recoveryRequired
                        ? ExtensionOperationStatus::RecoveryRequired
                        : ExtensionOperationStatus::Failed);
            } catch (Throwable $error) {
                $record->internalError = $error->getMessage();
                $record->status = ExtensionOperationStatus::Failed;
                $record->addStep('failed', 'error', 'Extension operation failed.');
            }

            $record->finishedAt = microtime(true);
            $this->operations->save($record);
            return $record;
        });
    }

    private function sanitizeMessage(string $message): string
    {
        $message = preg_replace(
            '#(?:[A-Za-z]:[\\\\/]|/)(?:[^\\s<>"\']+[\\\\/])+[^\\s<>"\']*#',
            '[path-redacted]',
            $message,
        ) ?? $message;

        return trim($message);
    }
}
