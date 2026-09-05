<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Audit;

final class InMemoryAuditRepository implements AuditRepositoryInterface
{
    /** @var list<AuditEvent> */
    private array $events = [];

    public function append(AuditEvent $event): void
    {
        $this->events[] = $event;
    }

    public function recent(int $limit = 100): array
    {
        $limit = max(1, min($limit, 1000));
        return array_slice(array_reverse($this->events), 0, $limit);
    }
}
