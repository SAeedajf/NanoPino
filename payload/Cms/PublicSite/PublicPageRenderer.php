<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\PublicSite;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentLoader;
use App\com_pinoox_cms\Cms\Block\Render\BlockDocumentRenderer;
use App\com_pinoox_cms\Cms\Block\Render\BlockRenderContext;
use App\com_pinoox_cms\Cms\Builder\BuilderPublishedResolver;
use App\com_pinoox_cms\Cms\Builder\BuilderTarget;
use App\com_pinoox_cms\Cms\Builder\BuilderTargetType;
use App\com_pinoox_cms\Cms\Content\ContentRecord;
use App\com_pinoox_cms\Cms\Builder\GlobalBlock\GlobalBlockReferenceExpander;
use App\com_pinoox_cms\Cms\Security\Output\RichTextRenderBoundary;
use App\com_pinoox_cms\Cms\Security\Output\StrictRichTextSanitizer;
use App\com_pinoox_cms\Cms\Theme\Design\DesignTokenCssCompiler;
use App\com_pinoox_cms\Cms\Theme\Design\ResolvedDesign;

final readonly class PublicPageRenderer
{
    public function __construct(
        private RichTextRenderBoundary $richText = new RichTextRenderBoundary(new StrictRichTextSanitizer()),
        private ?BlockDocumentLoader $blockLoader = null,
        private ?BlockDocumentRenderer $blockRenderer = null,
        private ?BuilderPublishedResolver $publishedBuilder = null,
        private ?GlobalBlockReferenceExpander $globalBlocks = null,
        private ?ResolvedDesign $themeDesign = null,
        private DesignTokenCssCompiler $designCss = new DesignTokenCssCompiler(),
    ) {}

    /** @param list<array{name:string,url:string}> $taxonomyTerms */
    public function render(
        ContentRecord $record,
        string $canonicalPath,
        array $taxonomyTerms = [],
        ?PublicNavigation $navigation = null,
        ?string $mountPath = null,
        ?string $locale = null,
    ): string
    {
        $copy = new PublicSiteCopy($locale ?? $record->locale);
        $title = $this->escape($record->title !== '' ? $record->title : 'NanoPino');
        $excerpt = trim($record->excerpt);
        $description = $this->escape($excerpt !== '' ? $excerpt : $record->title);
        $bodyHtml = $this->renderBody($record);
        $publishedAt = $record->publishedAt !== null
            ? $this->escape($record->publishedAt)
            : '';
        $canonical = $this->escape($canonicalPath);

        if ($bodyHtml === '') {
            $bodyHtml = '<p class="public-page__empty">' . $this->escape($copy->text('empty_page')) . '</p>';
        }
        $termsHtml = $this->renderTaxonomyTerms($taxonomyTerms, $copy);

        $date = $publishedAt !== ''
            ? '<time datetime="' . $publishedAt . '">' . $this->escape($copy->text('published_at')) . ': ' . $publishedAt . '</time>'
            : '';

        $lang = $this->escape($copy->language());
        $direction = $copy->direction();
        $kind = $copy->text($record->type === 'post' ? 'post' : 'page');
        return $this->normalizeMarkup('<!doctype html>\n'
            . '<html lang="' . $lang . '" dir="' . $direction . '">\n<head>\n'
            . '<meta charset="utf-8">\n'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">\n'
            . '<meta name="description" content="' . $description . '">\n'
            . '<link rel="canonical" href="' . $canonical . '">\n'
            . '<title>' . $title . ' | NanoPino</title>\n'
            . '<style>' . $this->styles() . '</style>\n'
            . '</head>\n<body>\n'
            . '<main id="public-main" class="public-page">\n'
            . ($navigation?->render($mountPath, $canonicalPath, $copy->language()) ?? '')
            . '<article class="public-page__article">\n'
            . '<header class="public-page__header">\n'
            . '<p class="public-page__eyebrow">NanoPino · ' . $this->escape($kind) . '</p>\n'
            . '<h1>' . $title . '</h1>\n'
            . ($excerpt !== '' ? '<p class="public-page__excerpt">' . $this->escape($excerpt) . '</p>\n' : '')
            . ($date !== '' ? '<p class="public-page__meta">' . $date . '</p>\n' : '')
            . $termsHtml
            . '</header>\n'
            . '<div id="public-primary-content" class="public-page__content">' . $bodyHtml . '</div>\n'
            . '</article>\n'
            . '<footer class="public-page__footer">NanoPino</footer>\n'
            . '</main>\n</body>\n</html>');
    }

    /** @param list<array{name:string,url:string}> $taxonomyTerms */
    private function renderTaxonomyTerms(array $taxonomyTerms, PublicSiteCopy $copy): string
    {
        if ($taxonomyTerms === []) return '';
        $links = '';
        foreach ($taxonomyTerms as $term) {
            $links .= '<a class="public-page__term" href="' . $this->escape($term['url']) . '">' . $this->escape($term['name']) . '</a>';
        }
        return '<nav class="public-page__terms" aria-label="' . $this->escape($copy->text('taxonomy_aria')) . '">' . $links . '</nav>\n';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    private function normalizeMarkup(string $markup): string
    {
        return str_replace('\\n', "\n", $markup);
    }

    private function renderBody(ContentRecord $record): string
    {
        $builderHtml = $this->renderPublishedBuilder($record);
        if ($builderHtml !== null) {
            return $builderHtml;
        }

        $rawDocument = $record->document;
        if (isset($rawDocument['blocks']) && is_array($rawDocument['blocks'])) {
            $blockHtml = $this->renderBlockDocument([
                'version' => (int)($rawDocument['version'] ?? 1),
                'blocks' => $rawDocument['blocks'],
            ], $record);
            if ($blockHtml !== null) {
                return $blockHtml;
            }
        }

        $body = $rawDocument['content'] ?? '';
        return $this->richText->render(is_string($body) ? $body : '')->value();
    }

    private function renderPublishedBuilder(ContentRecord $record): ?string
    {
        if (
            $this->publishedBuilder === null
            || $this->blockLoader === null
            || $this->blockRenderer === null
        ) {
            return null;
        }

        try {
            $published = $this->publishedBuilder->resolve(new BuilderTarget(
                $record->siteId,
                BuilderTargetType::Content,
                $record->type . ':' . $record->id,
                $record->locale,
            ));
            if ($published === null) {
                return null;
            }

            return $this->renderBlockDocument($published->document->toArray(), $record);
        } catch (\Throwable) {
            // A broken optional Builder document must not take a published page down.
            return null;
        }
    }

    /** @param array<string,mixed> $rawDocument */
    private function renderBlockDocument(array $rawDocument, ContentRecord $record): ?string
    {
        if ($this->blockLoader === null || $this->blockRenderer === null) {
            return null;
        }

        try {
            $document = $this->blockLoader->fromArray($rawDocument);
            if ($this->globalBlocks !== null) {
                $document = $this->globalBlocks->expand($document, $record->siteId);
            }
            $rendered = $this->blockRenderer->render(
                $document,
                new BlockRenderContext($record->siteId, $record->id, $record->locale),
            );
            $html = trim($rendered->html);
            return $html !== '' ? $html : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function styles(): string
    {
        $themeCss = $this->themeDesign !== null ? $this->designCss->compile($this->themeDesign) : '';
        return $themeCss . 'body{margin:0}.public-page{min-height:100vh;background:var(--cms-colors-background,#f5f7fb);color:var(--cms-colors-text,#172033);font-family:var(--cms-fontFamilies-body,Vazir,Vazirmatn,IRANSans,system-ui,sans-serif);padding:clamp(1rem,4vw,4rem)}'
            . '.public-navigation{max-width:900px;margin:0 auto 1rem;padding:.75rem 1rem;border:1px solid #dce5f6;border-radius:16px;background:#fff;box-shadow:0 8px 24px rgba(26,39,67,.06)}.public-navigation ul{display:flex;align-items:center;flex-wrap:wrap;gap:.35rem;margin:0;padding:0;list-style:none}.public-navigation li{position:relative}.public-navigation a{display:block;padding:.55rem .75rem;border-radius:10px;color:#29466f;text-decoration:none;font-size:.86rem}.public-navigation a:hover{background:#edf4ff;color:#1d5ebd}.public-navigation a:focus-visible{outline:3px solid #8ab0ff;outline-offset:2px}.public-navigation li>ul{display:none;position:absolute;z-index:2;inset-block-start:100%;inset-inline-start:0;min-inline-size:12rem;padding:.35rem;border:1px solid #dce5f6;border-radius:12px;background:#fff;box-shadow:0 12px 28px rgba(26,39,67,.12)}.public-navigation li:hover>ul,.public-navigation li:focus-within>ul{display:grid}.public-navigation li>ul li>ul{inset-block-start:0;inset-inline-start:100%}'
            . '.public-page__article{max-width:var(--cms-containers-content,900px);margin:0 auto;background:#fff;border:1px solid #e5e9f2;border-radius:var(--cms-radius-base,24px);box-shadow:0 18px 50px rgba(26,39,67,.08);overflow:hidden}'
            . '.public-page__header{padding:clamp(1.5rem,5vw,4rem);background:linear-gradient(135deg,#f0f6ff,#fff)}'
            . '.public-page__eyebrow{margin:0 0 1rem;color:var(--cms-colors-primary,#3974d8);font-size:.85rem;font-weight:700;letter-spacing:.02em}'
            . '.public-page h1{margin:0;font-size:clamp(2rem,5vw,4rem);line-height:1.15;letter-spacing:-.04em}'
            . '.public-page__excerpt{max-width:700px;margin:1.2rem 0 0;color:#536078;font-size:1.15rem;line-height:1.9}'
            . '.public-page__meta{margin:1rem 0 0;color:#7c879b;font-size:.85rem}'
            . '.public-page__terms{display:flex;flex-wrap:wrap;gap:.45rem;margin-top:1rem}.public-page__term{padding:.35rem .65rem;border:1px solid #cbdaf5;border-radius:999px;color:#245cae;text-decoration:none;font-size:.78rem;background:#fff}.public-page__term:hover{background:#edf4ff}.public-page__term:focus-visible{outline:3px solid #8ab0ff;outline-offset:2px}'
            . '.public-page__content{padding:clamp(1.5rem,5vw,4rem);font-size:1.08rem;line-height:2}'
            . '.public-page__content h2,.public-page__content h3,.public-page__content h4{line-height:1.35;margin:2rem 0 .7rem}'
            . '.public-page__content p{margin:0 0 1.2rem}.public-page__content a{color:var(--cms-colors-primary,#2463c5)}'
            . '.public-page__content ul,.public-page__content ol{padding-inline-start:1.5rem}'
            . '.public-page__content blockquote{margin:1.5rem 0;padding:1rem 1.2rem;border-inline-start:4px solid var(--cms-colors-primary,#3974d8);background:#f5f8ff;color:#536078}'
            . '.public-page__content .cms-block-section{display:block}.public-page__content .cms-block-button{display:inline-flex;align-items:center;justify-content:center;min-block-size:44px;padding:.7rem 1rem;border-radius:12px;background:var(--cms-colors-primary,#2463c5);color:#fff;text-decoration:none;font-weight:700}.public-page__content .cms-block-button:hover{background:#173d8b}.public-page__content .cms-block-button:focus-visible{outline:3px solid #8ab0ff;outline-offset:3px}'
            . '.public-page__empty{color:#7c879b}.public-page__footer{max-width:900px;margin:1rem auto 0;padding:.75rem 1rem;color:#7c879b;text-align:center;font-size:.8rem}@media(max-width:480px){.public-page{padding:.65rem}.public-page__article{border-radius:16px}}@media(prefers-reduced-motion:reduce){.public-page__content .cms-block-button{transition:none}}';
    }
}
