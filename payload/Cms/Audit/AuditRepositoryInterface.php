<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Audit;

interface AuditRepositoryInterface
{
    public function append(AuditEvent $event): void;

    /** @return list<AuditEvent> */
    public function recent(int $limit = 100): array;
}
