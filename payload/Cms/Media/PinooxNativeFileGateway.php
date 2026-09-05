<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

use Pinoox\Component\File\Manager;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class PinooxNativeFileGateway implements NativeFileGatewayInterface
{
    public function __construct(
        private readonly string $package = 'com_pinoox_cms',
        private readonly string $group = 'cms-media',
    ) {}

    public function store(
        ValidatedMediaUpload $upload,
        int $siteId,
        bool $public,
        array $metadata = [],
    ): NativeFileReference {
        $currentSize = filesize($upload->path);
        $currentHash = hash_file('sha256', $upload->path);
        if (
            !is_int($currentSize)
            || $currentSize !== $upload->size
            || !is_string($currentHash)
            || !hash_equals($upload->sha256, $currentHash)
        ) {
            throw new RuntimeException('Validated upload payload changed before native storage.');
        }

        // Pinoox UploadBuilder ultimately expects an UploadedFile or a $_FILES key.
        // Passing a raw temp path string is ambiguous and can be interpreted as a form
        // field key, while also losing the validated original extension.
        $nativeUpload = new UploadedFile(
            $upload->path,
            $upload->originalName,
            $upload->mime,
            UPLOAD_ERR_OK,
            true,
        );

        $manager = new Manager();
        $builder = $manager
            ->upload($nativeUpload)
            ->to('cms/media/' . $siteId)
            ->group($this->group)
            ->package($this->package)
            ->extensions([$upload->extension])
            ->maxSize($upload->size)
            ->metadata(array_replace($metadata, [
                'cms_site_id' => $siteId,
                'cms_media_mime' => $upload->mime,
                'cms_original_name' => $upload->originalName,
                'cms_sha256' => $upload->sha256,
            ]));

        if ($upload->kind === MediaKind::Image) {
            $builder->thumb(512, 512);
        }

        // Pincore 3.8.15 UploadBuilder exposes access(), not public()/private().
        // Keep the CMS policy explicit and aligned with the native Pinoox contract.
        $builder->access($public ? 'public' : 'private');

        $result = $builder->save();
        if (!$result->success || $result->id === null) {
            throw new RuntimeException(
                'Pinoox native file upload failed: ' . $this->errorString($result->error)
            );
        }

        $info = $manager->info($result->id);

        return new NativeFileReference(
            $result->id,
            isset($info['hash_id']) ? (string)$info['hash_id'] : null,
            $result->url,
            $result->thumb,
            $result->path,
            isset($info['file_disk']) ? (string)$info['file_disk'] : null,
            isset($info['file_access']) ? (string)$info['file_access'] : null,
            is_array($info['file_metadata'] ?? null) ? $info['file_metadata'] : [],
        );
    }

    public function find(int $nativeFileId): ?NativeFileReference
    {
        $manager = new Manager();
        $info = $manager->info($nativeFileId);
        if ($info === []) {
            return null;
        }

        return new NativeFileReference(
            (int)$info['file_id'],
            isset($info['hash_id']) ? (string)$info['hash_id'] : null,
            isset($info['url']) ? (string)$info['url'] : null,
            isset($info['thumb']) ? (string)$info['thumb'] : null,
            isset($info['file_path']) ? (string)$info['file_path'] : null,
            isset($info['file_disk']) ? (string)$info['file_disk'] : null,
            isset($info['file_access']) ? (string)$info['file_access'] : null,
            is_array($info['file_metadata'] ?? null) ? $info['file_metadata'] : [],
        );
    }

    public function remove(int $nativeFileId): bool
    {
        return (new Manager())->remove($nativeFileId);
    }

    private function errorString(mixed $error): string
    {
        if (is_scalar($error) || $error === null) {
            return (string)$error;
        }

        return json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'unknown';
    }
}
