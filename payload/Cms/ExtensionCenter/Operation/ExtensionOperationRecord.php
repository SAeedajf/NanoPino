<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Operation;

final class ExtensionOperationRecord
{
    /** @var list<ExtensionOperationStep> */
    public array $steps = [];

    public function __construct(
        public readonly string $id,
        public readonly ExtensionOperationType $type,
        public readonly string $extensionId,
        public ExtensionOperationStatus $status = ExtensionOperationStatus::Pending,
        public readonly float $createdAt = 0.0,
        public ?float $startedAt = null,
        public ?float $finishedAt = null,
        public ?string $recoveryPointId = null,
        public ?string $internalError = null,
    ) {}

    public function addStep(string $step, string $status, string $message): void
    {
        if (
            preg_match('/^[a-z][a-z0-9_.-]{0,63}$/', $step) !== 1
            || preg_match('/^[a-z][a-z0-9_.-]{0,31}$/', $status) !== 1
        ) {
            throw new \InvalidArgumentException('Invalid extension operation progress entry.');
        }

        $message = trim($message);
        if (strlen($message) > 1000) {
            $message = substr($message, 0, 1000);
        }

        $this->steps[] = new ExtensionOperationStep(
            $step,
            $status,
            $message,
            microtime(true),
        );

        if (count($this->steps) > 500) {
            array_shift($this->steps);
        }
    }

    /** @return array<string,mixed> */
    public function publicData(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'extension_id' => $this->extensionId,
            'status' => $this->status->value,
            'created_at' => $this->createdAt,
            'started_at' => $this->startedAt,
            'finished_at' => $this->finishedAt,
            'recovery_point_id' => $this->recoveryPointId,
            'steps' => array_map(
                static fn (ExtensionOperationStep $step): array => $step->toArray(),
                $this->steps,
            ),
        ];
    }
}
