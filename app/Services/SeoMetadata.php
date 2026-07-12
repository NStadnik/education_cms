<?php

declare(strict_types=1);

namespace App\Services;

final class SeoMetadata
{
    public static function page(array $page, array $settings, string $path): array
    {
        $blocks = json_decode((string) ($page['blocks_json'] ?? '[]'), true);
        $blocks = is_array($blocks) ? $blocks : [];
        $description = self::description((string) ($page['excerpt'] ?? ''));
        if ($description === '') {
            $description = self::description(self::blockText($blocks));
        }

        return self::make(
            (string) ($page['title'] ?? ''),
            $description,
            $settings,
            $path,
            'website',
            self::blockImage($blocks)
        );
    }

    public static function news(array $item, array $settings): array
    {
        return self::make(
            (string) ($item['title'] ?? ''),
            self::description((string) ($item['body'] ?? '')),
            $settings,
            '/news/' . rawurlencode((string) ($item['slug'] ?? '')),
            'article',
            (string) ($item['image_path'] ?? '')
        );
    }

    public static function newsList(array $settings, string $category = '', string $query = '', int $page = 1): array
    {
        $title = $category !== '' ? 'Новини категорії «' . $category . '»' : 'Новини';
        $description = $category !== ''
            ? 'Останні новини, оголошення та події в категорії «' . $category . '».'
            : 'Актуальні новини, оголошення та події ' . self::institutionLabel($settings) . '.';
        if ($query !== '') {
            $title = 'Пошук новин: ' . $query;
            $description = 'Результати пошуку новин за запитом «' . $query . '».';
        }
        $params = [];
        if ($category !== '') { $params['category'] = $category; }
        if ($query !== '') { $params['q'] = $query; }
        if ($page > 1) { $params['page'] = $page; }
        $path = '/news' . ($params ? '?' . http_build_query($params) : '');

        return self::make($title, self::description($description), $settings, $path);
    }

    private static function make(string $title, string $description, array $settings, string $path, string $type = 'website', string $image = ''): array
    {
        $institution = trim((string) ($settings['institution_name'] ?? 'Заклад освіти')) ?: 'Заклад освіти';
        $title = trim($title) ?: $institution;
        if ($description === '') {
            $description = self::description($title . ' — офіційна інформація ' . self::institutionLabel($settings) . '.');
        }
        $image = Files::normalize($image);
        if ($image === '') {
            $image = Files::normalize((string) ($settings['site_logo'] ?? ''));
        }

        return [
            'title' => $title,
            'description' => $description,
            'site_name' => $institution,
            'type' => $type,
            'url' => self::absoluteUrl($path),
            'image' => $image !== '' ? self::absoluteUrl('/uploads/' . $image) : '',
        ];
    }

    private static function description(string $value, int $limit = 160): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($value === '') { return ''; }
        if (self::length($value) <= $limit) { return $value; }
        $short = self::substring($value, 0, $limit + 1);
        $short = preg_replace('/\s+\S*$/u', '', $short) ?? self::substring($value, 0, $limit);
        return rtrim($short, " \t\n\r\0\x0B,.;:!?—–-") . '…';
    }

    private static function blockText(array $blocks): string
    {
        $parts = [];
        array_walk_recursive($blocks, static function ($value, $key) use (&$parts): void {
            if (is_string($value) && in_array((string) $key, ['title', 'text'], true)) {
                $parts[] = $value;
            }
        });
        return implode(' ', $parts);
    }

    private static function blockImage(array $blocks): string
    {
        $image = '';
        array_walk_recursive($blocks, static function ($value, $key) use (&$image): void {
            if ($image === '' && is_string($value) && $key === 'image') { $image = $value; }
        });
        $path = parse_url($image, PHP_URL_PATH);
        if (is_string($path) && str_contains($path, '/uploads/')) {
            $image = substr($path, (int) strpos($path, '/uploads/') + 9);
        }
        return $image;
    }

    private static function absoluteUrl(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) { return $path; }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $host = preg_replace('/[^a-z0-9.\-:\[\]]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
        return ($https ? 'https' : 'http') . '://' . $host . url($path);
    }

    private static function institutionLabel(array $settings): string
    {
        $name = trim((string) ($settings['institution_name'] ?? ''));
        return $name !== '' ? 'закладу «' . $name . '»' : 'закладу освіти';
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    private static function substring(string $value, int $start, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, $start, $length) : substr($value, $start, $length);
    }
}
