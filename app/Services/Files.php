<?php

declare(strict_types=1);

namespace App\Services;

final class Files
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp'];

    public static function upload(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload failed');
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

        return $name;
    }

    public static function all(array $references = []): array
    {
        $root = self::uploadsRoot();
        if (!is_dir($root)) {
            return [];
        }

        $items = [];
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
            $items[] = [
                'path' => $relative,
                'name' => basename($relative),
                'extension' => $extension,
                'type' => self::type($extension),
                'size' => $file->getSize(),
                'size_label' => self::formatBytes($file->getSize()),
                'modified_at' => date('c', $file->getMTime()),
                'is_image' => in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true),
                'is_used' => $reference !== null,
                'reference' => $reference,
            ];
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

    private static function formatBytes(int $bytes): string
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
}
