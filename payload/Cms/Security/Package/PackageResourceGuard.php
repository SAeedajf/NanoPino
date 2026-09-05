<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Package;

use App\com_pinoox_cms\Cms\Installer\PackageArchiveEntry;
use App\com_pinoox_cms\Cms\Installer\PackageEntryType;

final readonly class PackageResourceGuard
{
    public function __construct(private PackageResourceBudget $budget = new PackageResourceBudget()) {}

    /** @param list<PackageArchiveEntry> $entries */
    public function validate(array $entries): void
    {
        if (count($entries) > $this->budget->maxEntries) {
            throw new \RuntimeException('Package contains too many archive entries.');
        }

        $total = 0;
        foreach ($entries as $entry) {
            if (!$entry instanceof PackageArchiveEntry) {
                throw new \InvalidArgumentException('Invalid package archive entry.');
            }
            if ($entry->type !== PackageEntryType::File) continue;

            if ($entry->size > $this->budget->maxSingleFileBytes) {
                throw new \RuntimeException('Package contains an oversized file.');
            }

            $total += $entry->size;
            if ($total > $this->budget->maxUncompressedBytes) {
                throw new \RuntimeException('Package exceeds total uncompressed-size budget.');
            }

            if ($entry->compressedSize !== null && $entry->compressedSize > 0) {
                $ratio = $entry->size / $entry->compressedSize;
                if ($ratio > $this->budget->maxCompressionRatio) {
                    throw new \RuntimeException('Package compression ratio exceeds safety budget.');
                }
            }
        }
    }
}
