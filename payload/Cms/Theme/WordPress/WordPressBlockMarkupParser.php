<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Block\Document\BlockNode;
use JsonException;

final class WordPressBlockMarkupParser
{
    // Core blocks are serialized as wp:paragraph, while registered third-party
    // blocks normally carry wp:vendor/name. Both forms become core/... or the
    // original namespaced identifier in the canonical document.
    // Capture the complete comment body first. JSON attributes may contain
    // nested objects, so a non-greedy `{...}` regex is not a valid parser.
    private const TOKEN = '~<!--\s*(/?)wp:([a-z0-9_-]+(?:/[a-z0-9_-]+)?)(.*?)-->~is';
    private const MAX_BYTES = 2_097_152;
    private const MAX_DEPTH = 128;
    private const MAX_NODES = 10_000;

    /** @var list<array{code:string,severity:string,message:string,block?:string}> */
    private array $issues = [];
    /** @var list<string> */
    private array $unsupported = [];
    private int $nodes = 0;

    public function __construct(
        private readonly int $maxBytes = self::MAX_BYTES,
        private readonly int $maxDepth = self::MAX_DEPTH,
        private readonly int $maxNodes = self::MAX_NODES,
    ) {}

    public function parse(string $markup): WordPressBlockMarkupParseResult
    {
        if ($this->maxBytes < 1 || strlen($markup) > $this->maxBytes) {
            throw new WordPressBlockMarkupException('WordPress Block Markup exceeds the parser byte limit.');
        }
        $this->issues = [];
        $this->unsupported = [];
        $this->nodes = 0;
        $root = [];
        $stack = [];
        $cursor = 0;

        preg_match_all(self::TOKEN, $markup, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $match) {
            $offset = (int)$match[0][1];
            $rawToken = (string)$match[0][0];
            $between = substr($markup, $cursor, $offset - $cursor);
            if (trim($between) !== '') {
                if ($stack === []) {
                    $this->issues[] = $this->issue('markup.unbound_html', 'warning', 'HTML outside a WordPress block boundary was ignored.');
                } else {
                    $stack[array_key_last($stack)]['html'] .= $between;
                }
            }

            $closing = ((string)$match[1][0]) === '/';
            $name = $this->canonicalName((string)$match[2][0]);
            $body = trim((string)($match[3][0] ?? ''));
            $selfClosing = !$closing && str_ends_with($body, '/');
            if ($selfClosing) $body = trim(substr($body, 0, -1));
            if ($closing && $body !== '') {
                throw new WordPressBlockMarkupException(
                    'WordPress Block Markup has attributes on a closing boundary for ' . $name . '.',
                    ['markup.invalid_closing_boundary'],
                );
            }
            $attributes = $this->attributes($body, $name);

            if ($closing) {
                if ($selfClosing || $stack === [] || $stack[array_key_last($stack)]['name'] !== $name) {
                    throw new WordPressBlockMarkupException(
                        'WordPress Block Markup has an invalid closing boundary for ' . $name . '.',
                        ['markup.invalid_nesting'],
                    );
                }
                $frame = array_pop($stack);
                $node = $this->map($frame['name'], $frame['attributes'], $frame['html'], $frame['children']);
                $this->append($node, $stack, $root);
            } elseif ($selfClosing) {
                $node = $this->map($name, $attributes, '', []);
                $this->append($node, $stack, $root);
            } else {
                if (count($stack) >= $this->maxDepth) {
                    throw new WordPressBlockMarkupException('WordPress Block Markup exceeds the parser depth limit.');
                }
                $stack[] = [
                    'name' => $name,
                    'attributes' => $attributes,
                    'html' => '',
                    'children' => [],
                ];
            }
            $cursor = $offset + strlen($rawToken);
        }

        $tail = substr($markup, $cursor);
        if (trim($tail) !== '') {
            if ($stack === []) {
                $this->issues[] = $this->issue('markup.unbound_html', 'warning', 'HTML outside a WordPress block boundary was ignored.');
            } else {
                $stack[array_key_last($stack)]['html'] .= $tail;
            }
        }
        if ($stack !== []) {
            throw new WordPressBlockMarkupException(
                'WordPress Block Markup contains an unclosed block: ' . $stack[array_key_last($stack)]['name'] . '.',
                ['markup.unclosed_block'],
            );
        }

        return new WordPressBlockMarkupParseResult(
            new BlockDocument(1, $root),
            $this->issues,
            array_values(array_unique($this->unsupported)),
            hash('sha256', $markup),
        );
    }

