<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Document;

final class BlockDocumentParser
{
    private int $nodes = 0;

    public function __construct(
        private readonly int $maxParseDepth = 128,
        private readonly int $maxParseNodes = 10000,
    ) {}

    /** @param array<string,mixed> $raw */
    public function parse(array $raw): BlockDocument
    {
        $this->nodes = 0;

        $unknownRoot = array_diff(array_keys($raw), ['version', 'blocks']);
        if ($unknownRoot !== []) {
            throw new BlockDocumentValidationException([
                'Block document contains unknown root keys.',
            ]);
        }

        $version = (int)($raw['version'] ?? 0);
        if ($version !== 1) {
            throw new BlockDocumentValidationException(['Block document version must be 1.']);
        }

        $blocks = $raw['blocks'] ?? null;
        if (!is_array($blocks) || !array_is_list($blocks)) {
            throw new BlockDocumentValidationException(['Block document blocks must be a list.']);
        }

        return new BlockDocument(
            $version,
            array_map(fn ($node) => $this->node($node, 0), $blocks),
        );
    }

    private function node(mixed $raw, int $depth): BlockNode
    {
        if (++$this->nodes > $this->maxParseNodes) {
            throw new BlockDocumentValidationException(['Block document exceeds parser node limit.']);
        }
        if ($depth > $this->maxParseDepth) {
            throw new BlockDocumentValidationException(['Block document exceeds parser depth limit.']);
        }

        if (!is_array($raw) || array_is_list($raw)) {
            throw new BlockDocumentValidationException(['Block node must be an object.']);
        }

        $allowed = ['id', 'type', 'version', 'attributes', 'styles', 'responsive', 'children', 'slot'];
        if (array_diff(array_keys($raw), $allowed) !== []) {
            throw new BlockDocumentValidationException(['Block node contains unknown keys.']);
        }

        foreach (['attributes', 'styles', 'responsive'] as $field) {
            if (
                isset($raw[$field])
                && (
                    !is_array($raw[$field])
                    || ($raw[$field] !== [] && array_is_list($raw[$field]))
                )
            ) {
                throw new BlockDocumentValidationException([
                    'Block ' . $field . ' must be an object.',
                ]);
            }
        }

        $children = $raw['children'] ?? [];
        if (!is_array($children) || !array_is_list($children)) {
            throw new BlockDocumentValidationException(['Block children must be a list.']);
        }

        $slot = $raw['slot'] ?? null;
        if ($slot !== null && !is_string($slot)) {
            throw new BlockDocumentValidationException(['Block slot must be a string or null.']);
        }

        return new BlockNode(
            id: trim((string)($raw['id'] ?? '')),
            type: strtolower(trim((string)($raw['type'] ?? ''))),
            version: (int)($raw['version'] ?? 1),
            attributes: $raw['attributes'] ?? [],
            styles: $raw['styles'] ?? [],
            responsive: $raw['responsive'] ?? [],
            children: array_map(fn ($child) => $this->node($child, $depth + 1), $children),
            slot: $slot !== null ? trim($slot) : null,
        );
    }
}
