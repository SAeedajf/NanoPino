<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentValidator;
use App\com_pinoox_cms\Cms\Block\Document\BlockNode;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Static Classic Theme conversion worker.
 *
 * Theme PHP is treated as untrusted text. This boundary never includes, evals,
 * bootstraps or calls a WordPress runtime; "isolation" means only approved
 * static fragments can cross into a native BlockDocument.
 */
final class WordPressClassicThemeConversionWorker
{
    private const MAX_TEMPLATES = 100;
    private const MAX_FILE_BYTES = 2_000_000;
    private const TEMPLATE_BASENAMES = '/^(?:index|front-page|home|single(?:-[a-z0-9_-]+)?|page(?:-[a-z0-9_-]+)?|archive(?:-[a-z0-9_-]+)?|author(?:-[a-z0-9_-]+)?|category(?:-[a-z0-9_-]+)?|tag(?:-[a-z0-9_-]+)?|taxonomy(?:-[a-z0-9_-]+)?|attachment(?:-[a-z0-9_-]+)?|singular|date|search|404|comments|header(?:-[a-z0-9_-]+)?|footer(?:-[a-z0-9_-]+)?|sidebar(?:-[a-z0-9_-]+)?)\.php$/i';

    public function __construct(
        private readonly WordPressThemeScanner $scanner = new WordPressThemeScanner(),
        private readonly WordPressBlockMarkupParser $parser = new WordPressBlockMarkupParser(),
        private readonly ?BlockDocumentValidator $validator = null,
        private readonly int $maxTemplates = self::MAX_TEMPLATES,
        private readonly int $maxFileBytes = self::MAX_FILE_BYTES,
    ) {
        if ($this->maxTemplates < 1 || $this->maxFileBytes < 1) {
            throw new InvalidArgumentException('Classic Theme conversion limits must be positive.');
        }
    }

    public function convert(string $themeRoot): WordPressClassicThemeConversionReport
    {
        try {
            $scan = $this->scanner->scan($themeRoot);
        } catch (Throwable $error) {
            return new WordPressClassicThemeConversionReport($themeRoot, WordPressThemeType::Unknown, issues: [[
                'code' => 'classic.theme_root_invalid',
                'severity' => 'blocker',
                'message' => $error->getMessage(),
            ]]);
        }

        // Preserve scanner blockers and warnings in the conversion report;
        // conversion must never silently downgrade an intake failure.
        $issues = $scan->issues;
        $unsupported = [];
        $type = $scan->type;
        if (!in_array($type, [WordPressThemeType::Classic, WordPressThemeType::Hybrid], true)) {
            $issues[] = $this->issue('classic.theme_type_unsupported', 'blocker', 'Classic conversion requires a Classic or Hybrid WordPress Theme.', null);
            return new WordPressClassicThemeConversionReport($scan->root, $type, issues: $issues, metadata: $scan->metadata, features: $scan->features, dependencies: $scan->dependencies);
        }

        $this->addScanFeatures($scan, $unsupported);
        $templates = [];
        $bindingSuggestions = [];
        $candidates = $this->templateCandidates($scan->files, $issues);
        foreach ($candidates as $relative) {
            $path = $scan->root . '/' . $relative;
            try {
                $content = $this->read($path);
                $templates[] = $this->convertTemplate($relative, $content, $issues, $unsupported, $bindingSuggestions);
            } catch (Throwable $error) {
                $issues[] = $this->issue('classic.template_conversion_failed', 'blocker', $error->getMessage(), $relative);
            }
        }

        foreach ($scan->dependencies as $dependency) {
            $unsupported[] = new WordPressClassicUnsupportedFeature(
                'classic.plugin_dependency_' . $dependency,
                'plugin-dependency',
                'warning',
                'Theme behavior depends on a plugin or extension that has no Classic adapter.',
                'theme',
                1,
                'Install or implement an explicit NanoPino adapter before activation.',
            );
        }

        $features = $scan->features;
        $features['candidate_template_count'] = count($candidates);
        $features['converted_template_count'] = count($templates);
        $features['unsupported_feature_count'] = count($unsupported);
        $features['runtime_boundary'] = 'static-only';
        return new WordPressClassicThemeConversionReport(
            $scan->root,
            $type,
            templates: $templates,
            unsupportedFeatures: $this->uniqueFeatures($unsupported),
            issues: $issues,
            metadata: $scan->metadata,
            features: $features,
            dependencies: $scan->dependencies,
            bindingSuggestions: $bindingSuggestions,
        );
    }

