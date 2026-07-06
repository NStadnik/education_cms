<?php

declare(strict_types=1);

namespace App\Services;

final class SiteThemes
{
    public static function all(?string $themesPath = null): array
    {
        $themesPath ??= base_path('templates/site-themes');
        $themes = [];
        foreach (glob($themesPath . '/*/theme.php') ?: [] as $file) {
            $key = basename(dirname($file));
            if (!preg_match('/^[a-z0-9_-]+$/i', $key)) {
                continue;
            }

            $theme = require $file;
            if (!is_array($theme)) {
                continue;
            }

            $themes[$key] = array_replace_recursive(self::defaults($key), $theme, [
                'key' => $key,
            ]);
        }

        uasort($themes, static function (array $left, array $right): int {
            $order = ((int) ($left['order'] ?? 100)) <=> ((int) ($right['order'] ?? 100));
            return $order !== 0 ? $order : strcmp((string) ($left['key'] ?? ''), (string) ($right['key'] ?? ''));
        });
        if (!$themes) {
            $themes['official'] = self::defaults('official');
        }

        return $themes;
    }

    public static function get(string $key, ?string $themesPath = null): array
    {
        $key = preg_replace('/[^a-z0-9_-]/i', '', $key) ?: 'official';
        $themes = self::all($themesPath);

        return $themes[$key] ?? $themes['official'] ?? reset($themes);
    }

    private static function defaults(string $key): array
    {
        return [
            'key' => $key,
            'name' => ucfirst($key),
            'description' => 'Шаблон сайту.',
            'order' => 100,
            'accent' => '#1f6feb',
            'css' => "/assets/site-themes/{$key}/theme.css",
            'features' => [],
            'preview' => [
                'top' => '#10233f',
                'hero' => '#dfeafb',
                'line' => '#c8d1df',
            ],
        ];
    }
}
