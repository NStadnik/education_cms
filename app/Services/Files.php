<?php

declare(strict_types=1);

namespace App\Services;

final class Files
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp'];

    public static function upload(array $file, array $metadata = []): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(self::uploadErrorMessage($error));
        }

        $size = (int) ($file['size'] ?? 0);
        $limit = self::uploadLimitBytes();
        if ($limit > 0 && $size > $limit) {
            throw new \RuntimeException('Файл завеликий. Поточний PHP-ліміт: ' . self::formatBytes($limit) . '.');
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \RuntimeException('File type is not allowed');
        }

        $name = date('Y/m/') . bin2hex(random_bytes(12)) . '.' . $extension;
        $target = base_path('storage/uploads/' . $name);
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new \RuntimeException('Could not save uploaded file');
        }

        try {
            MediaMetadata::save($name, array_replace([
                'original_name' => (string) ($file['name'] ?? ''),
            ], $metadata));
        } catch (\Throwable $exception) {
            @unlink($target);
            throw $exception;
        }

        return $name;
    }

    public static function all(array $references = []): array
    {
        $root = self::uploadsRoot();
        if (!is_dir($root)) {
            return [];
        }

        $items = [];
        $metadata = MediaMetadata::all();
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if ($relative === '.gitkeep' || substr($relative, -9) === '/.gitkeep') {
                continue;
            }

            $extension = strtolower($file->getExtension());
            $reference = $references[$relative] ?? null;
            $meta = $metadata[$relative] ?? null;
            $actualModifiedAt = date('c', $file->getMTime());
            if ($meta === null || (int) ($meta['size'] ?? -1) !== $file->getSize() || (string) ($meta['modified_at'] ?? '') !== $actualModifiedAt) {
                MediaMetadata::save($relative, []);
                $meta = MediaMetadata::details($relative);
            }
            $items[] = array_replace($meta, [
                'path' => $relative,
                'name' => ((string) ($meta['original_name'] ?? '')) ?: basename($relative),
                'extension' => $extension,
                'type' => self::type($extension),
                'size' => $file->getSize(),
                'size_label' => self::formatBytes($file->getSize()),
                'modified_at' => $actualModifiedAt,
                'is_image' => in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true),
                'is_used' => $reference !== null,
                'reference' => $reference,
            ]);
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) $b['modified_at'], (string) $a['modified_at']));
        return $items;
    }

    public static function delete(string $path): void
    {
        $path = self::normalize($path);
        if ($path === '') {
            throw new \RuntimeException('File not found');
        }

        $target = self::uploadsRoot() . '/' . $path;
        if (!is_file($target)) {
            throw new \RuntimeException('File not found');
        }

        if (!unlink($target)) {
            throw new \RuntimeException('Could not delete file');
        }

        MediaMetadata::delete($path);
    }

    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = trim($path, '/');
        $parts = array_filter(explode('/', $path), static fn (string $part): bool => $part !== '' && $part !== '.');
        if (in_array('..', $parts, true)) {
            return '';
        }

        return implode('/', $parts);
    }

    public static function uploadLimitBytes(): int
    {
        $upload = self::parseIniBytes((string) ini_get('upload_max_filesize'));
        $post = self::parseIniBytes((string) ini_get('post_max_size'));
        $limits = array_filter([$upload, $post], static fn (int $value): bool => $value > 0);

        return $limits ? min($limits) : 0;
    }

    public static function uploadLimitLabel(): string
    {
        $limit = self::uploadLimitBytes();
        return $limit > 0 ? self::formatBytes($limit) : 'не обмежено PHP';
    }

    private static function uploadsRoot(): string
    {
        return base_path('storage/uploads');
    }

    private static function type(string $extension): string
    {
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return 'Зображення';
        }
        if (in_array($extension, ['doc', 'docx'], true)) {
            return 'Word';
        }
        if (in_array($extension, ['xls', 'xlsx'], true)) {
            return 'Excel';
        }
        if ($extension === 'pdf') {
            return 'PDF';
        }

        return strtoupper($extension ?: 'file');
    }

    public static function formatBytes(int $bytes): string
    {
        $units = ['Б', 'КБ', 'МБ', 'ГБ'];
        $value = (float) $bytes;
        $unit = 0;
        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return ($unit === 0 ? (string) $bytes : number_format($value, 1, '.', '')) . ' ' . $units[$unit];
    }

    private static function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл перевищує дозволений розмір завантаження.',
            UPLOAD_ERR_PARTIAL => 'Файл завантажено не повністю.',
            UPLOAD_ERR_NO_TMP_DIR => 'На сервері немає тимчасової папки для завантажень.',
            UPLOAD_ERR_CANT_WRITE => 'Сервер не зміг записати файл.',
            UPLOAD_ERR_EXTENSION => 'PHP-розширення зупинило завантаження.',
            default => 'Upload failed',
        };
    }

    private static function parseIniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower($value[strlen($value) - 1]);
        $number = (float) $value;
        return match ($unit) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }
}
