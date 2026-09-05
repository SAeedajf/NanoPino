<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

use finfo;

final class MediaUploadValidator
{
    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
        'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'zsh',
        'exe', 'dll', 'com', 'bat', 'cmd', 'msi', 'jar',
        'htaccess', 'config', 'svg', 'svgz', 'html', 'htm',
        'js', 'mjs', 'wasm', 'zip', 'tar', 'gz', '7z', 'rar',
    ];

    public function __construct(private readonly MediaUploadPolicy $policy = new MediaUploadPolicy()) {}

    public function validate(MediaUploadCandidate $candidate): ValidatedMediaUpload
    {
        $path = $candidate->temporaryPath;

        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new MediaUploadValidationException('Upload source is not a readable regular file.');
        }

        if (is_link($path)) {
            throw new MediaUploadValidationException('Symlink uploads are not allowed.');
        }

        $real = realpath($path);
        if ($real === false) {
            throw new MediaUploadValidationException('Unable to resolve upload source.');
        }

        $size = filesize($real);
        if (!is_int($size) || $size <= 0) {
            throw new MediaUploadValidationException('Empty or unreadable upload.');
        }

        if ($size > $this->policy->maxBytes) {
            throw new MediaUploadValidationException('Upload exceeds configured size limit.');
        }

        $safeName = basename(str_replace('\\', '/', $candidate->originalName));
        if ($safeName === '' || $safeName === '.' || $safeName === '..') {
            throw new MediaUploadValidationException('Invalid original filename.');
        }
        $nameLength = function_exists('mb_strlen') ? mb_strlen($safeName) : strlen($safeName);
        if ($nameLength > 255) {
            throw new MediaUploadValidationException('Original filename exceeds 255 characters.');
        }

        $extension = strtolower((string)pathinfo($safeName, PATHINFO_EXTENSION));
        if ($extension === '' || in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            throw new MediaUploadValidationException('Blocked or missing file extension.');
        }

        if (!in_array($extension, $this->policy->allowedExtensions(), true)) {
            throw new MediaUploadValidationException('File extension is not allowed.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower((string)$finfo->file($real));
        if ($mime === '' || !isset($this->policy->mimeExtensions[$mime])) {
            throw new MediaUploadValidationException('Server-derived MIME type is not allowed.');
        }

        if (!$this->policy->allowDocuments && $mime === 'application/pdf') {
            throw new MediaUploadValidationException('Document uploads are disabled.');
        }

        if (!in_array($extension, $this->policy->mimeExtensions[$mime], true)) {
            throw new MediaUploadValidationException('File extension does not match server-derived MIME type.');
        }

        [$kind, $width, $height] = $this->inspect($real, $mime);

        $sha256 = hash_file('sha256', $real);
        if (!is_string($sha256) || strlen($sha256) !== 64) {
            throw new MediaUploadValidationException('Unable to hash upload payload.');
        }

        return new ValidatedMediaUpload(
            $real,
            $safeName,
            $extension,
            $mime,
            $kind,
            $size,
            $sha256,
            $width,
            $height,
        );
    }

    /** @return array{MediaKind,?int,?int} */
    private function inspect(string $path, string $mime): array
    {
        if (str_starts_with($mime, 'image/')) {
            $info = @getimagesize($path);
            if (!is_array($info) || !isset($info[0], $info[1], $info['mime'])) {
                throw new MediaUploadValidationException('Image payload could not be decoded.');
            }

            if (strtolower((string)$info['mime']) !== $mime) {
                throw new MediaUploadValidationException('Decoded image MIME does not match file MIME.');
            }

            return [MediaKind::Image, (int)$info[0], (int)$info[1]];
        }

        if (str_starts_with($mime, 'video/')) {
            return [MediaKind::Video, null, null];
        }

        if (str_starts_with($mime, 'audio/')) {
            return [MediaKind::Audio, null, null];
        }

        if ($mime === 'application/pdf') {
            return [MediaKind::Document, null, null];
        }

        throw new MediaUploadValidationException('Unsupported media kind.');
    }
}
