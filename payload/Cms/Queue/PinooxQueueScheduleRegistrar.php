<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Queue;

use Pinoox\Cron\Schedule;

/**
 * Thin adapter to native Pinoox Scheduler.
 * Scheduler supplies cadence/overlap protection; Queue owns persistence/retry.
 */
final readonly class PinooxQueueScheduleRegistrar
{
    public function __construct(private QueueSchedulerBridge $bridge) {}

    public function register(Schedule $schedule): void
    {
        $bridge=$this->bridge;
        $schedule
            ->call(static function () use ($bridge): void {
                $bridge();
            })
            ->name('cms.queue.drain')
            ->description('Drain due CMS queue jobs.')
            ->everyMinute()
            ->withoutOverlapping();
    }
}
