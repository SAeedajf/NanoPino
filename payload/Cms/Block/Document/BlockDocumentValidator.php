<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Document;

use App\com_pinoox_cms\Cms\Block\BlockDefinition;
use App\com_pinoox_cms\Cms\Block\BlockRegistry;

final class BlockDocumentValidator
{
    private int $nodes = 0;
    /** @var array<string,true> */
    private array $ids = [];

    public function __construct(
        private readonly BlockRegistry $blocks,
        private readonly BlockAttributeValidator $attributes = new BlockAttributeValidator(),
        private readonly ResponsiveStyleValidator $styles = new ResponsiveStyleValidator(),
        private readonly int $maxDepth = 64,
        private readonly int $maxNodes = 5000,
    ) {}

    public function validate(BlockDocument $document): void
    {
        $this->nodes = 0;
        $this->ids = [];
        $errors = [];

        foreach ($document->blocks as $index => $block) {
            $this->validateNode($block, null, 0, 'blocks.' . $index, $errors);
        }

        if ($errors !== []) {
            throw new BlockDocumentValidationException(array_values(array_unique($errors)));
        }
    }

    /** @param list<string> $errors */
    private function validateNode(
        BlockNode $node,
        ?BlockDefinition $parent,
        int $depth,
        string $path,
        array &$errors,
    ): void {
        ++$this->nodes;
        if ($this->nodes > $this->maxNodes) {
            $errors[] = 'Block document exceeds node limit.';
            return;
        }
        if ($depth > $this->maxDepth) {
            $errors[] = 'Block document exceeds depth limit.';
            return;
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/', $node->id) !== 1) {
            $errors[] = $path . ': invalid block id.';
        } elseif (isset($this->ids[$node->id])) {
            $errors[] = $path . ': duplicate block id.';
        } else {
            $this->ids[$node->id] = true;
        }

        if ($node->slot !== null && preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $node->slot) !== 1) {
            $errors[] = $path . ': invalid slot name.';
        }

        $definition = $this->blocks->definition($node->type);
        if ($definition === null) {
            $errors[] = $path . ': unknown block type ' . $node->type . '.';
            return;
        }

        if ($node->version < 1 || $node->version > $definition->schemaVersion) {
            $errors[] = $path . ': unsupported block version.';
        }

        if ($parent !== null) {
            if (!$parent->allowsChildren) {
                $errors[] = $path . ': parent block does not allow children.';
            } elseif (
                $parent->allowedChildren !== []
                && !in_array('*', $parent->allowedChildren, true)
                && !in_array($node->type, $parent->allowedChildren, true)
            ) {
                $errors[] = $path . ': child block type not allowed by parent.';
            }
        }

        foreach ($definition->attributes as $name => $attribute) {
            if (!array_key_exists($name, $node->attributes)) {
                if ($attribute->required && $attribute->default === null) {
                    $errors[] = $path . ': required attribute missing: ' . $name;
                }
                continue;
            }
            if (!$this->attributes->valid($attribute, $node->attributes[$name])) {
                $errors[] = $path . ': invalid attribute: ' . $name;
            }
        }

        foreach (array_keys($node->attributes) as $name) {
            if (!isset($definition->attributes[$name])) {
                $errors[] = $path . ': unknown attribute: ' . $name;
            }
        }

        if (
            !$this->styles->validateStyles($node->styles)
            || !$this->stylesAllowedBySupports($definition, $node->styles)
        ) {
            $errors[] = $path . ': invalid or unsupported styles.';
        }

        if (!($definition->supports['responsive'] ?? false) && $node->responsive !== []) {
            $errors[] = $path . ': responsive styles not supported.';
        } elseif (
            !$this->styles->validateResponsive($node->responsive)
            || !$this->responsiveAllowedBySupports($definition, $node->responsive)
        ) {
            $errors[] = $path . ': invalid or unsupported responsive styles.';
        }

        if (!$definition->allowsChildren && $node->children !== []) {
            $errors[] = $path . ': block cannot have children.';
        }

        if ($definition->slots !== []) {
            $slotCounts = [];
            foreach ($node->children as $child) {
                $slot = $child->slot;
                if ($slot === null || !isset($definition->slots[$slot])) {
                    $errors[] = $path . ': child is missing a valid declared slot.';
                    continue;
                }

                $slotConfig = $definition->slots[$slot];
                $allowed = is_array($slotConfig['allowed'] ?? null) ? $slotConfig['allowed'] : ['*'];
                if (!in_array('*', $allowed, true) && !in_array($child->type, $allowed, true)) {
                    $errors[] = $path . ': child type is not allowed in slot ' . $slot . '.';
                }

                $slotCounts[$slot] = ($slotCounts[$slot] ?? 0) + 1;
            }

            foreach ($definition->slots as $slotName => $slotConfig) {
                $count = $slotCounts[$slotName] ?? 0;
                if (($slotConfig['required'] ?? false) && $count === 0) {
                    $errors[] = $path . ': required slot is empty: ' . $slotName;
                }
                if (!($slotConfig['multiple'] ?? true) && $count > 1) {
                    $errors[] = $path . ': slot allows only one child: ' . $slotName;
                }
            }
        } else {
            foreach ($node->children as $child) {
                if ($child->slot !== null) {
                    $errors[] = $path . ': child declares slot but parent has no slots.';
                }
            }
        }

        foreach ($node->children as $index => $child) {
            $this->validateNode($child, $definition, $depth + 1, $path . '.children.' . $index, $errors);
        }
    }

    /** @param array<string,mixed> $styles */
    private function stylesAllowedBySupports(BlockDefinition $definition, array $styles): bool
    {
        foreach (array_keys($styles) as $key) {
            $group = match ((string)$key) {
                'color', 'backgroundColor', 'borderColor' => 'color',
                'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing', 'textAlign' => 'typography',
                'margin', 'padding', 'gap' => 'spacing',
                'width', 'height', 'minWidth', 'maxWidth', 'minHeight', 'maxHeight' => 'dimensions',
                'display', 'alignItems', 'justifyContent', 'gridTemplateColumns' => 'align',
                'borderRadius', 'borderWidth', 'opacity' => null,
                default => null,
            };

            if ($group !== null && !($definition->supports[$group] ?? false)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $responsive */
    private function responsiveAllowedBySupports(BlockDefinition $definition, array $responsive): bool
    {
        foreach ($responsive as $styles) {
            if (!is_array($styles) || !$this->stylesAllowedBySupports($definition, $styles)) {
                return false;
            }
        }
        return true;
    }
}
