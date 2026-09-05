<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Tree;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Block\Document\BlockNode;

final class BlockTreeEditor
{
    public function locate(BlockDocument $document, string $id): ?BlockNodeLocation
    {
        foreach ($document->blocks as $index => $node) {
            $found = $this->locateNode($node, $id, null, $index);
            if ($found !== null) {
                return $found;
            }
        }
        return null;
    }

    public function contains(BlockDocument $document, string $ancestorId, string $candidateId): bool
    {
        $ancestor = $this->locate($document, $ancestorId);
        if ($ancestor === null) return false;
        return $this->nodeContains($ancestor->node, $candidateId);
    }

    public function insert(
        BlockDocument $document,
        ?string $parentId,
        int $index,
        BlockNode $node,
        ?string $slot = null,
    ): BlockDocument {
        foreach ($this->collectIds($node) as $id) {
            if ($this->locate($document, $id) !== null) {
                throw new BlockTreeException('Block ID already exists: ' . $id);
            }
        }

        $node = $this->withSlot($node, $slot);

        if ($parentId === null) {
            $blocks = $document->blocks;
            $index = max(0, min(count($blocks), $index));
            array_splice($blocks, $index, 0, [$node]);
            return new BlockDocument($document->schemaVersion, $blocks);
        }

        if ($this->locate($document, $parentId) === null) {
            throw new BlockTreeException('Parent block not found: ' . $parentId);
        }

        $changed = false;
        $blocks = array_map(
            function (BlockNode $current) use ($parentId, $index, $node, &$changed): BlockNode {
                return $this->insertIntoNode($current, $parentId, $index, $node, $changed);
            },
            $document->blocks,
        );

        if (!$changed) {
            throw new BlockTreeException('Parent block could not be updated.');
        }

        return new BlockDocument($document->schemaVersion, $blocks);
    }

    public function remove(BlockDocument $document, string $id): BlockTreeMutation
    {
        $location = $this->locate($document, $id);
        if ($location === null) {
            throw new BlockTreeException('Block not found: ' . $id);
        }

        if ($location->parentId === null) {
            $blocks = $document->blocks;
            array_splice($blocks, $location->index, 1);

            return new BlockTreeMutation(
                new BlockDocument($document->schemaVersion, $blocks),
                $location->node,
                null,
                $location->index,
                $location->slot,
            );
        }

        $changed = false;
        $blocks = array_map(
            function (BlockNode $node) use ($location, &$changed): BlockNode {
                return $this->removeFromNode($node, $location->parentId, $location->index, $changed);
            },
            $document->blocks,
        );

        if (!$changed) {
            throw new BlockTreeException('Block could not be removed.');
        }

        return new BlockTreeMutation(
            new BlockDocument($document->schemaVersion, $blocks),
            $location->node,
            $location->parentId,
            $location->index,
            $location->slot,
        );
    }

    public function move(
        BlockDocument $document,
        string $id,
        ?string $newParentId,
        int $newIndex,
        ?string $slot = null,
    ): BlockDocument {
        if ($newParentId === $id) {
            throw new BlockTreeException('Block cannot be moved into itself.');
        }

        if ($newParentId !== null && $this->contains($document, $id, $newParentId)) {
            throw new BlockTreeException('Block cannot be moved into its descendant.');
        }

        $removed = $this->remove($document, $id);

        if (
            $removed->parentId === $newParentId
            && $removed->index < $newIndex
        ) {
            --$newIndex;
        }

        return $this->insert(
            $removed->document,
            $newParentId,
            $newIndex,
            $removed->node,
            $slot,
        );
    }

    public function replace(BlockDocument $document, string $id, BlockNode $replacement): BlockDocument
    {
        if ($replacement->id !== $id) {
            throw new BlockTreeException('Replacement cannot change block ID.');
        }

        $found = false;
        $blocks = array_map(
            function (BlockNode $node) use ($id, $replacement, &$found): BlockNode {
                return $this->replaceNode($node, $id, $replacement, $found);
            },
            $document->blocks,
        );

        if (!$found) {
            throw new BlockTreeException('Block not found: ' . $id);
        }

        return new BlockDocument($document->schemaVersion, $blocks);
    }

