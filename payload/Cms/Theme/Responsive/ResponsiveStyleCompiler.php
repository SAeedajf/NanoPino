<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Responsive;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Block\Document\BlockNode;
use App\com_pinoox_cms\Cms\Block\Document\ResponsiveStyleValidator;
use InvalidArgumentException;

final class ResponsiveStyleCompiler
{
    public function __construct(
        private readonly ResponsiveStyleValidator $styles = new ResponsiveStyleValidator(),
    ) {}

    public function compile(BlockDocument $document, BreakpointSet $breakpoints): string
    {
        $baseRules = [];
        $responsive = [];

        foreach ($document->blocks as $node) {
            $this->collect($node, $breakpoints, $baseRules, $responsive);
        }

        $chunks = [];
        if ($baseRules !== []) {
            $chunks[] = implode("\n", $baseRules);
        }

        foreach ($breakpoints->items as $breakpoint) {
            $rules = $responsive[$breakpoint->id] ?? [];
            if ($rules === []) continue;

            $chunks[] = '@media (min-width: ' . $breakpoint->minWidth . ") {\n"
                . $this->indent(implode("\n", $rules))
                . "\n}";
        }

        return $chunks === [] ? '' : implode("\n\n", $chunks) . "\n";
    }

    /**
     * @param list<string> $baseRules
     * @param array<string,list<string>> $responsive
     */
    private function collect(
        BlockNode $node,
        BreakpointSet $breakpoints,
        array &$baseRules,
        array &$responsive,
    ): void {
        if (!$this->safeId($node->id)) {
            throw new InvalidArgumentException('Unsafe Block ID for responsive CSS compilation.');
        }

        if ($node->styles !== []) {
            if (!$this->styles->validateStyles($node->styles)) {
                throw new InvalidArgumentException('Invalid Block base styles.');
            }
            $declarations = $this->declarations($node->styles);
            if ($declarations !== []) {
                $baseRules[] = '[data-cms-block-id="' . $node->id . '"] { ' . implode(' ', $declarations) . ' }';
            }
        }

        foreach ($node->responsive as $breakpointId => $styleMap) {
            if (!$breakpoints->has((string)$breakpointId)) {
                throw new InvalidArgumentException('Unknown responsive breakpoint: ' . $breakpointId);
            }
            if (!is_array($styleMap) || !$this->styles->validateStyles($styleMap)) {
                throw new InvalidArgumentException('Invalid responsive style map.');
            }
            $declarations = $this->declarations($styleMap);
            if ($declarations !== []) {
                $responsive[(string)$breakpointId][] =
                    '[data-cms-block-id="' . $node->id . '"] { ' . implode(' ', $declarations) . ' }';
            }
        }

        foreach ($node->children as $child) {
            $this->collect($child, $breakpoints, $baseRules, $responsive);
        }
    }

    /** @param array<string,mixed> $styles @return list<string> */
    private function declarations(array $styles): array
    {
        $result = [];
        foreach ($styles as $key => $value) {
            if ($value === null) continue;

            $property = $this->property((string)$key);
            if ($property === null) continue;

            $value = trim((string)$value);
            if ($value === '') continue;

            $result[] = $property . ': ' . $value . ';';
        }
        return $result;
    }

    private function property(string $key): ?string
    {
        return match ($key) {
            'color' => 'color',
            'backgroundColor' => 'background-color',
            'fontSize' => 'font-size',
            'fontWeight' => 'font-weight',
            'lineHeight' => 'line-height',
            'letterSpacing' => 'letter-spacing',
            'margin' => 'margin',
            'padding' => 'padding',
            'width' => 'width',
            'height' => 'height',
            'minWidth' => 'min-width',
            'maxWidth' => 'max-width',
            'minHeight' => 'min-height',
            'maxHeight' => 'max-height',
            'display' => 'display',
            'alignItems' => 'align-items',
            'justifyContent' => 'justify-content',
            'gap' => 'gap',
            'gridTemplateColumns' => 'grid-template-columns',
            'textAlign' => 'text-align',
            'borderRadius' => 'border-radius',
            'borderWidth' => 'border-width',
            'borderColor' => 'border-color',
            'opacity' => 'opacity',
            default => null,
        };
    }

    private function safeId(string $id): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/', $id) === 1;
    }

    private function indent(string $css): string
    {
        return '  ' . str_replace("\n", "\n  ", $css);
    }
}
