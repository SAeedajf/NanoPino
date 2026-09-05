<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\GlobalBlock;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentLoader;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentValidator;
use App\com_pinoox_cms\Cms\Block\Document\BlockNode;

final readonly class GlobalBlockReferenceExpander
{
    public function __construct(
        private GlobalBlockRepositoryInterface $repository,
        private BlockDocumentLoader $loader,
        private BlockDocumentValidator $validator,
        private int $maxDepth = 16,
    ) {}

    public function expand(BlockDocument $document, int $siteId): BlockDocument
    {
        $blocks = $this->expandList($document->blocks, $siteId, [], 0, 'root');
        $expanded = new BlockDocument($document->schemaVersion, $blocks);
        $this->validator->validate($expanded);
        return $expanded;
    }

    /**
     * @param list<BlockNode> $nodes
     * @param list<int> $stack
     * @return list<BlockNode>
     */
    private function expandList(
        array $nodes,
        int $siteId,
        array $stack,
        int $depth,
        string $prefix,
    ): array {
        if ($depth > $this->maxDepth) {
            throw new \RuntimeException('Global Block expansion depth exceeded.');
        }

        $result = [];
        foreach ($nodes as $node) {
            if ($node->type !== 'core/global-reference') {
                $children = $this->expandList(
                    $node->children,
                    $siteId,
                    $stack,
                    $depth,
                    $prefix . '-' . $node->id,
                );
                $result[] = new BlockNode(
                    $node->id,
                    $node->type,
                    $node->version,
                    $node->attributes,
                    $node->styles,
                    $node->responsive,
                    $children,
                    $node->slot,
                );
                continue;
            }

            $globalId = (int)($node->attributes['globalId'] ?? 0);
            if ($globalId < 1) {
                throw new \RuntimeException('Invalid Global Block reference.');
            }
            if (in_array($globalId, $stack, true)) {
                throw new \RuntimeException('Circular Global Block reference detected.');
            }

            $record = $this->repository->find($globalId)
                ?? throw new \RuntimeException('Referenced Global Block not found.');

            if ($record->siteId !== $siteId) {
                throw new \RuntimeException('Global Block belongs to another site.');
            }

            $migrated = $this->loader->fromArray($record->document->toArray());

            $expanded = $this->expandList(
                $migrated->blocks,
                $siteId,
                [...$stack, $globalId],
                $depth + 1,
                $prefix . '-' . $node->id . '-g' . $globalId,
            );

            foreach ($expanded as $child) {
                $result[] = $this->remapIds(
                    $child,
                    $prefix . '-' . $node->id . '-g' . $globalId,
                    $node->slot,
                );
            }
        }

        return $result;
    }

    private function remapIds(
        BlockNode $node,
        string $prefix,
        ?string $rootSlot = null,
    ): BlockNode {
        $raw = preg_replace('/[^A-Za-z0-9._:-]+/', '-', $prefix . '-' . $node->id) ?: 'global';
        if (strlen($raw) > 112) {
            $raw = substr($raw, 0, 99) . '-' . substr(hash('sha256', $raw), 0, 12);
        }

        return new BlockNode(
            $raw,
            $node->type,
            $node->version,
            $node->attributes,
            $node->styles,
            $node->responsive,
            array_map(
                fn (BlockNode $child): BlockNode => $this->remapIds($child, $prefix, null),
                $node->children
            ),
            $rootSlot ?? $node->slot,
        );
    }
}
