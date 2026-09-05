<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\GlobalBlock;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;

interface GlobalBlockRepositoryInterface
{
    public function create(
        int $siteId,
        string $name,
        BlockDocument $document,
        string $checksum,
        ?int $actorId,
    ): GlobalBlockRecord;

    public function find(int $id): ?GlobalBlockRecord;

    /** @return list<GlobalBlockRecord> */
    public function forSite(int $siteId, int $limit = 200): array;

    public function save(
        int $id,
        string $name,
        BlockDocument $document,
        string $checksum,
        int $expectedVersion,
        ?int $actorId,
    ): GlobalBlockRecord;
}
