<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

use App\com_pinoox_cms\Cms\Theme\Design\DesignDocument;
use App\com_pinoox_cms\Cms\Theme\Design\DesignSchemaValidator;
use App\com_pinoox_cms\Cms\Theme\Design\StyleVariation;
use App\com_pinoox_cms\Cms\Theme\ThemeFileReader;
use InvalidArgumentException;

/**
 * Converts the data-only part of WordPress theme.json into NanoPino design
 * tokens. It never evaluates custom CSS, PHP, block render callbacks or JS.
 */
final class WordPressThemeJsonCompiler
{
    private const SUPPORTED_VERSIONS = [2, 3];
    private const ROOT_KEYS = [
        'settings', 'styles', 'customTemplates', 'templateParts', 'patterns',
        'title', 'description', '$schema', 'version', 'useRootPaddingAwareAlignments',
    ];

    public function __construct(
        private readonly DesignSchemaValidator $validator = new DesignSchemaValidator(),
        private readonly ThemeFileReader $files = new ThemeFileReader(),
    ) {}

    public function compileFile(string $themeRoot, string $relativeFile = 'theme.json'): WordPressThemeJsonCompileResult
    {
        try {
            $raw = $this->files->json($themeRoot, $relativeFile);
        } catch (\Throwable $error) {
            return new WordPressThemeJsonCompileResult(
                $relativeFile,
                null,
                [$this->issue('theme_json.file_invalid', 'blocker', 'theme.json could not be loaded: ' . $error->getMessage(), $relativeFile)],
            );
        }
        if ($raw === null) {
            return new WordPressThemeJsonCompileResult(
                $relativeFile,
                null,
                [$this->issue('theme_json.file_missing', 'blocker', 'theme.json was not found.', $relativeFile)],
            );
        }
        return $this->compile($raw, $relativeFile);
    }

    /** @param array<string,mixed> $theme */
    public function compile(array $theme, string $sourcePath = 'theme.json'): WordPressThemeJsonCompileResult
    {
        return $this->compileInternal($theme, $sourcePath, null, null);
    }

