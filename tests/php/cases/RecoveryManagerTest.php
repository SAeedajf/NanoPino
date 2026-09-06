<?php
declare(strict_types=1);

use App\com_pinoox_cms\Cms\Recovery\RecoveryManager;
use App\com_pinoox_cms\Cms\Recovery\RecoveryPoint;
use App\com_pinoox_cms\Cms\Recovery\RecoveryPointRepositoryInterface;
use App\com_pinoox_cms\Cms\Recovery\RecoveryPointStatus;
use App\com_pinoox_cms\Cms\Recovery\SnapshotProviderInterface;

final class R13MemoryRecoveryRepository implements RecoveryPointRepositoryInterface
{
    /** @var array<string,RecoveryPoint> */
    public array $points = [];

    public function save(RecoveryPoint $point): void
    {
        $this->points[$point->id] = $point;
    }

    public function find(string $id): ?RecoveryPoint
    {
        return $this->points[$id] ?? null;
    }

    public function forExtension(string $extensionId): array
    {
        return array_values(array_filter(
            $this->points,
            static fn (RecoveryPoint $point): bool => $point->extensionId === $extensionId,
        ));
    }

    public function delete(string $id): void
    {
        unset($this->points[$id]);
    }
}

final readonly class R13TrackingSnapshotProvider implements SnapshotProviderInterface
{
    public function __construct(
        private string $providerId,
        private ArrayObject $events,
        private bool $failCreate = false,
        private bool $failRestore = false,
    ) {}

    public function id(): string
    {
        return $this->providerId;
    }

    public function create(RecoveryPoint $point): array
    {
        $this->events->append('create:' . $this->providerId);
        if ($this->failCreate) {
            throw new RuntimeException('create failed ' . $this->providerId);
        }
        return ['provider' => $this->providerId, 'point' => $point->id];
    }

    public function restore(RecoveryPoint $point, array $receipt): void
    {
        $this->events->append('restore:' . $this->providerId);
        if ($this->failRestore) {
            throw new RuntimeException('restore failed ' . $this->providerId);
        }
        np_assert_same($this->providerId, $receipt['provider'] ?? null);
    }

    public function delete(RecoveryPoint $point, array $receipt): void
    {
        $this->events->append('delete:' . $this->providerId);
    }
}

return [
    'Recovery manager creates receipts and restores providers in reverse order' => static function (): void {
        $repo = new R13MemoryRecoveryRepository();
        $events = new ArrayObject();
        $manager = new RecoveryManager($repo, [
            new R13TrackingSnapshotProvider('filesystem', $events),
            new R13TrackingSnapshotProvider('migrations', $events),
        ]);

        $point = $manager->create('com_demo', 'update', ['version' => '1.2.3']);
        np_assert_same(RecoveryPointStatus::Ready, $point->status);
        np_assert_same(['filesystem', 'migrations'], array_keys($point->providerReceipts));
        np_assert_same(['create:filesystem', 'create:migrations'], $events->getArrayCopy());

        $restored = $manager->restore($point->id);
        np_assert_same(RecoveryPointStatus::Restored, $restored->status);
        np_assert_same(
            ['create:filesystem', 'create:migrations', 'restore:migrations', 'restore:filesystem'],
            $events->getArrayCopy(),
        );
    },

    'Recovery manager marks snapshot creation failure as failed' => static function (): void {
        $repo = new R13MemoryRecoveryRepository();
        $events = new ArrayObject();
        $manager = new RecoveryManager($repo, [
            new R13TrackingSnapshotProvider('filesystem', $events, failCreate: true),
        ]);

        np_assert_throws(
            static fn () => $manager->create('com_demo', 'install'),
            RuntimeException::class,
            'create failed filesystem',
        );

        np_assert_same(1, count($repo->points));
        $point = array_values($repo->points)[0];
        np_assert_same(RecoveryPointStatus::Failed, $point->status);
        np_assert_contains('create failed filesystem', (string)$point->error);
    },

    'Recovery manager fails closed when any provider cannot restore' => static function (): void {
        $repo = new R13MemoryRecoveryRepository();
        $events = new ArrayObject();
        $manager = new RecoveryManager($repo, [
            new R13TrackingSnapshotProvider('filesystem', $events),
            new R13TrackingSnapshotProvider('migrations', $events, failRestore: true),
        ]);

        $point = $manager->create('com_demo', 'update');

        np_assert_throws(
            static fn () => $manager->restore($point->id),
            RuntimeException::class,
            'Recovery incomplete',
        );

        $stored = $repo->find($point->id);
        np_assert_true($stored !== null);
        np_assert_same(RecoveryPointStatus::Failed, $stored->status);
        np_assert_contains('migrations: restore failed migrations', (string)$stored->error);
        np_assert_contains('restore:filesystem', implode('|', $events->getArrayCopy()));
    },

    'Recovery point serialization round-trips state and receipts' => static function (): void {
        $point = new RecoveryPoint(
            'rp-test',
            'com_demo',
            'update',
            123.45,
            RecoveryPointStatus::Ready,
            ['version' => '2.0.0'],
            ['filesystem' => ['sha256' => 'abc']],
        );

        $restored = RecoveryPoint::fromArray($point->toArray());
        np_assert_same($point->toArray(), $restored->toArray());
    },
];