    /** @return list<string> */
    public function collectIds(BlockNode $node): array
    {
        $ids = [$node->id];
        foreach ($node->children as $child) {
            $ids = array_merge($ids, $this->collectIds($child));
        }
        return $ids;
    }

    private function locateNode(
        BlockNode $node,
        string $id,
        ?string $parentId,
        int $index,
    ): ?BlockNodeLocation {
        if ($node->id === $id) {
            return new BlockNodeLocation($node, $parentId, $index, $node->slot);
        }

        foreach ($node->children as $childIndex => $child) {
            $found = $this->locateNode($child, $id, $node->id, $childIndex);
            if ($found !== null) return $found;
        }

        return null;
    }

    private function nodeContains(BlockNode $node, string $candidateId): bool
    {
        foreach ($node->children as $child) {
            if ($child->id === $candidateId || $this->nodeContains($child, $candidateId)) {
                return true;
            }
        }
        return false;
    }

    private function insertIntoNode(
        BlockNode $current,
        string $parentId,
        int $index,
        BlockNode $insert,
        bool &$changed,
    ): BlockNode {
        if ($current->id === $parentId) {
            $children = $current->children;
            $index = max(0, min(count($children), $index));
            array_splice($children, $index, 0, [$insert]);
            $changed = true;

            return $this->withChildren($current, $children);
        }

        $children = [];
        $childChanged = false;
        foreach ($current->children as $child) {
            $before = $changed;
            $next = $this->insertIntoNode($child, $parentId, $index, $insert, $changed);
            if ($changed !== $before || $next !== $child) {
                $childChanged = true;
            }
            $children[] = $next;
        }

        return $childChanged ? $this->withChildren($current, $children) : $current;
    }

    private function removeFromNode(
        BlockNode $current,
        string $parentId,
        int $index,
        bool &$changed,
    ): BlockNode {
        if ($current->id === $parentId) {
            $children = $current->children;
            if (!isset($children[$index])) {
                return $current;
            }
            array_splice($children, $index, 1);
            $changed = true;
            return $this->withChildren($current, $children);
        }

        $children = [];
        $childChanged = false;
        foreach ($current->children as $child) {
            $before = $changed;
            $next = $this->removeFromNode($child, $parentId, $index, $changed);
            if ($changed !== $before || $next !== $child) {
                $childChanged = true;
            }
            $children[] = $next;
        }

        return $childChanged ? $this->withChildren($current, $children) : $current;
    }

    private function replaceNode(
        BlockNode $current,
        string $id,
        BlockNode $replacement,
        bool &$found,
    ): BlockNode {
        if ($current->id === $id) {
            $found = true;
            return $replacement;
        }

        $children = [];
        $childChanged = false;
        foreach ($current->children as $child) {
            $before = $found;
            $next = $this->replaceNode($child, $id, $replacement, $found);
            if ($found !== $before || $next !== $child) {
                $childChanged = true;
            }
            $children[] = $next;
        }

        return $childChanged ? $this->withChildren($current, $children) : $current;
    }

    /** @param list<BlockNode> $children */
    private function withChildren(BlockNode $node, array $children): BlockNode
    {
        return new BlockNode(
            $node->id,
            $node->type,
            $node->version,
            $node->attributes,
            $node->styles,
            $node->responsive,
            $children,
            $node->slot,
        );
    }

    private function withSlot(BlockNode $node, ?string $slot): BlockNode
    {
        return new BlockNode(
            $node->id,
            $node->type,
            $node->version,
            $node->attributes,
            $node->styles,
            $node->responsive,
            $node->children,
            $slot,
        );
    }
}