    /** @param list<string> $files @param list<array{code:string,severity:string,message:string,path?:string}> $issues @return list<string> */
    private function templateCandidates(array $files, array &$issues): array
    {
        $candidates = [];
        foreach ($files as $relative) {
            if (strtolower(pathinfo($relative, PATHINFO_EXTENSION)) !== 'php') continue;
            $base = basename($relative);
            if ($base === 'functions.php') continue;
            $isTemplatePart = str_starts_with(strtolower($relative), 'template-parts/');
            $isRootTemplate = !str_contains($relative, '/') && preg_match(self::TEMPLATE_BASENAMES, $base) === 1;
            if (!$isTemplatePart && !$isRootTemplate) continue;
            $candidates[] = $relative;
        }
        sort($candidates, SORT_STRING);
        if (count($candidates) > $this->maxTemplates) {
            $issues[] = $this->issue('classic.template_count_exceeded', 'blocker', 'Classic Theme template count exceeds the conversion limit.', 'template-parts');
            return array_slice($candidates, 0, $this->maxTemplates);
        }
        return $candidates;
    }

    /** @param list<array{code:string,severity:string,message:string,path?:string}> $issues @param list<WordPressClassicUnsupportedFeature> $unsupported @param array<string,list<string>> $bindingSuggestions */
    private function convertTemplate(string $relative, string $content, array &$issues, array &$unsupported, array &$bindingSuggestions): WordPressConvertedTemplate
    {
        $static = $this->stripPhp($relative, $content, $issues, $unsupported, $bindingSuggestions);
        $templateIssues = [];
        try {
            if (preg_match('/<!--\s*\/?wp:/i', $static) === 1) {
                $parsed = $this->parser->parse($static);
                $this->validator?->validate($parsed->document);
                foreach ($parsed->issues as $issue) $templateIssues[] = $this->withPath($issue, $relative);
                foreach ($parsed->unsupportedBlocks as $block) {
                    $unsupported[] = new WordPressClassicUnsupportedFeature(
                        'classic.block_' . str_replace('/', '_', $block),
                        'block',
                        'warning',
                        'Classic template contains a WordPress block without a direct native mapping.',
                        $relative,
                        1,
                        'Add a registered NanoPino block adapter or keep the structural section fallback.',
                    );
                }
                return new WordPressConvertedTemplate('classic-template', strtolower(pathinfo($relative, PATHINFO_FILENAME)), $relative, $parsed->document, $templateIssues);
            }

            $document = $this->staticHtml($relative, $static, $templateIssues, $unsupported);
            $this->validator?->validate($document);
            return new WordPressConvertedTemplate('classic-template', strtolower(pathinfo($relative, PATHINFO_FILENAME)), $relative, $document, $templateIssues);
        } catch (Throwable $error) {
            $templateIssues[] = $this->issue('classic.static_markup_failed', 'blocker', $error->getMessage(), $relative);
            return new WordPressConvertedTemplate('classic-template', strtolower(pathinfo($relative, PATHINFO_FILENAME)), $relative, new BlockDocument(1, []), $templateIssues);
        }
    }

