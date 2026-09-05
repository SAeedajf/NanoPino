<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Output;

final class StrictRichTextSanitizer implements RichTextSanitizerInterface
{
    /** @var array<string,true> */
    private const ALLOWED = [
        'p'=>true,'br'=>true,'strong'=>true,'b'=>true,'em'=>true,'i'=>true,
        'u'=>true,'s'=>true,'blockquote'=>true,'ul'=>true,'ol'=>true,'li'=>true,
        'code'=>true,'pre'=>true,'h2'=>true,'h3'=>true,'h4'=>true,'h5'=>true,
        'h6'=>true,'a'=>true,
    ];

    /** @var list<string> */
    private const DROP_WITH_CONTENT = [
        'script','style','iframe','object','embed','svg','math','form',
        'input','textarea','select','option','button','template','noscript',
    ];

    public function sanitize(string $source): SanitizedHtml
    {
        if (strlen($source) > 2_000_000) {
            throw new \InvalidArgumentException('RichText source exceeds safety limit.');
        }

        $source = str_replace("\0", '', $source);
        $source = preg_replace('/<!--.*?-->/s', '', $source) ?? $source;

        foreach (self::DROP_WITH_CONTENT as $tag) {
            $source = preg_replace(
                '#<' . $tag . '\b[^>]*>.*?</' . $tag . '\s*>#is',
                '',
                $source
            ) ?? $source;
        }

        $tokens = preg_split('/(<[^>]*>)/s', $source, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($tokens === false) {
            return new SanitizedHtml($this->escape($source));
        }

        $out = '';
        $stack = [];

        foreach ($tokens as $token) {
            if ($token === '') continue;

            if ($token[0] !== '<') {
                $out .= $this->escapeDecodedText($token);
                continue;
            }

            if (!preg_match('/^<\s*(\/?)\s*([a-zA-Z0-9]+)([^>]*)>$/s', $token, $m)) {
                $out .= $this->escapeDecodedText($token);
                continue;
            }

            $closing = $m[1] === '/';
            $tag = strtolower($m[2]);
            $rawAttributes = (string)$m[3];

            if (!isset(self::ALLOWED[$tag])) {
                continue;
            }

            if ($tag === 'br') {
                if (!$closing) $out .= '<br>';
                continue;
            }

            if ($closing) {
                if (($stack[count($stack)-1] ?? null) === $tag) {
                    array_pop($stack);
                    $out .= '</' . $tag . '>';
                }
                continue;
            }

            $attributes = $tag === 'a' ? $this->anchorAttributes($rawAttributes) : '';
            $out .= '<' . $tag . $attributes . '>';
            $stack[] = $tag;
        }

        while ($stack !== []) {
            $out .= '</' . array_pop($stack) . '>';
        }

        return new SanitizedHtml($out);
    }

    private function anchorAttributes(string $raw): string
    {
        $attributes = [];
        if (preg_match_all(
            '/\s+([a-zA-Z][a-zA-Z0-9:_-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))/u',
            $raw,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $name = strtolower($match[1]);
                $value = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : ($match[4] ?? ''));

                if ($name === 'href') {
                    $safe = $this->safeHref($value);
                    if ($safe !== null) $attributes['href'] = $safe;
                } elseif ($name === 'title') {
                    $attributes['title'] = $this->boundedAttribute($value, 500);
                } elseif ($name === 'target' && $value === '_blank') {
                    $attributes['target'] = '_blank';
                }
            }
        }

        if (($attributes['target'] ?? null) === '_blank') {
            $attributes['rel'] = 'noopener noreferrer';
        }

        $html = '';
        foreach ($attributes as $name => $value) {
            $html .= ' ' . $name . '="' . $this->escape($value) . '"';
        }
        return $html;
    }

    private function safeHref(string $url): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || strlen($url) > 2048) return null;
        if (preg_match('/[\x00-\x20\x7F]/', $url) === 1) return null;
        if (str_starts_with($url, '//')) return null;

        $parts = parse_url($url);
        if ($parts === false) return null;

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if ($scheme !== '' && !in_array($scheme, ['http','https','mailto','tel'], true)) {
            return null;
        }

        if ($scheme === '' && !str_starts_with($url, '/') && !str_starts_with($url, '#') && !str_starts_with($url, '?')) {
            return null;
        }

        return $url;
    }

    private function boundedAttribute(string $value, int $max): string
    {
        $value = trim($value);
        return strlen($value) > $max ? substr($value, 0, $max) : $value;
    }

    private function escapeDecodedText(string $value): string
    {
        return $this->escape(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
