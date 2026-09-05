<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Revision;

use App\com_pinoox_cms\Cms\Content\ContentRecord;

interface ContentRevisionRecorderInterface
{
    public function record(
        ContentRecord $content,
        RevisionKind $kind,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): RevisionRecord;
}
