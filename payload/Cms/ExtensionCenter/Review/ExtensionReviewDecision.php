<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Review;

enum ExtensionReviewDecision: string
{
    case Allow = 'allow';
    case ApprovalRequired = 'approval_required';
    case Block = 'block';
}
