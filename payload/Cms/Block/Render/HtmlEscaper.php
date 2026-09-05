<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Render;

final class HtmlEscaper
{
    public function text(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function url(mixed $value): string
    {
        $url = trim((string)$value);
        if ($url === '' || strlen($url) > 2048) return '#';
        if (preg_match('/[\x00-\x20\x7F]/', $url) === 1) return '#';
        if (str_starts_with($url, '//')) return '#';

        $parts = parse_url($url);
        if ($parts === false) return '#';

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if ($scheme !== '' && !in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
            return '#';
        }

        if (
            $scheme === ''
            && !str_starts_with($url, '/')
            && !str_starts_with($url, '#')
            && !str_starts_with($url, '?')
        ) {
            return '#';
        }

        return $this->text($url);
    }

    public function classList(string ...$classes): string
    {
        $safe = [];
        foreach ($classes as $class) {
            foreach (preg_split('/\s+/', trim($class)) ?: [] as $token) {
                if ($token !== '' && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $token) === 1) {
                    $safe[] = $token;
                }
            }
        }
        return implode(' ', array_values(array_unique($safe)));
    }
}
