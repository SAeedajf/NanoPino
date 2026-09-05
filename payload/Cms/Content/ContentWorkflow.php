<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Content;

use LogicException;

final class ContentWorkflow
{
    /** @var array<string,list<ContentStatus>> */
    private const TRANSITIONS = [
        'draft' => [
            ContentStatus::Published,
            ContentStatus::Scheduled,
            ContentStatus::Trash,
        ],
        'published' => [
            ContentStatus::Draft,
            ContentStatus::Trash,
        ],
        'scheduled' => [
            ContentStatus::Draft,
            ContentStatus::Published,
            ContentStatus::Trash,
        ],
        'trash' => [
            ContentStatus::Draft,
        ],
    ];

    public function can(ContentStatus $from, ContentStatus $to): bool
    {
        return $from === $to || in_array($to, self::TRANSITIONS[$from->value] ?? [], true);
    }

    public function assert(ContentStatus $from, ContentStatus $to): void
    {
        if (!$this->can($from, $to)) {
            throw new LogicException(sprintf(
                'Invalid content status transition: %s -> %s',
                $from->value,
                $to->value,
            ));
        }
    }
}
