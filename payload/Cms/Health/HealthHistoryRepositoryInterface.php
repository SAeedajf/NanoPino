<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Health;
interface HealthHistoryRepositoryInterface {
 /** @param list<HealthResult> $results */
 public function append(HealthStatus $overall,array $results):void;
 /** @return list<array<string,mixed>> */
 public function recent(int $limit=20):array;
}
