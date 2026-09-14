<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\PublicSite;

/**
 * Small server-side copy contract for the public surfaces.
 * Content titles and taxonomy labels remain author-controlled; this class
 * only localizes the surrounding product chrome and document metadata.
 */
final readonly class PublicSiteCopy
{
    private string $language;

    public function __construct(string $locale = PublicSiteContext::DEFAULT_LOCALE)
    {
        $language = strtolower(substr(trim($locale), 0, 2));
        $this->language = in_array($language, ['fa', 'ar', 'he', 'ur'], true) ? 'fa' : 'en';
    }

    public function language(): string
    {
        return $this->language;
    }

    public function direction(): string
    {
        return $this->language === 'fa' ? 'rtl' : 'ltr';
    }

    public function text(string $key): string
    {
        $fa = [
            'site_aria' => 'منوی اصلی',
            'skip' => 'پرش به محتوای اصلی',
            'site_eyebrow' => 'NanoPino · سایت عمومی',
            'hero_title' => 'محتوای شما، در یک سایت واقعی',
            'hero_description' => 'این همان نمایی است که بازدیدکننده‌ی سایت می‌بیند؛ جدا از محیط مدیریت و کنترل‌پلین.',
            'published_eyebrow' => 'انتشار',
            'latest_content' => 'آخرین محتوا',
            'item_count' => 'مورد',
            'view_content' => 'مشاهده محتوا',
            'card_fallback' => 'برای مشاهده‌ی جزئیات این محتوا وارد شوید.',
            'no_published' => 'هنوز محتوای منتشرشده‌ای برای نمایش وجود ندارد.',
            'page' => 'برگه',
            'post' => 'نوشته',
            'published_at' => 'منتشرشده در',
            'empty_page' => 'این صفحه هنوز محتوایی ندارد.',
            'taxonomy_aria' => 'طبقه‌بندی محتوا',
            'taxonomy_eyebrow' => 'طبقه‌بندی',
            'related_content' => 'محتوای مرتبط',
            'no_related' => 'محتوای منتشرشده‌ای در این طبقه‌بندی وجود ندارد.',
        ];
        $en = [
            'site_aria' => 'Primary navigation',
            'skip' => 'Skip to main content',
            'site_eyebrow' => 'NanoPino · Public site',
            'hero_title' => 'Your content, on a real site',
            'hero_description' => 'This is the visitor-facing view, separate from the admin control plane.',
            'published_eyebrow' => 'Published',
            'latest_content' => 'Latest content',
            'item_count' => 'items',
            'view_content' => 'View content',
            'card_fallback' => 'Open this item to read the full content.',
            'no_published' => 'There is no published content to show yet.',
            'page' => 'Page',
            'post' => 'Post',
            'published_at' => 'Published',
            'empty_page' => 'This page does not have any content yet.',
            'taxonomy_aria' => 'Content taxonomy',
            'taxonomy_eyebrow' => 'Taxonomy',
            'related_content' => 'Related content',
            'no_related' => 'There is no published content in this taxonomy yet.',
        ];

        return ($this->language === 'fa' ? $fa : $en)[$key] ?? $key;
    }
}
