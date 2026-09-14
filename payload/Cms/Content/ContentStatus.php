<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Content;

enum ContentStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Archived = 'archived';
    case Trash = 'trash';
}
