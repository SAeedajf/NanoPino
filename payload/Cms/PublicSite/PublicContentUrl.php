<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\PublicSite;

use App\com_pinoox_cms\Cms\Admin\AdminRuntimeUrl;
use App\com_pinoox_cms\Cms\Content\ContentRecord;
use App\com_pinoox_cms\Cms\Content\ContentStatus;

final class PublicContentUrl
{
    public static function path(ContentRecord $record, ?string $mountPath = null): ?string
    {
        if (!in_array($record->type, ['page', 'post'], true)) {
            return null;
        }

        // Public routes intentionally expose published content only. Returning
        // a URL for drafts made the admin API advertise links that could only
        // resolve to a public 404.
        if ($record->status !== ContentStatus::Published) {
            return null;
        }

        if (!PublicSlugPolicy::accepts($record->slug)) {
            return null;
        }

        return AdminRuntimeUrl::appPath('/' . $record->type . '/' . $record->slug, $mountPath);
    }
}
