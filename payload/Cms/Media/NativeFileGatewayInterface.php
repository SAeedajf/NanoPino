<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

interface NativeFileGatewayInterface
{
    /** @param array<string,mixed> $metadata */
    public function store(
        ValidatedMediaUpload $upload,
        int $siteId,
        bool $public,
        array $metadata = [],
    ): NativeFileReference;

    public function find(int $nativeFileId): ?NativeFileReference;
    public function remove(int $nativeFileId): bool;
}
