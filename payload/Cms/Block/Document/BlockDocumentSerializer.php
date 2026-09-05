<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Document;

final class BlockDocumentSerializer
{
    public function json(BlockDocument $document, bool $pretty = false): string
    {
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($pretty) $flags |= JSON_PRETTY_PRINT;

        return json_encode($document->toArray(), $flags);
    }

    public function checksum(BlockDocument $document): string
    {
        return hash('sha256', $this->canonicalJson($document->toArray()));
    }

    /** @param array<string,mixed> $value */
    private function canonicalJson(array $value): string
    {
        $normalize = function (mixed $item) use (&$normalize): mixed {
            if (!is_array($item)) return $item;

            if (array_is_list($item)) {
                return array_map($normalize, $item);
            }

            ksort($item);
            foreach ($item as $key => $child) {
                $item[$key] = $normalize($child);
            }
            return $item;
        };

        return json_encode(
            $normalize($value),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
