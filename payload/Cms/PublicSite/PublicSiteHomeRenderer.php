<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\PublicSite;

use App\com_pinoox_cms\Cms\Content\ContentRecord;
use App\com_pinoox_cms\Cms\Theme\Design\DesignTokenCssCompiler;
use App\com_pinoox_cms\Cms\Theme\Design\ResolvedDesign;

final readonly class PublicSiteHomeRenderer
{
    public function __construct(
        private ?ResolvedDesign $themeDesign = null,
        private DesignTokenCssCompiler $designCss = new DesignTokenCssCompiler(),
    ) {}

    /** @param list<ContentRecord> $records */
    public function render(
        array $records,
        string $canonicalPath,
        ?PublicNavigation $navigation = null,
        ?string $mountPath = null,
        ?string $locale = null,
    ): string
    {
        $copy = new PublicSiteCopy($locale ?? PublicSiteContext::DEFAULT_LOCALE);
        $cards = '';
        $visibleCount = 0;
        foreach ($records as $record) {
            $url = PublicContentUrl::path($record, $mountPath);
            if ($url === null) {
                continue;
            }

            $visibleCount++;
            $title = $this->escape($record->title !== '' ? $record->title : 'بدون عنوان');
            $excerpt = trim($record->excerpt);
            $summary = $this->escape($excerpt !== '' ? $excerpt : $copy->text('card_fallback'));
            $kind = $copy->text($record->type === 'post' ? 'post' : 'page');
            $cards .= '<a class="public-site__card" href="' . $this->escape($url) . '">'
                . '<span class="public-site__card-kind">' . $this->escape($kind) . '</span>'
                . '<h2>' . $title . '</h2>'
                . '<p>' . $summary . '</p>'
                . '<span class="public-site__card-link">' . $this->escape($copy->text('view_content')) . ' <span aria-hidden="true">' . ($copy->direction() === 'rtl' ? '←' : '→') . '</span></span>'
                . '</a>';
        }

        if ($cards === '') {
            $cards = '<p class="public-site__empty">' . $this->escape($copy->text('no_published')) . '</p>';
        }

        $canonical = $this->escape($canonicalPath);
        $lang = $this->escape($copy->language());
        $direction = $copy->direction();
        return $this->normalizeMarkup('<!doctype html>\n'
            . '<html lang="' . $lang . '" dir="' . $direction . '">\n<head>\n'
            . '<meta charset="utf-8">\n'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">\n'
            . '<meta name="description" content="' . $this->escape($copy->text('hero_description')) . '">\n'
            . '<link rel="canonical" href="' . $canonical . '">\n'
            . '<title>NanoPino | ' . $this->escape($copy->text('site_eyebrow')) . '</title>\n'
            . '<style>' . $this->styles() . '</style>\n'
            . '</head>\n<body>\n'
            . '<main id="public-main" class="public-site">\n'
            . ($navigation?->render($mountPath, $canonicalPath, $copy->language()) ?? '')
            . '<section class="public-site__hero">'
            . '<p class="public-site__eyebrow">' . $this->escape($copy->text('site_eyebrow')) . '</p>'
            . '<h1>' . $this->escape($copy->text('hero_title')) . '</h1>'
            . '<p>' . $this->escape($copy->text('hero_description')) . '</p>'
            . '</section>\n'
            . '<section id="public-primary-content" class="public-site__content" aria-labelledby="public-site-content-title">'
            . '<div class="public-site__section-head"><div><p class="public-site__eyebrow">' . $this->escape($copy->text('published_eyebrow')) . '</p><h2 id="public-site-content-title">' . $this->escape($copy->text('latest_content')) . '</h2></div>'
            . '<span class="public-site__count">' . $visibleCount . ' ' . $this->escape($copy->text('item_count')) . '</span></div>'
            . '<div class="public-site__cards">' . $cards . '</div>'
            . '</section>\n'
            . '<footer class="public-site__footer">NanoPino</footer>\n'
            . '</main>\n</body>\n</html>');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    private function normalizeMarkup(string $markup): string
    {
        return str_replace('\\n', "\n", $markup);
    }

    private function styles(): string
    {
        $themeCss = $this->themeDesign !== null ? $this->designCss->compile($this->themeDesign) : '';
        return $themeCss . ':root{color-scheme:light;font-family:var(--cms-fontFamilies-body,Vazir,Vazirmatn,IRANSans,system-ui,sans-serif);color:var(--cms-colors-text,#172033);background:var(--cms-colors-background,#f5f7fb)}body{margin:0}'
            . '.public-navigation{max-width:1120px;margin:0 auto 1rem;padding:.75rem 1rem;border:1px solid #dce5f6;border-radius:16px;background:#fff;box-shadow:0 8px 24px rgba(26,39,67,.06)}.public-navigation ul{display:flex;align-items:center;flex-wrap:wrap;gap:.35rem;margin:0;padding:0;list-style:none}.public-navigation li{position:relative}.public-navigation a{display:block;padding:.55rem .75rem;border-radius:10px;color:#29466f;text-decoration:none;font-size:.86rem}.public-navigation a:hover{background:#edf4ff;color:#1d5ebd}.public-navigation a:focus-visible{outline:3px solid #8ab0ff;outline-offset:2px}.public-navigation li>ul{display:none;position:absolute;z-index:2;inset-block-start:100%;inset-inline-start:0;min-inline-size:12rem;padding:.35rem;border:1px solid #dce5f6;border-radius:12px;background:#fff;box-shadow:0 12px 28px rgba(26,39,67,.12)}.public-navigation li:hover>ul,.public-navigation li:focus-within>ul{display:grid}.public-navigation li>ul li>ul{inset-block-start:0;inset-inline-start:100%}'
            . '*{box-sizing:border-box}.public-site{min-height:100vh;padding:clamp(1rem,4vw,4rem);background:radial-gradient(circle at 10% 0%,#e3edff 0,transparent 38%),#f5f7fb}'
            . '.public-site__hero,.public-site__content{max-width:1120px;margin:0 auto}.public-site__hero{padding:clamp(2rem,7vw,6rem) clamp(1.25rem,5vw,5rem);border:1px solid #dce5f6;border-radius:32px;background:linear-gradient(135deg,#152a55,#315fb7 62%,#5d82d7);color:#fff;box-shadow:0 24px 70px rgba(31,62,122,.2)}'
            . '.public-site__eyebrow{margin:0 0 .7rem;color:#bcd2ff;font-size:.8rem;font-weight:700;letter-spacing:.04em}.public-site__hero h1{max-width:720px;margin:0;font-size:clamp(2rem,5vw,4.4rem);line-height:1.2;letter-spacing:-.04em}.public-site__hero>p:last-child{max-width:650px;margin:1.3rem 0 0;color:#e5edff;font-size:1.08rem;line-height:2}'
            . '.public-site__content{margin-top:1.2rem;padding:clamp(1.25rem,4vw,2rem);border:1px solid #e4e9f2;border-radius:var(--cms-radius-base,24px);background:#fff;box-shadow:0 16px 44px rgba(26,39,67,.07)}.public-site__section-head{display:flex;align-items:end;justify-content:space-between;gap:1rem;margin-bottom:1rem}.public-site__section-head h2{margin:0;font-size:clamp(1.35rem,3vw,2rem)}.public-site__section-head .public-site__eyebrow{margin-bottom:.25rem;color:var(--cms-colors-primary,#3974d8)}.public-site__count{color:#6e7b92;font-size:.82rem}'
            . '.public-site__cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:.85rem}.public-site__card{display:flex;min-height:210px;flex-direction:column;padding:1.15rem;border:1px solid #e3e8f2;border-radius:18px;background:linear-gradient(180deg,#fff,#f8faff);color:inherit;transition:transform .18s,box-shadow .18s,border-color .18s}.public-site__card:hover{border-color:#9bb8f1;box-shadow:0 14px 30px rgba(48,88,160,.12);transform:translateY(-2px)}.public-site__card:focus-visible{outline:3px solid #3974d8;outline-offset:3px}.public-site__card-kind{color:#3974d8;font-size:.74rem;font-weight:700}.public-site__card h2{margin:.65rem 0 .45rem;font-size:1.12rem;line-height:1.5}.public-site__card p{margin:0;color:#5d6a80;line-height:1.85}.public-site__card-link{margin-top:auto;padding-top:1rem;color:#2463c5;font-size:.82rem;font-weight:700}.public-site__empty{margin:0;color:#6e7b92}@media(max-width:520px){.public-site{padding:.65rem}.public-site__hero{border-radius:20px}.public-site__content{border-radius:18px}.public-site__section-head{align-items:flex-start;flex-direction:column}}
            .public-site__card-link span{display:inline-block;margin-inline-start:.25rem;transition:transform .18s}.public-site__card:hover .public-site__card-link span{transform:translateX(-3px)}.public-site__footer{max-width:1120px;margin:1rem auto 0;padding:.75rem 1rem;color:#6e7b92;text-align:center;font-size:.8rem}@media(prefers-reduced-motion:reduce){.public-site__card,.public-site__card-link span{transition:none}.public-site__card:hover{transform:none}}';
    }
}
