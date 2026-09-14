<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

use App\com_pinoox_cms\Cms\Theme\Template\TemplateRequest;

/**
 * Resolves WordPress block-theme template names without loading WordPress.
 * The returned names are logical Builder/template keys, never filesystem paths.
 */
final class WordPressTemplateHierarchyResolver
{
    /** @return list<string> */
    public function candidates(TemplateRequest $request): array
    {
        $v = [];
        foreach ($request->variables as $key => $value) {
            if ($value === null || trim((string)$value) === '') continue;
            $safe = strtolower(trim((string)$value));
            $safe = preg_replace('/[^a-z0-9._-]+/', '-', $safe) ?? '';
            $safe = trim($safe, '-.');
            if ($safe !== '') $v[(string)$key] = $safe;
        }

        $candidates = match ($request->kind) {
            'home' => ['front-page', 'home', 'index'],
            'page' => $this->with($v, ['page-{slug}', 'page', 'singular', 'index']),
            'single' => $this->with($v, [
                'single-{type}-{slug}', 'single-{type}', 'single', 'singular', 'index',
            ]),
            'archive' => $this->with($v, ['archive-{type}', 'archive', 'index']),
            'taxonomy' => $this->with($v, [
                'taxonomy-{taxonomy}-{term}', 'taxonomy-{taxonomy}', 'taxonomy', 'archive', 'index',
            ]),
            'author' => $this->with($v, ['author-{author}', 'author', 'archive', 'index']),
            'date' => ['date', 'archive', 'index'],
            'search' => ['search', 'index'],
            '404' => ['404', 'index'],
            'part' => $this->with($v, ['{part}']),
            default => ['index'],
        };

        return array_values(array_unique(array_filter($candidates, static fn (mixed $item): bool => is_string($item) && $item !== '')));
    }

    /** @param array<string,string> $variables @param list<string> $patterns @return list<string> */
    private function with(array $variables, array $patterns): array
    {
        $result = [];
        foreach ($patterns as $pattern) {
            $candidate = preg_replace_callback('/\{([a-z][a-z0-9_]*)\}/', static function (array $match) use ($variables): string {
                return $variables[$match[1]] ?? '';
            }, $pattern);
            if (!is_string($candidate) || str_contains($candidate, '{') || $candidate === '') {
                continue;
            }
            if (preg_match('/^[a-z0-9][a-z0-9._-]{0,190}$/', $candidate) === 1) $result[] = $candidate;
        }
        return $result;
    }
}
