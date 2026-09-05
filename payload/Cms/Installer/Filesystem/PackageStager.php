<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer\Filesystem;

use App\com_pinoox_cms\Cms\Installer\PackageFilePlan;
use App\com_pinoox_cms\Cms\Installer\PackagePayloadReaderInterface;
use RuntimeException;

final class PackageStager
{
    public function stage(PackageFilePlan $plan, PackagePayloadReaderInterface $reader, string $stagingRoot): void
    {
        $root = rtrim(str_replace('\\', '/', $stagingRoot), '/');
        if ($root === '') {
            throw new RuntimeException('Staging root cannot be empty.');
        }
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
            throw new RuntimeException('Cannot create staging root: ' . $root);
        }

        foreach ($plan->directories() as $entry) {
            $target = $root . '/' . $entry->path;
            if (is_file($target)) {
                throw new RuntimeException('Staging directory collides with file: ' . $entry->path);
            }
            if (!is_dir($target) && !mkdir($target, 0700, true) && !is_dir($target)) {
                throw new RuntimeException('Cannot create staged directory: ' . $entry->path);
            }
        }

        foreach ($plan->files() as $entry) {
            $target = $root . '/' . $entry->path;
            $parent = dirname($target);
            if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
                throw new RuntimeException('Cannot create staged file parent: ' . $entry->path);
            }
            if (is_dir($target)) {
                throw new RuntimeException('Staged file target is a directory: ' . $entry->path);
            }
            $contents = $reader->read($entry->path);
            if (strlen($contents) !== $entry->size) {
                throw new RuntimeException('Staged payload size mismatch: ' . $entry->path);
            }
            if ($entry->sha256 !== null && !hash_equals(strtolower($entry->sha256), hash('sha256', $contents))) {
                throw new RuntimeException('Staged payload hash mismatch: ' . $entry->path);
            }
            if (file_put_contents($target, $contents, LOCK_EX) === false) {
                throw new RuntimeException('Cannot write staged file: ' . $entry->path);
            }
        }
    }
}
