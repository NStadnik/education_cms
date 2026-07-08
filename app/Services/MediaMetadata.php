<?php

declare(strict_types=1);

namespace App\Services;

final class MediaMetadata
{
    private const FIELDS = ['folder', 'alt_text', 'title', 'caption', 'description', 'uploaded_by'];

    public static function all(): array
    {
        $path = self::path();
        if (!is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    public static function get(string $path): array
    {
        return self::normalizeEntry(self::all()[Files::normalize($path)] ?? []);
    }

    public static function save(string $path, array $metadata): array
    {
        $path = Files::normalize($path);
        if ($path === '') {
            throw new \InvalidArgumentException('Файл не знайдено.');
        }

        $all = self::all();
        $entry = self::normalizeEntry(array_replace(self::normalizeEntry($all[$path] ?? []), $metadata));
        if (implode('', $entry) === '') {
            unset($all[$path]);
        } else {
            $all[$path] = $entry;
        }
        self::write($all);

        return $entry;
    }

    public static function saveMany(array $items): int
    {
        if (!$items) {
            return 0;
        }

        $all = self::all();
        $updated = 0;
        foreach ($items as $path => $metadata) {
            $path = Files::normalize((string) $path);
            if ($path === '' || !is_array($metadata)) {
                continue;
            }

            $entry = self::normalizeEntry(array_replace(self::normalizeEntry($all[$path] ?? []), $metadata));
            if (implode('', $entry) === '') {
                if (isset($all[$path])) {
                    unset($all[$path]);
                    $updated++;
                }
                continue;
            }

            if (($all[$path] ?? null) !== $entry) {
                $all[$path] = $entry;
                $updated++;
            }
        }

        if ($updated > 0) {
            self::write($all);
        }

        return $updated;
    }

    public static function delete(string $path): void
    {
        $path = Files::normalize($path);
        if ($path === '') {
            return;
        }

        $all = self::all();
        unset($all[$path]);
        self::write($all);
    }

    public static function folders(array $items): array
    {
        $folders = [];
        foreach ($items as $item) {
            $folder = trim((string) ($item['folder'] ?? ''));
            if ($folder !== '') {
                $folders[$folder] = true;
            }
        }

        $folders = array_keys($folders);
        sort($folders, SORT_NATURAL | SORT_FLAG_CASE);
        return $folders;
    }

    public static function normalizeEntry(array $metadata): array
    {
        $entry = [];
        foreach (self::FIELDS as $field) {
            $value = trim((string) ($metadata[$field] ?? ''));
            if ($field === 'uploaded_by') {
                $entry[$field] = (string) max(0, (int) $value);
                continue;
            }

            $entry[$field] = self::limit($field === 'description' ? $value : preg_replace('/\s+/', ' ', $value), $field === 'description' ? 1000 : 160);
        }
        $entry['folder'] = self::normalizeFolder($entry['folder']);

        return $entry;
    }

    public static function normalizeFolder(string $folder): string
    {
        $folder = trim(preg_replace('/\s+/', ' ', str_replace(['\\', '/'], ' ', $folder)) ?? '');
        return self::limit($folder, 80);
    }

    private static function write(array $metadata): void
    {
        $path = self::path();
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        ksort($metadata, SORT_NATURAL | SORT_FLAG_CASE);
        $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($path, $json . "\n", LOCK_EX) === false) {
            throw new \RuntimeException('Не вдалося зберегти метадані файлу.');
        }
    }

    private static function path(): string
    {
        return base_path('storage/media-meta.json');
    }

    private static function limit(?string $value, int $length): string
    {
        $value = (string) $value;
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $length);
        }

        return substr($value, 0, $length);
    }
}