    /** @param array<string,mixed> $theme */
    public function compileVariation(
        array $theme,
        string $id,
        string $sourcePath,
        ?string $title = null,
    ): WordPressThemeJsonCompileResult {
        $id = strtolower(trim($id));
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,95}$/', $id) !== 1) {
            throw new InvalidArgumentException('WordPress style variation ID is invalid.');
        }
        return $this->compileInternal($theme, $sourcePath, $id, $title);
    }

    /** @param array<string,mixed> $theme */
    private function compileInternal(array $theme, string $sourcePath, ?string $variationId, ?string $variationTitle): WordPressThemeJsonCompileResult
    {
        $issues = [];
        foreach (array_keys($theme) as $key) {
            if (!in_array((string)$key, self::ROOT_KEYS, true)) {
                $issues[] = $this->issue('theme_json.unknown_root_key', 'warning', 'theme.json key is not part of the data-only compiler contract.', (string)$key);
            }
        }

        $version = isset($theme['version']) && is_numeric($theme['version']) ? (int)$theme['version'] : null;
        if ($version === null || !in_array($version, self::SUPPORTED_VERSIONS, true)) {
            $issues[] = $this->issue('theme_json.version_unsupported', 'blocker', 'theme.json version must be 2 or 3.', 'version');
            return new WordPressThemeJsonCompileResult($sourcePath, null, $issues, null, $version);
        }

        $tokens = [];
        $settings = is_array($theme['settings'] ?? null) ? $theme['settings'] : [];
        $styles = is_array($theme['styles'] ?? null) ? $theme['styles'] : [];
        $this->compileSettings($settings, $tokens, $issues);
        $this->compileStyles($styles, $tokens, $issues);
        $this->reportUnsupported($theme, $settings, $styles, $issues);

        try {
            $document = $this->validator->validate(['schema' => 1, 'tokens' => $tokens]);
        } catch (\Throwable $error) {
            $issues[] = $this->issue('theme_json.compiled_design_invalid', 'blocker', 'Compiled NanoPino design tokens failed schema validation: ' . $error->getMessage());
            return new WordPressThemeJsonCompileResult($sourcePath, null, $issues, null, $version);
        }

        $variation = null;
        if ($variationId !== null) {
            $resolvedTitle = trim($variationTitle ?? (string)($theme['title'] ?? ''));
            $variation = new StyleVariation(
                $variationId,
                $resolvedTitle !== '' ? $resolvedTitle : $variationId,
                $document->tokens,
                $sourcePath,
            );
        }

        return new WordPressThemeJsonCompileResult($sourcePath, $document, $issues, $variation, $version);
    }

    /** @param array<string,mixed> $settings @param array<string,mixed> $tokens @param list<array{code:string,severity:string,message:string,path?:string}> $issues */
    private function compileSettings(array $settings, array &$tokens, array &$issues): void
    {
        $color = is_array($settings['color'] ?? null) ? $settings['color'] : [];
        foreach (['palette', 'gradients', 'duotone'] as $kind) {
            $items = is_array($color[$kind] ?? null) ? $color[$kind] : [];
            $inputKey = match ($kind) {
                'palette' => 'color',
                'gradients' => 'gradient',
                default => 'value',
            };
            $compiled = $this->presetMap($items, $kind, $issues, $inputKey, 'value');
            if ($compiled !== []) $tokens['colors'][$kind] = $compiled;
        }

        $typographySettings = is_array($settings['typography'] ?? null) ? $settings['typography'] : [];
        $fontFamilies = is_array($typographySettings['fontFamilies'] ?? null) ? $typographySettings['fontFamilies'] : [];
        $compiledFonts = [];
        foreach ($fontFamilies as $item) {
            if (!is_array($item)) continue;
            $slug = $this->slug($item['slug'] ?? null);
            $family = $this->safeValue($item['fontFamily'] ?? null);
            if ($slug === null || $family === null) {
                $issues[] = $this->issue('theme_json.font_family_invalid', 'warning', 'A font family preset was skipped because its slug or value is invalid.', 'settings.typography.fontFamilies');
                continue;
            }
            $compiledFonts[$slug] = array_filter([
                'name' => $this->safeValue($item['name'] ?? null),
                'family' => $family,
                'font_style' => $this->safeValue($item['fontStyle'] ?? null),
                'font_weight' => $this->safeValue($item['fontWeight'] ?? null),
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        }
        if ($compiledFonts !== []) $tokens['fontFamilies'] = $compiledFonts;

        $fontSizes = is_array($typographySettings['fontSizes'] ?? null) ? $typographySettings['fontSizes'] : [];
        $compiledSizes = $this->presetMap($fontSizes, 'font-size', $issues, 'size', 'size');
        if ($compiledSizes !== []) $tokens['fontSizes'] = $compiledSizes;

        $spacing = is_array($settings['spacing'] ?? null) ? $settings['spacing'] : [];
        if (is_array($spacing['units'] ?? null)) $tokens['spacing']['units'] = array_values(array_filter(array_map(fn (mixed $value): ?string => $this->safeValue($value), $spacing['units']), static fn (?string $value): bool => $value !== null));
        $spacingSizes = is_array($spacing['spacingSizes'] ?? null) ? $spacing['spacingSizes'] : [];
        $compiledSpacing = $this->presetMap($spacingSizes, 'spacing', $issues, 'size', 'size');
        if ($compiledSpacing !== []) $tokens['spacing']['scale'] = $compiledSpacing;

        $layout = is_array($settings['layout'] ?? null) ? $settings['layout'] : [];
        foreach (['contentSize' => 'content', 'wideSize' => 'wide'] as $source => $target) {
            $value = $this->safeValue($layout[$source] ?? null);
            if ($value !== null) $tokens['containers'][$target] = $value;
        }

        $shadowSettings = is_array($settings['shadow'] ?? null) ? $settings['shadow'] : [];
        $shadow = is_array($shadowSettings['presets'] ?? null) ? $shadowSettings['presets'] : [];
        $compiledShadows = $this->presetMap($shadow, 'shadow', $issues, 'shadow', 'shadow');
        if ($compiledShadows !== []) $tokens['shadows'] = $compiledShadows;

        if (($settings['appearanceTools'] ?? false) === true) $tokens['accessibility']['appearance_tools'] = true;
        if (($settings['useRootPaddingAwareAlignments'] ?? false) === true) $tokens['spacing']['root_padding_aware'] = true;
    }

    /** @param array<string,mixed> $styles @param array<string,mixed> $tokens @param list<array{code:string,severity:string,message:string,path?:string}> $issues */
    private function compileStyles(array $styles, array &$tokens, array &$issues): void
    {
        $color = is_array($styles['color'] ?? null) ? $styles['color'] : [];
        foreach (['background' => 'background', 'text' => 'text'] as $source => $target) {
            $value = $this->safeValue($color[$source] ?? null);
            if ($value !== null) {
                $tokens['colors'][$target] = $value;
            } elseif (array_key_exists($source, $color)) {
                $issues[] = $this->issue('theme_json.style_value_skipped', 'warning', 'Unsafe or unsupported CSS value was skipped.', 'styles.color.' . $source);
            }
        }

        $typography = is_array($styles['typography'] ?? null) ? $styles['typography'] : [];
        foreach (['fontFamily', 'fontSize', 'fontStyle', 'fontWeight', 'letterSpacing', 'lineHeight', 'textAlign'] as $key) {
            $value = $this->safeValue($typography[$key] ?? null);
            if ($value !== null) {
                $tokens['typography']['default'][$this->snake($key)] = $value;
            } elseif (array_key_exists($key, $typography)) {
                $issues[] = $this->issue('theme_json.style_value_skipped', 'warning', 'Unsafe or unsupported CSS value was skipped.', 'styles.typography.' . $key);
            }
        }

        $spacing = is_array($styles['spacing'] ?? null) ? $styles['spacing'] : [];
        foreach (['padding', 'margin', 'blockGap'] as $key) {
            $value = $this->compileSpacing($spacing[$key] ?? null);
            if ($value !== null) $tokens['spacing']['default'][$this->snake($key)] = $value;
        }

        $elements = is_array($styles['elements'] ?? null) ? $styles['elements'] : [];
        foreach (['button' => 'buttons', 'link' => 'links', 'input' => 'forms'] as $element => $target) {
            if (!is_array($elements[$element] ?? null)) continue;
            $compiled = $this->compileStyleObject($elements[$element], $issues, 'styles.elements.' . $element);
            if ($compiled !== []) $tokens[$target]['default'] = $compiled;
        }
    }

    /** @param array<string,mixed> $object @param list<array{code:string,severity:string,message:string,path?:string}> $issues @return array<string,mixed> */
    private function compileStyleObject(array $object, array &$issues, string $path): array
    {
        $compiled = [];
        foreach ($object as $key => $value) {
            if (is_array($value)) {
                $nested = $this->compileStyleObject($value, $issues, $path . '.' . $key);
                if ($nested !== []) $compiled[$this->snake((string)$key)] = $nested;
                continue;
            }
            $safe = $this->safeValue($value);
            if ($safe !== null) $compiled[$this->snake((string)$key)] = $safe;
            elseif ($value !== null) $issues[] = $this->issue('theme_json.style_value_skipped', 'warning', 'Unsafe or unsupported CSS value was skipped.', $path . '.' . $key);
        }
        return $compiled;
    }

    /** @param array<string,mixed> $theme @param array<string,mixed> $settings @param array<string,mixed> $styles @param list<array{code:string,severity:string,message:string,path?:string}> $issues */
    private function reportUnsupported(array $theme, array $settings, array $styles, array &$issues): void
    {
        foreach (['customTemplates', 'templateParts', 'patterns'] as $key) {
            if (isset($theme[$key]) && is_array($theme[$key]) && $theme[$key] !== []) $issues[] = $this->issue('theme_json.structure_deferred', 'warning', $key . ' metadata is preserved for the template conversion phase.', $key);
        }
        foreach (['blocks', 'custom', 'border', 'dimensions', 'outline', 'position', 'shadow', 'typography'] as $key) {
            if (array_key_exists($key, $settings) && !in_array($key, ['shadow', 'typography'], true)) $issues[] = $this->issue('theme_json.setting_deferred', 'warning', 'theme.json setting requires a later specialized adapter.', 'settings.' . $key);
        }
        foreach (['blocks', 'css', 'filter', 'outline', 'position', 'shadow'] as $key) {
            if (array_key_exists($key, $styles)) $issues[] = $this->issue('theme_json.style_deferred', 'warning', 'theme.json style requires a later specialized adapter.', 'styles.' . $key);
        }
    }

    /** @param array<int,mixed> $items @param list<array{code:string,severity:string,message:string,path?:string}> $issues @return array<string,mixed> */
    private function presetMap(array $items, string $kind, array &$issues, string $inputKey = 'value', string $outputKey = 'value'): array
    {
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $slug = $this->slug($item['slug'] ?? null);
            $value = $this->safeValue($item[$inputKey] ?? null);
            if ($slug === null || $value === null) {
                $issues[] = $this->issue('theme_json.preset_invalid', 'warning', 'A ' . $kind . ' preset was skipped because its slug or value is invalid.', 'settings');
                continue;
            }
            $result[$slug] = array_filter([
                'name' => $this->safeValue($item['name'] ?? null),
                $outputKey => $value,
            ], static fn (mixed $entry): bool => $entry !== null && $entry !== '');
        }
        return $result;
    }

    private function compileSpacing(mixed $value): string|array|null
    {
        if (is_string($value) || is_int($value) || is_float($value)) return $this->safeValue($value);
        if (!is_array($value)) return null;
        $result = [];
        foreach (['top', 'right', 'bottom', 'left', 'blockStart', 'blockEnd', 'inlineStart', 'inlineEnd'] as $key) {
            $safe = $this->safeValue($value[$key] ?? null);
            if ($safe !== null) $result[$this->snake($key)] = $safe;
        }
        return $result !== [] ? $result : null;
    }

    private function safeValue(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) return null;
        $value = trim((string)$value);
        if ($value === '' || strlen($value) > 1000 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) return null;
        $lower = strtolower($value);
        foreach ([';', '{', '}', '<', '>', 'url(', 'expression(', 'javascript:', '@import'] as $blocked) {
            if (str_contains($lower, $blocked)) return null;
        }
        if (preg_match('/^var:(preset|custom)\|([a-z0-9_-]+)\|([a-z0-9_-]+)$/i', $value, $match) === 1) {
            return 'var(--wp--' . strtolower($match[1]) . '--' . strtolower($match[2]) . '--' . strtolower($match[3]) . ')';
        }
        return $value;
    }

    private function slug(mixed $value): ?string
    {
        $slug = strtolower(trim((string)$value));
        return preg_match('/^[a-z0-9][a-z0-9_-]{0,95}$/', $slug) === 1 ? $slug : null;
    }

    private function snake(string $value): string
    {
        return strtolower((string)(preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value));
    }

    /** @return array{code:string,severity:string,message:string,path?:string} */
    private function issue(string $code, string $severity, string $message, ?string $path = null): array
    {
        $issue = ['code' => $code, 'severity' => $severity, 'message' => $message];
        if ($path !== null && $path !== '') $issue['path'] = $path;
        return $issue;
    }
}