    /** @param list<array{code:string,severity:string,message:string,path?:string}> $issues @param list<WordPressClassicUnsupportedFeature> $unsupported @param array<string,list<string>> $bindingSuggestions */
    private function stripPhp(string $relative, string $content, array &$issues, array &$unsupported, array &$bindingSuggestions): string
    {
        $phpTags = preg_match_all('/<\?(?:php|=)?/i', $content, $matches) ?: 0;
        if ($phpTags > 0) {
            // A PHP-only tail without a closing tag is valid PHP and is common in
            // production WordPress templates. The converter strips it as
            // untrusted text; treating it as an unclosed boundary incorrectly
            // blocked standard themes such as Twenty Seventeen.
            $unsupported[] = new WordPressClassicUnsupportedFeature(
                'classic.php_runtime',
                'php-runtime',
                'warning',
                'PHP template logic was removed from the static conversion and was not executed.',
                $relative,
                $phpTags,
                'Map the behavior to a native data source, Builder block or approved adapter.',
            );
        }
        $this->detectUnsupportedPhp($relative, $content, $unsupported, $bindingSuggestions);
        return preg_replace('/<\?(?:php|=)?[\s\S]*?(?:\?>|$)/i', '', $content) ?? '';
    }

    /** @param list<WordPressClassicUnsupportedFeature> $unsupported @param array<string,list<string>> $bindingSuggestions */
    private function detectUnsupportedPhp(string $relative, string $content, array &$unsupported, array &$bindingSuggestions): void
    {
        foreach ([
            'classic.template_tags' => ['presentation', '/\b(?:get_header|get_footer|get_sidebar|get_template_part|the_title|the_content|the_excerpt|the_permalink|get_permalink|the_post_thumbnail|have_posts|the_post|wp_nav_menu|dynamic_sidebar|get_search_form|comments_template|paginate_links|wp_link_pages)\s*\(/i', 'Map template tags to validated NanoPino data bindings.'],
            'classic.hooks' => ['runtime-hook', '/\b(?:add_action|add_filter|do_action|apply_filters|remove_action|remove_filter)\s*\(/i', 'Create an explicit hook adapter; do not run WordPress hooks in the public runtime.'],
            'classic.shortcodes' => ['shortcode', '/\b(?:add_shortcode|do_shortcode)\s*\(/i', 'Convert the shortcode to a registered block or approved adapter.'],
            'classic.dynamic_include' => ['php-runtime', '/\b(?:include|include_once|require|require_once|eval)\s*(?:\(|["\'])/i', 'Resolve the dependency statically and import only approved output.'],
            'classic.theme_registration' => ['theme-registration', '/\b(?:wp_enqueue_style|wp_enqueue_script|register_nav_menus|register_sidebar|add_theme_support)\s*\(/i', 'Replace theme registration with native asset, navigation or capability configuration.'],
        ] as $code => [$category, $pattern, $recommendation]) {
            $count = preg_match_all($pattern, $content, $matches) ?: 0;
            if ($count > 0) $unsupported[] = new WordPressClassicUnsupportedFeature($code, $category, 'warning', 'Classic PHP contains runtime behavior that was not executed.', $relative, $count, $recommendation);
        }
        foreach ([
            'the_title' => 'content.current.title',
            'the_content' => 'content.current.body',
            'the_excerpt' => 'content.current.excerpt',
            'the_permalink' => 'content.current.url',
            'get_permalink' => 'content.current.url',
            'the_post_thumbnail' => 'media.featured',
            'have_posts' => 'content.items',
            'the_post' => 'content.current',
            'wp_nav_menu' => 'navigation.primary',
            'dynamic_sidebar' => 'navigation.sidebar',
            'get_search_form' => 'content.search',
            'comments_template' => 'content.comments',
            'paginate_links' => 'pagination.current',
            'wp_link_pages' => 'pagination.current',
        ] as $tag => $binding) {
            if (preg_match('/\b' . preg_quote($tag, '/') . '\s*\(/i', $content) === 1) {
                $bindingSuggestions[$tag] = array_values(array_unique([...(array)($bindingSuggestions[$tag] ?? []), $binding]));
            }
        }
        $shortcodeCount = preg_match_all('/\[[a-z][a-z0-9_-]*(?:\s[^\]]*)?\]/i', $content, $matches) ?: 0;
        if ($shortcodeCount > 0) $unsupported[] = new WordPressClassicUnsupportedFeature('classic.inline_shortcodes', 'shortcode', 'warning', 'Template contains shortcode syntax that requires an explicit adapter.', $relative, $shortcodeCount, 'Convert the shortcode to a native block or approved adapter.');
    }