    /** @param list<BlockNode> $children @param list<array{name:string,attributes:array<string,mixed>,html:string,children:list<BlockNode>}> $stack @param list<BlockNode> $root */
    private function append(BlockNode $node, array &$stack, array &$root): void
    {
        if ($stack === []) $root[] = $node;
        else $stack[array_key_last($stack)]['children'][] = $node;
    }

    /** @return array<string,mixed> */
    private function attributes(string $json, string $name): array
    {
        if ($json === '') return [];
        try {
            $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new WordPressBlockMarkupException('Invalid JSON attributes for WordPress block ' . $name . '.', ['markup.invalid_attributes']);
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new WordPressBlockMarkupException('WordPress block attributes must be an object.', ['markup.attributes_not_object']);
        }
        return $decoded;
    }

    private function canonicalName(string $name): string
    {
        $name = strtolower(trim($name));
        return str_contains($name, '/') ? $name : 'core/' . $name;
    }

    /** @param array<string,mixed> $attributes @param list<BlockNode> $children */
    private function map(string $name, array $attributes, string $html, array $children): BlockNode
    {
        if (++$this->nodes > $this->maxNodes) throw new WordPressBlockMarkupException('WordPress Block Markup exceeds the parser node limit.');
        $id = 'wp-' . substr(hash('sha256', $this->nodes . "\0" . $name . "\0" . $this->canonical($attributes) . "\0" . $html), 0, 16);
        $styles = $this->styles($attributes);

        if ($name === 'core/paragraph') {
            return new BlockNode($id, 'core/paragraph', 1, ['text' => $this->text($html)], $styles);
        }
        if ($name === 'core/heading') {
            $level = (int)($attributes['level'] ?? $this->headingLevel($html));
            return new BlockNode($id, 'core/heading', 2, ['text' => $this->text($html), 'level' => max(1, min(6, $level))], $styles);
        }
        if ($name === 'core/button') {
            $url = is_string($attributes['url'] ?? null) ? trim($attributes['url']) : $this->href($html);
            if (!$this->safeUrl($url)) {
                $this->issues[] = $this->issue('markup.button_url_invalid', 'warning', 'Button URL was unsafe or missing and was replaced with #.', $name);
                $url = '#';
            }
            $target = ($attributes['linkTarget'] ?? '') === '_blank' || str_contains(strtolower($html), 'target="_blank"');
            return new BlockNode($id, 'core/button', 1, [
                'label' => $this->text($html),
                'url' => $url,
                'newTab' => $target,
            ], $styles);
        }

        $this->unsupported[] = $name;
        $this->issues[] = $this->issue(
            'markup.block_mapped_to_section',
            'warning',
            'WordPress block ' . $name . ' has no direct NanoPino mapping and was converted to a structural section wrapper.',
            $name,
        );
        $wrapperChildren = $children;
        $plainText = $this->text($html);
        if ($plainText !== '') {
            $wrapperChildren[] = new BlockNode(
                $id . '-text',
                'core/paragraph',
                1,
                ['text' => $plainText],
                $styles,
            );
        }
        $className = 'wp-block-' . str_replace('/', '-', $name);
        if (is_string($attributes['className'] ?? null)) {
            $className .= ' ' . trim(substr($attributes['className'], 0, 180));
        }
        return new BlockNode(
            $id,
            'core/section',
            1,
            ['tag' => $this->wrapperTag($name), 'className' => $className],
            $styles,
            [],
            $wrapperChildren,
        );
    }

