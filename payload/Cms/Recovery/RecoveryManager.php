<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

use RuntimeException;
use Throwable;

final class RecoveryManager
{
    /** @param list<SnapshotProviderInterface> $providers */
    public function __construct(
        private readonly RecoveryPointRepositoryInterface $repository,
        private readonly array $providers,
    ) {}

    /** @param array<string,mixed> $metadata */
    public function create(string $extensionId, string $operation, array $metadata = []): RecoveryPoint
    {
        $id = 'rp-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(5));
        $point = new RecoveryPoint($id, $extensionId, $operation, microtime(true), metadata: $metadata);
        $this->repository->save($point);

        try {
            foreach ($this->providers as $provider) {
                $point->providerReceipts[$provider->id()] = $provider->create($point);
                $this->repository->save($point);
            }
            $point->status = RecoveryPointStatus::Ready;
            $this->repository->save($point);
            return $point;
        } catch (Throwable $e) {
            $point->status = RecoveryPointStatus::Failed;
            $point->error = $e->getMessage();
            $this->repository->save($point);
            throw $e;
        }
    }

    public function restore(string $id): RecoveryPoint
    {
        $point = $this->repository->find($id);
        if ($point === null) {
            throw new RuntimeException('Recovery point not found: ' . $id);
        }
        if (!in_array($point->status, [RecoveryPointStatus::Ready, RecoveryPointStatus::Failed], true)) {
            throw new RuntimeException('Recovery point is not restorable from state: ' . $point->status->value);
        }

        $point->status = RecoveryPointStatus::Restoring;
        $point->error = null;
        $this->repository->save($point);

        $errors = [];
        foreach (array_reverse($this->providers) as $provider) {
            $receipt = $point->providerReceipts[$provider->id()] ?? null;
            if (!is_array($receipt)) { continue; }
            try {
                $provider->restore($point, $receipt);
            } catch (Throwable $e) {
                $errors[] = $provider->id() . ': ' . $e->getMessage();
            }
        }

        if ($errors !== []) {
            $point->status = RecoveryPointStatus::Failed;
            $point->error = implode(' | ', $errors);
            $this->repository->save($point);
            throw new RuntimeException('Recovery incomplete: ' . $point->error);
        }

        $point->status = RecoveryPointStatus::Restored;
        $this->repository->save($point);
        return $point;
    }

    public function delete(string $id): void
    {
        $point = $this->repository->find($id);
        if ($point === null) { return; }

        foreach ($this->providers as $provider) {
            $receipt = $point->providerReceipts[$provider->id()] ?? null;
            if (is_array($receipt)) {
                $provider->delete($point, $receipt);
            }
        }
        $this->repository->delete($id);
    }
}
