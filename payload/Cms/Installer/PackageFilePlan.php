<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer;

use App\com_pinoox_cms\Cms\Exception\PackageFilePlanException;
use App\com_pinoox_cms\Cms\Security\Package\PackageResourceGuard;

final readonly class PackageFilePlan
{
    /** @var list<PlannedPackageEntry> */
    private array $entries;

    /** @param list<PackageArchiveEntry> $entries */
    public function __construct(
        array $entries,
        ?PackageResourceGuard $resourceGuard = null,
    ) {
        ($resourceGuard ?? new PackageResourceGuard())->validate($entries);

        $planned = [];
        $byPath = [];
        $caseFold = [];

        foreach ($entries as $entry) {
            if ($entry->type === PackageEntryType::Symlink) {
                throw new PackageFilePlanException('Symlink package entries are forbidden: ' . $entry->path);
            }
            if ($entry->size < 0) {
                throw new PackageFilePlanException('Negative package entry size is invalid: ' . $entry->path);
            }
            $path = CanonicalPackagePath::normalize($entry->path);
            $fold = strtolower($path);
            if (isset($byPath[$path])) {
                throw new PackageFilePlanException('Duplicate package target: ' . $path);
            }
            if (isset($caseFold[$fold])) {
                throw new PackageFilePlanException('Case-insensitive package target collision: ' . $path . ' vs ' . $caseFold[$fold]);
            }
            $byPath[$path] = $entry->type;
            $caseFold[$fold] = $path;
            $planned[] = new PlannedPackageEntry($path, $entry->type, $entry->size, $entry->sha256);
        }

        foreach ($byPath as $path => $type) {
            $parts = explode('/', $path);
            array_pop($parts);
            $ancestor = '';
            foreach ($parts as $part) {
                $ancestor = $ancestor === '' ? $part : $ancestor . '/' . $part;
                if (($byPath[$ancestor] ?? null) === PackageEntryType::File) {
                    throw new PackageFilePlanException(sprintf(
                        'File/directory collision: file "%s" is an ancestor of "%s".',
                        $ancestor,
                        $path,
                    ));
                }
            }
        }

        usort($planned, static function (PlannedPackageEntry $a, PlannedPackageEntry $b): int {
            if ($a->type !== $b->type) {
                return $a->type === PackageEntryType::Directory ? -1 : 1;
            }
            return strcmp($a->path, $b->path);
        });
        $this->entries = $planned;
    }

    /** @return list<PlannedPackageEntry> */
    public function entries(): array { return $this->entries; }

    /** @return list<PlannedPackageEntry> */
    public function files(): array
    {
        return array_values(array_filter($this->entries, static fn ($e): bool => $e->type === PackageEntryType::File));
    }

    /** @return list<PlannedPackageEntry> */
    public function directories(): array
    {
        return array_values(array_filter($this->entries, static fn ($e): bool => $e->type === PackageEntryType::Directory));
    }

    public function count(): int { return count($this->entries); }
}
