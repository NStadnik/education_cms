<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\Database;

final class MediaMetadata
{
    private const FIELDS = ['folder', 'alt_text', 'title', 'caption', 'description', 'uploaded_by'];

    public static function all(): array
    {
        $items = [];
        foreach (self::db()->fetchAll('select * from media order by path asc') as $row) {
            $path = Files::normalize((string) ($row['path'] ?? ''));
            if ($path !== '') {
                $items[$path] = self::rowEntry($row);
            }
        }

        return $items;
    }

    public static function get(string $path): array
    {
        $row = self::find($path);
        return self::normalizeEntry($row ?? []);
    }

    public static function details(string $path): array
    {
        $row = self::find($path);
        return $row === null ? [] : self::rowEntry($row);
    }

    public static function save(string $path, array $metadata): array
    {
        $path = Files::normalize($path);
        $absolutePath = self::absolutePath($path);
        if ($path === '' || !is_file($absolutePath)) {
            throw new \InvalidArgumentException('Файл не знайдено.');
        }

        $existing = self::find($path) ?? [];
        if ((int) ($existing['uploaded_by'] ?? 0) > 0) {
            unset($metadata['uploaded_by']);
        }
        $entry = self::normalizeEntry(array_replace(self::normalizeEntry($existing), $metadata));
        $file = self::fileInfo($path, $absolutePath, (string) ($existing['original_name'] ?? ($metadata['original_name'] ?? '')));
        $now = date('c');
        $createdAt = (string) ($existing['created_at'] ?? $now);
        $uploadedBy = (int) $entry['uploaded_by'];

        self::db()->execute(
            'insert into media
                (path, original_name, extension, mime_type, size, width, height, modified_at, folder, alt_text, title, caption, description, uploaded_by, created_at, updated_at)
             values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             on duplicate key update
                original_name = values(original_name), extension = values(extension), mime_type = values(mime_type),
                size = values(size), width = values(width), height = values(height), modified_at = values(modified_at),
                folder = values(folder), alt_text = values(alt_text), title = values(title), caption = values(caption),
                description = values(description), uploaded_by = values(uploaded_by), updated_at = values(updated_at)',
            [
                $path,
                $file['original_name'],
                $file['extension'],
                $file['mime_type'],
                $file['size'],
                $file['width'],
                $file['height'],
                $file['modified_at'],
                $entry['folder'],
                $entry['alt_text'],
                $entry['title'],
                $entry['caption'],
                $entry['description'],
                $uploadedBy > 0 ? $uploadedBy : null,
                $createdAt,
                $now,
            ]
        );

        return $entry;
    }

    public static function ensure(string $path, array $metadata = []): array
    {
        $path = Files::normalize($path);
        $existing = self::find($path);
        if ($existing !== null) {
            return self::normalizeEntry($existing);
        }

        return self::save($path, $metadata);
    }

    public static function saveMany(array $items): int
    {
        $updated = 0;
        foreach ($items as $path => $metadata) {
            $path = Files::normalize((string) $path);
            if ($path === '' || !is_array($metadata) || !is_file(self::absolutePath($path))) {
                continue;
            }

            $before = self::get($path);
            $after = self::normalizeEntry(array_replace($before, $metadata));
            self::save($path, $metadata);
            if ($before !== $after) {
                $updated++;
            }
        }

        return $updated;
    }

    public static function delete(string $path): void
    {
        $path = Files::normalize($path);
        if ($path !== '') {
            self::db()->execute('delete from media where path = ?', [$path]);
        }
    }

    public static function migrateLegacyStorage(): void
    {
        $legacy = [];
        $legacyPath = base_path('storage/media-meta.json');
        if (is_file($legacyPath)) {
            $decoded = json_decode((string) file_get_contents($legacyPath), true);
            $legacy = is_array($decoded) ? $decoded : [];
        }

        $root = base_path('storage/uploads');
        if (!is_dir($root)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $path = Files::normalize(str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1)));
            if ($path === '' || $file->getBasename() === '.gitkeep') {
                continue;
            }

            $legacyMetadata = is_array($legacy[$path] ?? null) ? $legacy[$path] : [];
            if ($legacyMetadata) {
                self::save($path, $legacyMetadata);
            } else {
                self::ensure($path);
            }
        }
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

            $value = $field === 'description' ? $value : (preg_replace('/\s+/', ' ', $value) ?? '');
            $entry[$field] = self::limit($value, $field === 'description' ? 1000 : 160);
        }
        $entry['folder'] = self::normalizeFolder($entry['folder']);

        return $entry;
    }

    public static function normalizeFolder(string $folder): string
    {
        $folder = trim(preg_replace('/\s+/', ' ', str_replace(['\\', '/'], ' ', $folder)) ?? '');
        return self::limit($folder, 80);
    }

    private static function find(string $path): ?array
    {
        $path = Files::normalize($path);
        return $path === '' ? null : self::db()->fetch('select * from media where path = ?', [$path]);
    }

    private static function rowEntry(array $row): array
    {
        return array_replace([
            'original_name' => (string) ($row['original_name'] ?? ''),
            'extension' => (string) ($row['extension'] ?? ''),
            'mime_type' => (string) ($row['mime_type'] ?? ''),
            'size' => (int) ($row['size'] ?? 0),
            'width' => isset($row['width']) ? (int) $row['width'] : null,
            'height' => isset($row['height']) ? (int) $row['height'] : null,
            'modified_at' => (string) ($row['modified_at'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ], self::normalizeEntry($row));
    }

    private static function fileInfo(string $path, string $absolutePath, string $originalName): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeType = '';
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($absolutePath);
            $mimeType = is_string($detected) ? $detected : '';
        }

        $width = null;
        $height = null;
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $dimensions = @getimagesize($absolutePath);
            if (is_array($dimensions)) {
                $width = (int) ($dimensions[0] ?? 0) ?: null;
                $height = (int) ($dimensions[1] ?? 0) ?: null;
                $mimeType = (string) ($dimensions['mime'] ?? $mimeType);
            }
        }

        $originalName = trim(basename(str_replace('\\', '/', $originalName)));
        return [
            'original_name' => self::limit($originalName !== '' ? $originalName : basename($path), 255),
            'extension' => self::limit($extension, 20),
            'mime_type' => self::limit($mimeType, 120),
            'size' => max(0, (int) filesize($absolutePath)),
            'width' => $width,
            'height' => $height,
            'modified_at' => date('c', (int) filemtime($absolutePath)),
        ];
    }

    private static function absolutePath(string $path): string
    {
        return base_path('storage/uploads/' . $path);
    }

    private static function db(): Database
    {
        return Container::get('db');
    }

    private static function limit(?string $value, int $length): string
    {
        $value = (string) $value;
        return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
    }
}
