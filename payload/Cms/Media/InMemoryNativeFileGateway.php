<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

final class InMemoryNativeFileGateway implements NativeFileGatewayInterface
{
    /** @var array<int,NativeFileReference> */
    private array $files = [];
    private int $nextId = 1;

    public function store(
        ValidatedMediaUpload $upload,
        int $siteId,
        bool $public,
        array $metadata = [],
    ): NativeFileReference {
        $id = $this->nextId++;
        $ref = new NativeFileReference(
            $id,
            'mem-' . $id,
            $public ? '/media/' . $id : null,
            $upload->kind === MediaKind::Image ? '/media/' . $id . '/thumb' : null,
            $upload->path,
            $public ? 'public' : 'local',
            $public ? 'public' : 'private',
            $metadata,
        );
        $this->files[$id] = $ref;
        return $ref;
    }

    public function find(int $nativeFileId): ?NativeFileReference
    {
        return $this->files[$nativeFileId] ?? null;
    }

    public function remove(int $nativeFileId): bool
    {
        if (!isset($this->files[$nativeFileId])) return false;
        unset($this->files[$nativeFileId]);
        return true;
    }
}
