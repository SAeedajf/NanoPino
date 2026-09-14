<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

use App\com_pinoox_cms\Cms\Recovery\FaultInjection\FaultInjectorInterface;
use App\com_pinoox_cms\Cms\Recovery\FaultInjection\NullFaultInjector;
use RuntimeException;
use Throwable;

final class RecoveryManager
{
    private readonly FaultInjectorInterface $faults;

    /** @param list<SnapshotProviderInterface> $providers */
    public function __construct(
        private readonly RecoveryPointRepositoryInterface $repository,
        private readonly array $providers,
        ?FaultInjectorInterface $faults = null,
    ) {
        $this->faults = $faults ?? new NullFaultInjector();
    }

    /** @param array<string,mixed> $metadata */
    public function create(string $extensionId, string $operation, array $metadata = []): RecoveryPoint
    {
        $this->faults->checkpoint('recovery.create.before');
        $id = 'rp-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(5));
        $point = new RecoveryPoint($id, $extensionId, $operation, microtime(true), metadata: $metadata);
        $this->repository->save($point);

        try {
            foreach ($this->providers as $provider) {
                $this->faults->checkpoint('recovery.create.provider.' . $provider->id() . '.before');
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
        $this->faults->checkpoint('recovery.restore.before');
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
                $this->faults->checkpoint('recovery.restore.provider.' . $provider->id() . '.before');
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
        $this->faults->checkpoint('recovery.delete.before');
        $point = $this->repository->find($id);
        if ($point === null) { return; }

        foreach ($this->providers as $provider) {
            $receipt = $point->providerReceipts[$provider->id()] ?? null;
            if (is_array($receipt)) {
                $this->faults->checkpoint('recovery.delete.provider.' . $provider->id() . '.before');
                $provider->delete($point, $receipt);
            }
        }
        $this->repository->delete($id);
    }
}
