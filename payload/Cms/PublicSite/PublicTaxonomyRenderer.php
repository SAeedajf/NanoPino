<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\PublicSite;

use App\com_pinoox_cms\Cms\Content\ContentRecord;
use App\com_pinoox_cms\Cms\Taxonomy\TermRecord;
use App\com_pinoox_cms\Cms\Theme\Design\DesignTokenCssCompiler;
use App\com_pinoox_cms\Cms\Theme\Design\ResolvedDesign;

final readonly class PublicTaxonomyRenderer
{
    public function __construct(
        private ?ResolvedDesign $themeDesign = null,
        private DesignTokenCssCompiler $designCss = new DesignTokenCssCompiler(),
    ) {}

    /** @param list<ContentRecord> $records */
    public function render(
        TermRecord $term,
        string $taxonomyLabel,
        array $records,
        string $canonicalPath,
        ?PublicNavigation $navigation = null,
        ?string $mountPath = null,
        ?string $locale = null,
    ): string
    {
        $copy = new PublicSiteCopy($locale ?? $term->locale);
        $cards = '';
        $visibleCount = 0;
        foreach ($records as $record) {
            $url = PublicContentUrl::path($record, $mountPath);
            if ($url === null) continue;
            $visibleCount++;
            $title = $this->escape($record->title !== '' ? $record->title : 'بدون عنوان');
            $summary = $this->escape(trim($record->excerpt) !== '' ? $record->excerpt : $copy->text('card_fallback'));
            $cards .= '<a class="public-taxonomy__card" href="' . $this->escape($url) . '"><span>' . $this->escape($copy->text($record->type === 'post' ? 'post' : 'page')) . '</span><h2>' . $title . '</h2><p>' . $summary . '</p><strong>' . $this->escape($copy->text('view_content')) . ' <span aria-hidden="true">' . ($copy->direction() === 'rtl' ? '←' : '→') . '</span></strong></a>';
        }
        if ($cards === '') $cards = '<p class="public-taxonomy__empty">' . $this->escape($copy->text('no_related')) . '</p>';

        $name = $this->escape($term->name);
        $description = trim($term->description) !== '' ? '<p class="public-taxonomy__description">' . $this->escape($term->description) . '</p>' : '';
        $canonical = $this->escape($canonicalPath);
        $lang = $this->escape($copy->language());
        return $this->normalizeMarkup('<!doctype html>\n<html lang="' . $lang . '" dir="' . $copy->direction() . '">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<meta name="description" content="' . $name . ' | ' . $this->escape($taxonomyLabel) . '">\n<link rel="canonical" href="' . $canonical . '">\n<title>' . $name . ' | NanoPino</title>\n<style>' . $this->styles() . '</style>\n</head>\n<body><main id="public-main" class="public-taxonomy">' . ($navigation?->render($mountPath, $canonicalPath, $copy->language()) ?? '') . '<header class="public-taxonomy__hero"><p>' . $this->escape($copy->text('taxonomy_eyebrow')) . ' · ' . $this->escape($taxonomyLabel) . '</p><h1>' . $name . '</h1>' . $description . '</header><section id="public-primary-content" class="public-taxonomy__content" aria-labelledby="taxonomy-content-title"><div class="public-taxonomy__head"><h2 id="taxonomy-content-title">' . $this->escape($copy->text('related_content')) . '</h2><span>' . $visibleCount . ' ' . $this->escape($copy->text('item_count')) . '</span></div><div class="public-taxonomy__cards">' . $cards . '</div></section><footer class="public-taxonomy__footer">NanoPino</footer></main></body></html>');
    }

    private function escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'); }
    private function normalizeMarkup(string $markup): string { return str_replace('\\n', "\n", $markup); }
    private function styles(): string
    {
        $themeCss = $this->themeDesign !== null ? $this->designCss->compile($this->themeDesign) : '';
        return $themeCss . ':root{font-family:var(--cms-fontFamilies-body,Vazir,Vazirmatn,IRANSans,system-ui,sans-serif);color:var(--cms-colors-text,#172033);background:var(--cms-colors-background,#f5f7fb)}*{box-sizing:border-box}body{margin:0}.public-taxonomy{min-height:100vh;padding:clamp(.75rem,4vw,4rem);background:radial-gradient(circle at 90% 0,#e3edff 0,transparent 35%),var(--cms-colors-background,#f5f7fb)}.public-taxonomy__hero,.public-taxonomy__content{max-width:1120px;margin:auto}.public-taxonomy__hero{padding:clamp(2rem,6vw,5rem);border-radius:var(--cms-radius-base,28px);background:linear-gradient(135deg,#162b57,#376cc8);color:#fff;box-shadow:0 24px 70px #1f3e7a33}.public-taxonomy__hero p{margin:0 0 .8rem;color:#c8daff;font-size:.85rem;font-weight:700}.public-taxonomy h1{margin:0;font-size:clamp(2.2rem,6vw,4.5rem);line-height:1.2}.public-taxonomy__description{max-width:700px;margin:1rem 0 0;color:#e5edff;line-height:2}.public-taxonomy__content{margin-top:1rem;padding:clamp(1.2rem,4vw,2rem);border-radius:22px;background:#fff;box-shadow:0 16px 44px #1a274311}.public-taxonomy__head{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem}.public-taxonomy__head h2{margin:0}.public-taxonomy__head span{color:#6e7b92;font-size:.8rem}.public-taxonomy__cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:.8rem}.public-taxonomy__card{display:flex;min-height:190px;flex-direction:column;padding:1.1rem;border:1px solid #e1e7f1;border-radius:16px;background:linear-gradient(180deg,#fff,#f8faff);color:inherit;text-decoration:none;transition:transform .18s,box-shadow .18s,border-color .18s}.public-taxonomy__card:hover{border-color:#9bb8f1;box-shadow:0 12px 28px #3058a01f;transform:translateY(-2px)}.public-taxonomy__card:focus-visible{outline:3px solid var(--cms-colors-primary,#3974d8);outline-offset:3px}.public-taxonomy__card>span{color:var(--cms-colors-primary,#3974d8);font-size:.75rem;font-weight:700}.public-taxonomy__card h2{margin:.6rem 0 .4rem;font-size:1.1rem}.public-taxonomy__card p{margin:0;color:#5d6a80;line-height:1.8}.public-taxonomy__card strong{margin-top:auto;padding-top:1rem;color:var(--cms-colors-primary,#2463c5);font-size:.82rem}.public-taxonomy__empty{color:#6e7b92}.public-taxonomy__footer{max-width:1120px;margin:1rem auto 0;padding:.75rem 1rem;color:#6e7b92;text-align:center;font-size:.8rem}@media(max-width:520px){.public-taxonomy__hero{border-radius:20px}.public-taxonomy__content{border-radius:18px}.public-taxonomy__head{align-items:flex-start;flex-direction:column}}@media(prefers-reduced-motion:reduce){.public-taxonomy__card{transition:none}.public-taxonomy__card:hover{transform:none}}';
    }
}
