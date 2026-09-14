<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\PublicSite;

use App\com_pinoox_cms\Cms\Admin\AdminRuntimeUrl;

/**
 * Validated, site-scoped public navigation.
 *
 * Navigation is intentionally stored in the existing Settings layer rather
 * than introducing a second persistence model. Only app-local paths are
 * accepted so an administrator cannot turn the menu into an open redirect.
 * The two-level limit keeps rendering bounded and the JSON contract portable.
 */
final readonly class PublicNavigation
{
    public const SETTING = 'site.primary_navigation';
    private const MAX_ITEMS = 24;
    private const MAX_CHILDREN = 8;
    private const MAX_DEPTH = 1;

    /** @param list<array{label:string,path:string,children?:list<array{label:string,path:string,children?:list<array{label:string,path:string}>}>}> $items */
    private function __construct(private array $items) {}

    /** @param mixed $value */
    public static function fromValue(mixed $value): self
    {
        return new self(self::normalizeItems($value, 0));
    }

    public static function validate(mixed $value): bool|string
    {
        try {
            self::fromValue($value);
            return true;
        } catch (\Throwable $error) {
            return $error->getMessage();
        }
    }

    /** @return list<array{label:string,path:string,children?:list<array{label:string,path:string,children?:list<array{label:string,path:string}>}>}> */
    public function items(): array
    {
        return $this->items;
    }

    public function render(?string $mountPath = null, ?string $currentPath = null, ?string $locale = null): string
    {
        if ($this->items === []) {
            return '';
        }

        $copy = new PublicSiteCopy($locale ?? PublicSiteContext::DEFAULT_LOCALE);
        return '<style>.public-navigation{max-width:1120px;margin:0 auto 1rem;padding:.75rem 1rem;border:1px solid #dce5f6;border-radius:16px;background:#fff;box-shadow:0 8px 24px rgba(26,39,67,.06)}.public-navigation__skip{position:absolute;inset-inline-start:1rem;inset-block-start:.5rem;z-index:10;transform:translateY(-180%);padding:.65rem .85rem;border-radius:10px;background:#152a55;color:#fff;text-decoration:none;font-weight:700}.public-navigation__skip:focus{transform:translateY(0)}.public-navigation ul{display:flex;align-items:center;flex-wrap:wrap;gap:.35rem;margin:0;padding:0;list-style:none}.public-navigation li{position:relative}.public-navigation a{display:block;min-block-size:44px;padding:.65rem .8rem;border-radius:10px;color:#29466f;text-decoration:none;font-size:.9rem;line-height:1.4}.public-navigation a:hover,.public-navigation a[aria-current="page"]{background:#edf4ff;color:#1d5ebd}.public-navigation a:focus-visible{outline:3px solid #8ab0ff;outline-offset:2px}.public-navigation li>ul{display:none;position:absolute;z-index:2;inset-block-start:100%;inset-inline-start:0;min-inline-size:12rem;padding:.35rem;border:1px solid #dce5f6;border-radius:12px;background:#fff;box-shadow:0 12px 28px rgba(26,39,67,.12)}.public-navigation li:hover>ul,.public-navigation li:focus-within>ul{display:grid}.public-navigation li>ul li>ul{inset-block-start:0;inset-inline-start:100%}@media(max-width:520px){.public-navigation{margin-bottom:.65rem;padding:.55rem}.public-navigation ul{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.public-navigation a{font-size:.86rem}.public-navigation li>ul{position:static;display:grid;margin-top:.2rem;box-shadow:none;border:0;background:#f7f9fd}.public-navigation li>ul li>ul{margin-inline-start:.4rem}}@media(prefers-reduced-motion:reduce){.public-navigation__skip{transition:none}}</style><a class="public-navigation__skip" href="#public-primary-content">' . $this->escape($copy->text('skip')) . '</a><nav class="public-navigation" aria-label="' . $this->escape($copy->text('site_aria')) . '"><ul>'
            . $this->renderItems($this->items, $mountPath, $currentPath)
            . '</ul></nav>';
    }

    /** @param mixed $value @return list<array{label:string,path:string,children?:list<array{label:string,path:string,children?:list<array{label:string,path:string}>}>}> */
    private static function normalizeItems(mixed $value, int $depth): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('Public navigation must be a list of items.');
        }
        if ($depth > self::MAX_DEPTH) {
            throw new \InvalidArgumentException('Public navigation supports at most two levels.');
        }
        if (count($value) > ($depth === 0 ? self::MAX_ITEMS : self::MAX_CHILDREN)) {
            throw new \InvalidArgumentException('Public navigation contains too many items.');
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Each public navigation item must be an object.');
            }

            $label = trim((string)($item['label'] ?? ''));
            $path = trim((string)($item['path'] ?? ''));
            if ($label === '' || self::length($label) > 120) {
                throw new \InvalidArgumentException('Public navigation labels must contain 1 to 120 characters.');
            }
            if (!self::isSafePath($path)) {
                throw new \InvalidArgumentException('Public navigation paths must be safe app-local paths.');
            }

            $normalized = ['label' => $label, 'path' => $path];
            if (array_key_exists('children', $item)) {
                $normalized['children'] = self::normalizeItems($item['children'], $depth + 1);
            }
            $items[] = $normalized;
        }

        return $items;
    }

    /** @param list<array{label:string,path:string,children?:array}> $items */
    private function renderItems(array $items, ?string $mountPath, ?string $currentPath = null): string
    {
        $html = '';
        foreach ($items as $item) {
            $url = AdminRuntimeUrl::appPath($item['path'], $mountPath);
            $current = $currentPath !== null && rtrim($url, '/') === rtrim($currentPath, '/')
                ? ' aria-current="page"'
                : '';
            $html .= '<li><a href="' . $this->escape($url) . '"' . $current . '>' . $this->escape($item['label']) . '</a>';
            if (isset($item['children']) && $item['children'] !== []) {
                $html .= '<ul>' . $this->renderItems($item['children'], $mountPath, $currentPath) . '</ul>';
            }
            $html .= '</li>';
        }
        return $html;
    }

    private static function isSafePath(string $path): bool
    {
        return $path !== ''
            && strlen($path) <= 512
            && str_starts_with($path, '/')
            && !str_starts_with($path, '//')
            && !str_contains($path, '\\')
            && !str_contains($path, "\0")
            && preg_match('/[\x00-\x1F\x7F]/', $path) !== 1
            && preg_match('#^[a-z][a-z0-9+.-]*://#i', $path) !== 1;
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
