<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Migration;

use App\com_pinoox_cms\Cms\Block\BlockRegistry;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentValidator;
use App\com_pinoox_cms\Cms\Block\Document\BlockNode;

final class BlockMigrationEngine
{
    public function __construct(
        private readonly BlockRegistry $blocks,
        private readonly BlockMigrationRegistry $migrations,
        private readonly BlockDocumentValidator $validator,
    ) {}

    public function migrateDocument(BlockDocument $document): BlockDocument
    {
        $blocks = array_map(fn (BlockNode $node): BlockNode => $this->migrateNode($node), $document->blocks);
        $migrated = new BlockDocument($document->schemaVersion, $blocks);
        $this->validator->validate($migrated);
        return $migrated;
    }

    private function migrateNode(BlockNode $node): BlockNode
    {
        $definition = $this->blocks->definition($node->type)
            ?? throw new BlockMigrationException('Cannot migrate unknown block: ' . $node->type);

        $current = $node;
        $guard = 0;

        while ($current->version < $definition->schemaVersion) {
            if (++$guard > 64) {
                throw new BlockMigrationException('Block migration chain exceeded safety limit.');
            }

            $migration = $this->next(
                $current->type,
                $current->version,
                $definition->owner(),
            );
            if ($migration === null) {
                throw new BlockMigrationException(sprintf(
                    'Missing migration for %s from v%d to v%d.',
                    $current->type,
                    $current->version,
                    $definition->schemaVersion,
                ));
            }

            $payload = [
                'id' => $current->id,
                'type' => $current->type,
                'version' => $current->version,
                'attributes' => $current->attributes,
                'styles' => $current->styles,
                'responsive' => $current->responsive,
            ];

            $result = ($migration->migrate)($payload);
            if (!is_array($result)) {
                throw new BlockMigrationException('Block migration must return an array payload.');
            }

            $resultId = (string)($result['id'] ?? $current->id);
            $resultType = (string)($result['type'] ?? $current->type);

            if ($resultId !== $current->id || $resultType !== $current->type) {
                throw new BlockMigrationException('Block migration cannot change block identity or type.');
            }

            $current = new BlockNode(
                $current->id,
                $current->type,
                $migration->toVersion,
                is_array($result['attributes'] ?? null) ? $result['attributes'] : $current->attributes,
                is_array($result['styles'] ?? null) ? $result['styles'] : $current->styles,
                is_array($result['responsive'] ?? null) ? $result['responsive'] : $current->responsive,
                $current->children,
            );
        }

        $children = array_map(fn (BlockNode $child): BlockNode => $this->migrateNode($child), $current->children);

        return new BlockNode(
            $current->id,
            $current->type,
            $current->version,
            $current->attributes,
            $current->styles,
            $current->responsive,
            $children,
            $current->slot,
        );
    }

    private function next(
        string $blockType,
        int $fromVersion,
        string $owner,
    ): ?BlockMigrationDefinition {
        $candidates = array_values(array_filter(
            $this->migrations->forBlock($blockType),
            static fn (BlockMigrationDefinition $migration): bool =>
                $migration->fromVersion === $fromVersion
                && $migration->owner() === $owner,
        ));

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (BlockMigrationDefinition $a, BlockMigrationDefinition $b): int =>
            $a->toVersion <=> $b->toVersion
        );

        return $candidates[0];
    }
}
