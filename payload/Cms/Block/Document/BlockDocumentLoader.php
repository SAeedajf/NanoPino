<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Document;

use App\com_pinoox_cms\Cms\Block\Migration\BlockMigrationEngine;
use App\com_pinoox_cms\Cms\Block\Migration\BlockMigrationException;

final class BlockDocumentLoader
{
    public function __construct(
        private readonly BlockDocumentParser $parser,
        private readonly BlockMigrationEngine $migrations,
        private readonly BlockDocumentValidator $validator,
        private readonly int $maxBytes = 2_097_152,
    ) {}

    /** @param array<string,mixed> $raw */
    public function fromArray(array $raw): BlockDocument
    {
        $encoded = json_encode(
            $raw,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        if (strlen($encoded) > $this->maxBytes) {
            throw new BlockDocumentValidationException(['Block document exceeds maximum encoded size.']);
        }

        $document = $this->parser->parse($raw);

        try {
            $document = $this->migrations->migrateDocument($document);
        } catch (BlockMigrationException $error) {
            throw new BlockDocumentValidationException([
                'Block document migration failed: ' . $error->getMessage(),
            ]);
        }

        $this->validator->validate($document);

        return $document;
    }

    public function fromJson(string $json): BlockDocument
    {
        if ($json === '' || strlen($json) > $this->maxBytes) {
            throw new BlockDocumentValidationException(['Block document JSON size is invalid.']);
        }

        try {
            $raw = json_decode($json, true, 128, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new BlockDocumentValidationException([
                'Block document JSON is invalid: ' . $error->getMessage(),
            ]);
        }

        if (!is_array($raw) || array_is_list($raw)) {
            throw new BlockDocumentValidationException(['Block document JSON root must be an object.']);
        }

        return $this->fromArray($raw);
    }
}
