<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

final class CallableSnapshotProvider implements SnapshotProviderInterface
{
    public function __construct(
        private readonly string $providerId,
        private readonly \Closure $createCallback,
        private readonly \Closure $restoreCallback,
        private readonly ?\Closure $deleteCallback = null,
    ) {}

    public function id(): string { return $this->providerId; }

    public function create(RecoveryPoint $point): array
    {
        $result = ($this->createCallback)($point);
        return is_array($result) ? $result : [];
    }

    public function restore(RecoveryPoint $point, array $receipt): void
    {
        ($this->restoreCallback)($point, $receipt);
    }

    public function delete(RecoveryPoint $point, array $receipt): void
    {
        if ($this->deleteCallback !== null) {
            ($this->deleteCallback)($point, $receipt);
        }
    }
}
