<?php
declare(strict_types=1);

use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;
use App\com_pinoox_cms\Cms\Queue\PinooxQueueScheduleRegistrar;
use App\com_pinoox_cms\Cms\Queue\QueueSchedulerBridge;
use Pinoox\Cron\Schedule;

/**
 * Native Pinoox scheduler entrypoint for production editorial publication.
 *
 * The repository performs the compare-and-set transition, so a delayed or
 * overlapping cron invocation cannot publish the same scheduled record twice.
 */
return static function (Schedule $schedule): void {
    (new PinooxQueueScheduleRegistrar(
        new QueueSchedulerBridge(CmsRuntimeServices::queueWorker(), 20),
    ))->register($schedule);

    $schedule->call(static function (): void {
        CmsRuntimeServices::content()->publishDue(100);
    })
        ->everyMinute()
        ->name('cms.content.publish-due')
        ->description('Publish due NanoPino editorial content.')
        ->withoutOverlapping();
};
