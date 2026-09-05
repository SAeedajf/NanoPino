<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

use RuntimeException;

final class MediaInUseException extends RuntimeException
{
    /** @param list<MediaUsage> $usages */
    public function __construct(public readonly array $usages)
    {
        parent::__construct('Media asset is still referenced by one or more resources.');
    }
}
