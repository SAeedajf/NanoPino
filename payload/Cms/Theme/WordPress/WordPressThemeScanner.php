<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Read-only, non-executing intake scanner for WordPress themes.
 *
 * This class deliberately does not include, eval, or bootstrap any theme PHP.
 * It produces a bounded report that later import/conversion stages can consume.
 */
final class WordPressThemeScanner
{
    private const CONTENT_EXTENSIONS = ['php', 'html', 'htm', 'css', 'js', 'json'];
    private const SKIP_DIRECTORIES = ['.git', 'node_modules', 'vendor'];

    public function __construct(
        private readonly int $maxFiles = 2000,
        private readonly int $maxTotalBytes = 20_000_000,
        private readonly int $maxFileBytes = 2_000_000,
    ) {
        if ($this->maxFiles < 1 || $this->maxTotalBytes < 1 || $this->maxFileBytes < 1) {
            throw new InvalidArgumentException('WordPress theme scanner limits must be positive.');
        }
    }

    public function scan(string $themeRoot): WordPressThemeScanResult
    {
        $root = realpath($themeRoot);
        if ($root === false || !is_dir($root)) {
            throw new InvalidArgumentException('WordPress theme root must be an existing directory.');
        }

        $root = $this->normalize($root);
        $files = [];
        $contents = [];
        $issues = [];
        $totalBytes = 0;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );
        } catch (\Throwable $error) {
            throw new InvalidArgumentException('WordPress theme root could not be inspected.', 0, $error);
        }

        foreach ($iterator as $info) {
            if (!$info instanceof SplFileInfo) continue;
            $path = $this->normalize($info->getPathname());
            $relative = ltrim(substr($path, strlen($root)), '/');
            if ($relative === '') continue;

            $parts = explode('/', $relative);
            if (count(array_intersect($parts, self::SKIP_DIRECTORIES)) > 0) continue;

            if ($info->isLink()) {
                $issues[] = $this->issue('theme.symlink_skipped', 'warning', 'Symbolic link was excluded from the scan.', $relative);
                continue;
            }

            $real = realpath($path);
            if ($real === false || !$this->inside($this->normalize($real), $root)) {
                $issues[] = $this->issue('theme.path_escape', 'blocker', 'A file resolves outside the theme root.', $relative);
                continue;
            }

            $size = $info->getSize();
            if ($size < 0 || $size > $this->maxFileBytes) {
                $issues[] = $this->issue('theme.file_too_large', 'blocker', 'A theme file exceeds the scanner limit.', $relative);
                continue;
            }

            if (count($files) >= $this->maxFiles) {
                $issues[] = $this->issue('theme.file_count_exceeded', 'blocker', 'The theme exceeds the maximum file count.');
                break;
            }

            $files[] = $relative;
            $totalBytes += $size;
            if ($totalBytes > $this->maxTotalBytes) {
                $issues[] = $this->issue('theme.total_size_exceeded', 'blocker', 'The theme exceeds the total scan size limit.');
                break;
            }

            $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
            if (in_array($extension, self::CONTENT_EXTENSIONS, true)) {
                $content = file_get_contents($path);
                if (is_string($content)) $contents[$relative] = $content;
            }
        }

        sort($files, SORT_STRING);
        ksort($contents, SORT_STRING);

        $metadata = $this->styleMetadata($contents['style.css'] ?? '');
        $themeJson = $this->themeJson($contents, $issues);
        $features = $this->features($files, $contents, $themeJson);
        $dependencies = $this->dependencies($contents);
        $type = $this->type($features);
        $this->addSemanticIssues($type, $features, $dependencies, $issues);

        $blockers = count(array_filter($issues, static fn (array $issue): bool => $issue['severity'] === 'blocker'));
        $scores = $this->scores($type, $features, $dependencies, $blockers);
        $safeToImport = $blockers === 0 && $type !== WordPressThemeType::Unknown;

        $nextActions = $this->nextActions($type, $features, $dependencies, $issues);

        return new WordPressThemeScanResult(
            $root,
            $type,
            $safeToImport,
            $files,
            $metadata,
            array_merge($features, ['total_bytes' => $totalBytes]),
            $dependencies,
            $issues,
            $scores,
            $nextActions,
        );
    }

    /** @param array<string,string> $contents @param list<array{code:string,severity:string,message:string,path?:string}> $issues */
    private function themeJson(array $contents, array &$issues): ?array
    {
        if (!isset($contents['theme.json'])) return null;

        try {
            $decoded = json_decode($contents['theme.json'], true, 64, JSON_THROW_ON_ERROR);
        } catch (\Throwable $error) {
            $issues[] = $this->issue('theme.theme_json_invalid', 'blocker', 'theme.json is not valid JSON.');
            return null;
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            $issues[] = $this->issue('theme.theme_json_root_invalid', 'blocker', 'theme.json must contain an object at its root.');
            return null;
        }

        return $decoded;
    }

    /** @return array<string,mixed> */
    private function styleMetadata(string $style): array
    {
        $metadata = [];
        if ($style === '') return $metadata;

        foreach (['Theme Name' => 'name', 'Version' => 'version', 'Text Domain' => 'text_domain', 'License' => 'license', 'License URI' => 'license_uri', 'Template' => 'parent', 'Requires at least' => 'requires_at_least', 'Requires PHP' => 'requires_php'] as $header => $key) {
            if (preg_match('/^\s*' . preg_quote($header, '/') . '\s*:\s*(.+)$/mi', $style, $match) === 1) {
                $metadata[$key] = trim($match[1]);
            }
        }
        return $metadata;
    }

    /** @param list<string> $files @param array<string,string> $contents @param array<string,mixed>|null $themeJson @return array<string,mixed> */
    private function features(array $files, array $contents, ?array $themeJson): array
    {
        $hasBlockTemplates = in_array('templates/index.html', $files, true)
            || in_array('block-templates/index.html', $files, true)
            || $this->contains($contents, '/<!--\s*\/?wp:[a-z0-9_-]+(?:\s[^>]*)?\s*-->/i');
        $hasClassicTemplates = in_array('index.php', $files, true)
            || in_array('functions.php', $files, true)
            || count(array_filter($files, static fn (string $file): bool => preg_match('/^(single|page|archive|404|header|footer|sidebar)(-[^\/]+)?\.php$/', basename($file)) === 1)) > 0;

        $phpFiles = array_values(array_filter($files, static fn (string $file): bool => strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php'));
        $htmlFiles = array_values(array_filter($files, static fn (string $file): bool => in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['html', 'htm'], true)));

        return [
            'style_css' => in_array('style.css', $files, true),
            'theme_json' => $themeJson !== null,
            'block_templates' => $hasBlockTemplates,
            'classic_templates' => $hasClassicTemplates,
            'functions_php' => in_array('functions.php', $files, true),
            'template_parts' => $this->hasDirectory($files, ['parts', 'template-parts']),
            'patterns' => $this->hasDirectory($files, ['patterns']),
            'style_variations' => $this->hasDirectory($files, ['styles']),
            'php_file_count' => count($phpFiles),
            'html_file_count' => count($htmlFiles),
            'theme_json_settings' => $this->countArray($themeJson['settings'] ?? null),
            'theme_json_styles' => $this->countArray($themeJson['styles'] ?? null),
            'theme_json_template_parts' => $this->countArray($themeJson['templateParts'] ?? null),
            'theme_json_custom_templates' => $this->countArray($themeJson['customTemplates'] ?? null),
            'theme_json_patterns' => $this->countArray($themeJson['patterns'] ?? null),
            'wp_block_markup_count' => $this->countMatches($contents, '/<!--\s*\/?wp:[^>]+-->/i'),
            'hook_count' => $this->countMatches($contents, '/\b(?:add_action|add_filter|do_action|apply_filters|remove_action|remove_filter)\s*\(/i'),
            'template_tag_count' => $this->countMatches($contents, '/\b(?:get_header|get_footer|get_sidebar|get_template_part|the_title|the_content|have_posts|the_post|wp_head|wp_footer|wp_body_open)\s*\(/i'),
            'shortcode_count' => $this->countMatches($contents, '/\b(?:add_shortcode|do_shortcode)\s*\(/i') + $this->countMatches($contents, '/\[[a-z][a-z0-9_-]*(?:\s[^\]]*)?\]/i'),
            'dynamic_block_count' => $this->countMatches($contents, '/\b(?:register_block_type|render_callback)\b/i'),
            'external_url_count' => $this->countMatches($contents, '/https?:\/\//i'),
        ];
    }

    /** @param array<string,string> $contents @return list<string> */
    private function dependencies(array $contents): array
    {
        $joined = implode("\n", $contents);
        $dependencies = [];
        foreach ([
            'woocommerce' => '/\b(?:woocommerce|WC_|is_product|shop_loop)\b/i',
            'elementor' => '/\b(?:elementor|ElementorPro)\b/i',
            'acf' => '/\b(?:acf_|get_field|the_field)\s*\(/i',
            'divi' => '/\b(?:ET_Builder|ElegantThemes|et_pb_)\b/i',
            'wpbakery' => '/\b(?:Visual Composer|WPBakery|vc_)/i',
            'custom-shortcodes' => '/\b(?:add_shortcode|do_shortcode)\s*\(/i',
            'custom-blocks' => '/\b(?:register_block_type|render_callback)\b/i',
        ] as $name => $pattern) {
            if (preg_match($pattern, $joined) === 1) $dependencies[] = $name;
        }
        sort($dependencies, SORT_STRING);
        return $dependencies;
    }

    /** @param array<string,mixed> $features */
    private function type(array $features): WordPressThemeType
    {
        // theme.json can also be used by a Classic Theme for editor settings;
        // Block Theme classification requires block templates or block markup.
        $block = (bool)$features['block_templates'];
        $classic = (bool)$features['classic_templates'] || (bool)$features['functions_php'];
        return match (true) {
            $block && $classic => WordPressThemeType::Hybrid,
            $block => WordPressThemeType::Block,
            $classic => WordPressThemeType::Classic,
            default => WordPressThemeType::Unknown,
        };
    }

    /** @param array<string,mixed> $features @param list<string> $dependencies @param list<array{code:string,severity:string,message:string,path?:string}> $issues */
    private function addSemanticIssues(WordPressThemeType $type, array $features, array $dependencies, array &$issues): void
    {
        if (!(bool)$features['style_css']) {
            $issues[] = $this->issue('theme.style_css_missing', 'warning', 'style.css was not found; WordPress metadata and baseline styles may be unavailable.');
        }
        if ($type === WordPressThemeType::Unknown) {
            $issues[] = $this->issue('theme.type_unknown', 'blocker', 'The directory does not contain a recognizable WordPress Classic or Block Theme structure.');
        }
        if ((bool)$features['functions_php']) {
            $issues[] = $this->issue('theme.php_runtime_required', 'warning', 'functions.php requires static analysis or isolated conversion; it must not run in NanoPino public runtime.');
        }
        if ((int)$features['hook_count'] > 0) {
            $issues[] = $this->issue('theme.wordpress_hooks_detected', 'warning', 'WordPress hooks require explicit NanoPino mappings.');
        }
        if ((int)$features['template_tag_count'] > 0) {
            $issues[] = $this->issue('theme.wordpress_template_tags_detected', 'warning', 'WordPress template tags require data-binding conversion.');
        }
        if ((int)$features['shortcode_count'] > 0) {
            $issues[] = $this->issue('theme.shortcodes_detected', 'warning', 'Shortcodes require approved block or adapter mappings.');
        }
        if ((int)$features['dynamic_block_count'] > 0) {
            $issues[] = $this->issue('theme.dynamic_blocks_detected', 'warning', 'Dynamic blocks require server-side adapters and cannot be treated as static markup.');
        }
        foreach ($dependencies as $dependency) {
            $issues[] = $this->issue('theme.plugin_dependency_' . $dependency, 'warning', 'Theme behavior depends on a WordPress plugin or extension: ' . $dependency . '.');
        }
    }

    /** @param array<string,mixed> $features @param list<string> $dependencies */
    private function scores(WordPressThemeType $type, array $features, array $dependencies, int $blockers): array
    {
        $blockConversion = match ($type) {
            WordPressThemeType::Block => 86,
            WordPressThemeType::Hybrid => 68,
            WordPressThemeType::Classic => 30,
            WordPressThemeType::Unknown => 0,
        };
        $classicConversion = match ($type) {
            WordPressThemeType::Classic => 70,
            WordPressThemeType::Hybrid => 55,
            WordPressThemeType::Block => 20,
            WordPressThemeType::Unknown => 0,
        };
        $penalty = min(45, count($dependencies) * 8 + ((int)$features['hook_count'] > 0 ? 8 : 0) + ((int)$features['shortcode_count'] > 0 ? 8 : 0));

        return [
            'recognition' => $type === WordPressThemeType::Unknown ? 0 : 100,
            'block_conversion' => max(0, $blockConversion - $penalty - ($blockers * 15)),
            'classic_conversion' => max(0, $classicConversion - $penalty - ($blockers * 15)),
            'direct_runtime' => 5,
            'estimated_import_readiness' => max(0, min(100, $type === WordPressThemeType::Unknown ? 0 : 82 - $penalty - ($blockers * 20))),
        ];
    }

    /** @param array<string,mixed> $features @param list<string> $dependencies @param list<array{code:string,severity:string,message:string,path?:string}> $issues @return list<string> */
    private function nextActions(WordPressThemeType $type, array $features, array $dependencies, array $issues): array
    {
        $actions = [];
        if ($type === WordPressThemeType::Block || $type === WordPressThemeType::Hybrid) $actions[] = 'Convert theme.json, Block Markup, template parts and patterns to NanoPino contracts.';
        if ($type === WordPressThemeType::Classic || $type === WordPressThemeType::Hybrid) $actions[] = 'Run isolated Classic Theme conversion and map WordPress template tags to NanoPino data sources.';
        if ((int)$features['external_url_count'] > 0) $actions[] = 'Review external URLs, fonts and assets before packaging.';
        if ($dependencies !== []) $actions[] = 'Resolve plugin dependencies with explicit adapters or mark the related features unsupported.';
        if ((int)$features['hook_count'] > 0) $actions[] = 'Create a hook mapping report; do not execute functions.php in production.';
        if ($issues !== []) $actions[] = 'Resolve blocker and warning findings before import or activation.';
        if ($actions === []) $actions[] = 'Theme is not recognizable; request a valid WordPress theme directory or archive.';
        return array_values(array_unique($actions));
    }

    /** @param array<string,string> $contents */
    private function contains(array $contents, string $pattern): bool
    {
        foreach ($contents as $content) if (preg_match($pattern, $content) === 1) return true;
        return false;
    }

    /** @param array<string,string> $contents */
    private function countMatches(array $contents, string $pattern): int
    {
        $count = 0;
        foreach ($contents as $content) $count += preg_match_all($pattern, $content, $matches) ?: 0;
        return $count;
    }

    /** @param list<string> $files @param list<string> $directories */
    private function hasDirectory(array $files, array $directories): bool
    {
        foreach ($files as $file) foreach ($directories as $directory) if (str_starts_with($file, $directory . '/')) return true;
        return false;
    }

    private function countArray(mixed $value): int
    {
        return is_array($value) ? count($value) : 0;
    }

    /** @return array{code:string,severity:string,message:string,path?:string} */
    private function issue(string $code, string $severity, string $message, ?string $path = null): array
    {
        $issue = ['code' => $code, 'severity' => $severity, 'message' => $message];
        if ($path !== null && $path !== '') $issue['path'] = $path;
        return $issue;
    }

    private function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    private function inside(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path, $root . '/');
    }
}
