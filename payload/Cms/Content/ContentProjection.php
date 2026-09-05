<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Content;

/**
 * Controls how much content data is materialized by repository reads.
 *
 * List is intentionally scalar-only for collection views. Detail and Editor
 * currently expose the complete ContentRecord contract; they remain separate
 * so editor-specific expansion can evolve without changing public list reads.
 */
enum ContentProjection: string
{
    case List = 'list';
    case Detail = 'detail';
    case Editor = 'editor';

    public function includesPayload(): bool
    {
        return $this !== self::List;
    }

    public function includesAssociations(): bool
    {
        return $this !== self::List;
    }
}
