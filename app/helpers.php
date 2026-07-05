<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_path(string $path = ''): string
{
    return BASE_PATH . ($path ? '/' . ltrim($path, '/') : '');
}

function url(string $path = ''): string
{
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $path = '/' . ltrim($path, '/');
    return ($base === '/' ? '' : $base) . $path;
}

function redirect(string $path): never
{
    header('Location: ' . url($path), true, 302);
    exit;
}

function selected($left, $right): string
{
    return (string) $left === (string) $right ? 'selected' : '';
}

function checked($value): string
{
    return $value ? 'checked' : '';
}

function excerpt(string $value, int $length = 160): string
{
    $value = trim(strip_tags($value));
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $length);
    }
    return substr($value, 0, $length);
}

function safe_html(?string $value): string
{
    $html = strip_tags((string) $value, '<p><br><strong><b><em><i><u><ul><ol><li><a><blockquote><h2><h3><h4><div><span><hr>');
    $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
    $html = preg_replace('/<(script|style|iframe|object|embed)\b[^>]*>.*?<\/\1>/is', '', $html) ?? '';

    return preg_replace_callback('/<([a-z0-9]+)([^>]*)>/i', static function (array $match): string {
        $tag = strtolower($match[1]);
        $attributes = $match[2] ?? '';
        $safeAttributes = '';

        if ($tag === 'a' && preg_match('/\shref\s*=\s*("|\')([^"\']+)\1/i', $attributes, $hrefMatch)) {
            $href = trim(html_entity_decode($hrefMatch[2], ENT_QUOTES, 'UTF-8'));
            if (preg_match('/^(https?:\/\/|mailto:|\/|#)/i', $href)) {
                $safeAttributes .= ' href="' . e($href) . '" rel="noopener"';
            }
        }

        if (in_array($tag, ['p', 'div', 'span', 'h2', 'h3', 'h4'], true)
            && preg_match('/\sstyle\s*=\s*("|\')[^"\']*text-align\s*:\s*(left|right|center|justify)[^"\']*\1/i', $attributes, $styleMatch)) {
            $safeAttributes .= ' style="text-align: ' . strtolower($styleMatch[2]) . '"';
        }

        return '<' . $tag . $safeAttributes . '>';
    }, $html) ?? '';
}
