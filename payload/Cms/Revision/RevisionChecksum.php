<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Revision;

final class RevisionChecksum
{
    public function make(RevisionSnapshot $snapshot): string
    {
        $payload = $snapshot->payload();
        $this->sortRecursive($payload);

        return hash('sha256', json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }

    /** @param array<mixed> $value */
    private function sortRecursive(array &$value): void
    {
        if (!array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortRecursive($item);
            }
        }
    }
}