    /** @param array<string,mixed> $attributes @return array<string,string> */
    private function styles(array $attributes): array
    {
        $styles = [];
        $align = $attributes['align'] ?? null;
        if (is_string($align) && in_array($align, ['left', 'center', 'right', 'justify'], true)) $styles['textAlign'] = $align;
        if (isset($attributes['backgroundColor'])) $styles['backgroundColor'] = $this->cssValue($attributes['backgroundColor'], 'color');
        if (isset($attributes['textColor'])) $styles['color'] = $this->cssValue($attributes['textColor'], 'color');
        if (isset($attributes['fontSize'])) $styles['fontSize'] = $this->cssValue($attributes['fontSize'], 'font-size');
        $style = $attributes['style'] ?? null;
        if (is_array($style)) {
            $color = $style['color'] ?? [];
            if (is_array($color)) {
                if (isset($color['background'])) $styles['backgroundColor'] = $this->cssValue($color['background'], 'color');
                if (isset($color['text'])) $styles['color'] = $this->cssValue($color['text'], 'color');
            }
            $typography = $style['typography'] ?? [];
            if (is_array($typography) && isset($typography['fontSize'])) $styles['fontSize'] = $this->cssValue($typography['fontSize'], 'font-size');
            $spacing = $style['spacing'] ?? [];
            if (is_array($spacing)) {
                foreach (['padding', 'margin'] as $key) if (isset($spacing[$key])) $styles[$key] = $this->spacingValue($spacing[$key]);
            }
        }
        return array_filter($styles, static fn (mixed $value): bool => is_string($value) && $value !== '');
    }

    private function cssValue(mixed $value, string $group): string
    {
        if (!is_string($value)) return '';
        $value = trim($value);
        if (preg_match('/^var:preset\|([a-z0-9_-]+)\|([a-z0-9_-]+)$/i', $value, $match) === 1) {
            return 'var(--wp--preset--' . strtolower($match[1]) . '--' . strtolower($match[2]) . ')';
        }
        if ($group === 'color' && preg_match('/^(?:#[0-9a-f]{3,8}|rgba?\([^;<>]{1,100}\)|hsla?\([^;<>]{1,100}\))$/i', $value) === 1) return $value;
        if ($group === 'font-size' && preg_match('/^(?:[0-9]+(?:\.[0-9]+)?)(?:px|rem|em|%|vw|vh)$/i', $value) === 1) return $value;
        return '';
    }

    private function spacingValue(mixed $value): string
    {
        if (is_string($value)) return $this->cssValue($value, 'spacing');
        if (!is_array($value)) return '';
        $parts = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $parts[] = isset($value[$side]) ? $this->cssValue($value[$side], 'spacing') : '';
        }
        return count(array_filter($parts, static fn (string $part): bool => $part !== '')) === 4 ? implode(' ', $parts) : '';
    }

    private function wrapperTag(string $name): string
    {
        return match ($name) {
            'core/navigation', 'core/template-part' => 'header',
            'core/cover' => 'section',
            default => 'div',
        };
    }

    private function text(string $html): string
    {
        $html = preg_replace('/<(?:script|style)\b[^>]*>.*?<\/(?:script|style)>/is', '', $html) ?? $html;
        $html = preg_replace('/<br\s*\/?\s*>/i', "\n", $html) ?? $html;
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function headingLevel(string $html): int
    {
        return preg_match('/<h([1-6])\b/i', $html, $match) === 1 ? (int)$match[1] : 2;
    }

    private function href(string $html): string
    {
        return preg_match('/<a\b[^>]*\bhref\s*=\s*["\']([^"\']*)["\']/i', $html, $match) === 1 ? trim($match[1]) : '';
    }

    private function safeUrl(string $url): bool
    {
        if ($url === '' || strlen($url) > 2000 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) return false;
        if (str_starts_with($url, '//')) return false;
        $parts = parse_url($url);
        if ($parts === false) return false;
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if ($scheme !== '') return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
        return str_starts_with($url, '/') || str_starts_with($url, '#');
    }

    /** @param array<string,mixed> $value */
    private function canonical(array $value): string
    {
        ksort($value);
        foreach ($value as $key => $item) if (is_array($item) && !array_is_list($item)) $value[$key] = $this->canonical($item);
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @return array{code:string,severity:string,message:string,block?:string} */
    private function issue(string $code, string $severity, string $message, ?string $block = null): array
    {
        $issue = ['code' => $code, 'severity' => $severity, 'message' => $message];
        if ($block !== null) $issue['block'] = $block;
        return $issue;
    }
}
