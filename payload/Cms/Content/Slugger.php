<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Content;

final class Slugger
{
    private const MAX_LENGTH = 160;

    public function slug(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'untitled';
        }

        $value = strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
        $value = trim($value, '-');
        $value = preg_replace('/-+/', '-', $value) ?? $value;
        $value = $this->truncate($value, self::MAX_LENGTH);

        return $value !== '' ? $value : 'untitled';
    }

    private function truncate(string $value, int $maxCharacters): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxCharacters);
        }

        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($characters)) {
            return substr($value, 0, $maxCharacters);
        }

        return implode('', array_slice($characters, 0, $maxCharacters));
    }

    public function unique(
        ContentRepositoryInterface $repository,
        int $siteId,
        string $type,
        string $locale,
        string $candidate,
        ?int $excludeId = null,
    ): string {
        $base = $this->slug($candidate);
        $slug = $base;
        $suffix = 2;

        while ($repository->slugExists($siteId, $type, $locale, $slug, $excludeId)) {
            $slug = $this->withSuffix($base, $suffix);
            ++$suffix;
        }

        return $slug;
    }

    public function uniqueTerm(
        \App\com_pinoox_cms\Cms\Taxonomy\TermRepositoryInterface $repository,
        int $siteId,
        string $taxonomy,
        string $locale,
        string $candidate,
        ?int $excludeId = null,
    ): string {
        $base = $this->slug($candidate);
        $slug = $base;
        $suffix = 2;

        while ($repository->slugExists($siteId, $taxonomy, $locale, $slug, $excludeId)) {
            $slug = $this->withSuffix($base, $suffix);
            ++$suffix;
        }

        return $slug;
    }

    private function withSuffix(string $base, int $suffix): string
    {
        $suffixText = '-' . $suffix;
        return $this->truncate($base, self::MAX_LENGTH - strlen($suffixText)) . $suffixText;
    }
}