    /** @param list<array{code:string,severity:string,message:string,path?:string}> $issues @param list<WordPressClassicUnsupportedFeature> $unsupported */
    private function staticHtml(string $relative, string $markup, array &$issues, array &$unsupported): BlockDocument
    {
        if (!class_exists(DOMDocument::class)) throw new RuntimeException('Static HTML conversion requires the DOM extension.');
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $dom->loadHTML('<!DOCTYPE html><html><body>' . $markup . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if ($loaded !== true || $dom->documentElement === null) throw new RuntimeException('Classic static HTML could not be parsed.');
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body === null) throw new RuntimeException('Classic static HTML body is missing.');
        $ordinal = 0;
        return new BlockDocument(1, $this->htmlChildren($body, $relative, $issues, $unsupported, $ordinal));
    }

    /** @return list<BlockNode> */
    private function htmlChildren(DOMNode $parent, string $relative, array &$issues, array &$unsupported, int &$ordinal): array
    {
        $nodes = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMText) {
                $text = $this->text($child->textContent);
                if ($text !== '') $nodes[] = $this->paragraph($relative, $text, $ordinal);
                continue;
            }
            if (!$child instanceof DOMElement) continue;
            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script', 'iframe', 'object', 'embed', 'form'], true)) {
                $unsupported[] = new WordPressClassicUnsupportedFeature('classic.unsafe_html_' . $tag, 'markup', 'blocker', 'Classic template contains active markup that cannot cross the static conversion boundary.', $relative, 1, 'Replace it with a reviewed native block or explicit integration.');
                $issues[] = $this->issue('classic.unsafe_markup', 'blocker', 'Active HTML was excluded from the converted document.', $relative);
                continue;
            }
            if ($tag === 'style') {
                $unsupported[] = new WordPressClassicUnsupportedFeature('classic.inline_style', 'markup', 'warning', 'Inline style markup is not copied into the native document.', $relative, 1, 'Use the Phase 7 asset manifest or native design tokens.');
                continue;
            }
            if ($tag === 'img') {
                $unsupported[] = new WordPressClassicUnsupportedFeature('classic.image_markup', 'markup', 'warning', 'Image markup needs an explicit media adapter.', $relative, 1, 'Import the media asset and map it to a native media block.');
                $alt = $this->text($child->getAttribute('alt'));
                if ($alt !== '') $nodes[] = $this->paragraph($relative, $alt, $ordinal);
                continue;
            }
            if (preg_match('/^h([1-6])$/', $tag, $heading) === 1) {
                $text = $this->text($child->textContent);
                if ($text !== '') $nodes[] = new BlockNode($this->id($relative, ++$ordinal), 'core/heading', 2, ['text' => $text, 'level' => (int)$heading[1]]);
                continue;
            }
            if ($tag === 'p') {
                $text = $this->text($child->textContent);
                if ($text !== '') $nodes[] = $this->paragraph($relative, $text, $ordinal);
                continue;
            }
            if ($tag === 'a') {
                $label = $this->text($child->textContent);
                if ($label === '') continue;
                $url = trim($child->getAttribute('href'));
                if (!$this->safeUrl($url)) {
                    $issues[] = $this->issue('classic.link_url_invalid', 'warning', 'Unsafe or missing classic link URL was replaced with #.', $relative);
                    $url = '#';
                }
                $nodes[] = new BlockNode($this->id($relative, ++$ordinal), 'core/button', 1, ['label' => $label, 'url' => $url, 'newTab' => $child->getAttribute('target') === '_blank']);
                continue;
            }
            if (in_array($tag, ['section', 'main', 'article', 'aside', 'header', 'footer', 'nav', 'div'], true)) {
                $children = $this->htmlChildren($child, $relative, $issues, $unsupported, $ordinal);
                $attributes = ['tag' => $tag === 'nav' ? 'section' : $tag, 'className' => $this->className($child->getAttribute('class'))];
                $nodes[] = new BlockNode($this->id($relative, ++$ordinal), 'core/section', 1, $attributes, [], [], $children);
                continue;
            }
            $unsupported[] = new WordPressClassicUnsupportedFeature('classic.html_' . $tag, 'markup', 'warning', 'HTML element was traversed but has no direct native mapping.', $relative, 1, 'Review the structural output or add a native block mapping.');
            $nodes = array_merge($nodes, $this->htmlChildren($child, $relative, $issues, $unsupported, $ordinal));
        }
        return $nodes;
    }

    /** @param list<WordPressClassicUnsupportedFeature> $unsupported */
    private function addScanFeatures(WordPressThemeScanResult $scan, array &$unsupported): void
    {
        $featureMap = [
            'hook_count' => ['classic.hooks', 'runtime-hook', 'warning', 'WordPress hooks require explicit mapping.', 'Create a reviewed native hook adapter.'],
            'template_tag_count' => ['classic.template_tags', 'presentation', 'warning', 'WordPress template tags require data-binding conversion.', 'Map tags to registered native data sources.'],
            'shortcode_count' => ['classic.shortcodes', 'shortcode', 'warning', 'Shortcodes require an approved block or adapter.', 'Convert each shortcode to a registered native capability.'],
            'dynamic_block_count' => ['classic.dynamic_blocks', 'dynamic-block', 'warning', 'Dynamic blocks require a server-side adapter.', 'Provide a bounded native renderer or keep the feature deferred.'],
        ];
        foreach ($featureMap as $key => [$code, $category, $severity, $message, $recommendation]) {
            $count = (int)($scan->features[$key] ?? 0);
            if ($count > 0) $unsupported[] = new WordPressClassicUnsupportedFeature($code, $category, $severity, $message, 'theme', $count, $recommendation);
        }
    }

    /** @param list<WordPressClassicUnsupportedFeature> $features @return list<WordPressClassicUnsupportedFeature> */
    private function uniqueFeatures(array $features): array
    {
        $unique = [];
        foreach ($features as $feature) {
            $key = implode("\0", [$feature->code, $feature->path]);
            if (!isset($unique[$key])) $unique[$key] = $feature;
            else {
                $old = $unique[$key];
                $unique[$key] = new WordPressClassicUnsupportedFeature($old->code, $old->category, $old->severity, $old->message, $old->path, $old->occurrences + $feature->occurrences, $old->recommendation);
            }
        }
        return array_values($unique);
    }

    private function read(string $file): string
    {
        $size = filesize($file);
        if (!is_int($size) || $size < 0 || $size > $this->maxFileBytes) throw new RuntimeException('Classic template exceeds the conversion byte limit.');
        $content = file_get_contents($file);
        if (!is_string($content)) throw new RuntimeException('Classic template could not be read.');
        return $content;
    }

    private function paragraph(string $relative, string $text, int &$ordinal): BlockNode
    {
        return new BlockNode($this->id($relative, ++$ordinal), 'core/paragraph', 1, ['text' => substr($text, 0, 20_000)]);
    }

    private function text(string $value): string
    {
        return trim((string)(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? $value));
    }

    private function className(string $value): string
    {
        return substr(trim((string)(preg_replace('/[^A-Za-z0-9 _-]/', '', $value) ?? '')), 0, 255);
    }

    private function safeUrl(string $url): bool
    {
        return $url !== '' && strlen($url) <= 2000 && preg_match('/^(?:https?:\/\/|\/|#|\?|[A-Za-z0-9][A-Za-z0-9._~:\/?#\[\]@!$&\'()*+,;=%-]*)$/i', $url) === 1 && preg_match('/^(?:javascript|data|vbscript):/i', $url) !== 1;
    }

    private function id(string $relative, int $ordinal): string
    {
        return 'classic-' . substr(hash('sha256', $relative . "\0" . $ordinal), 0, 16);
    }

    /** @return array{code:string,severity:string,message:string,path?:string} */
    private function issue(string $code, string $severity, string $message, ?string $path = null): array
    {
        $issue = ['code' => $code, 'severity' => $severity, 'message' => $message];
        if ($path !== null && $path !== '') $issue['path'] = $path;
        return $issue;
    }

    /** @param array<string,mixed> $issue @return array<string,mixed> */
    private function withPath(array $issue, string $path): array
    {
        $issue['path'] = $path;
        return $issue;
    }
}
