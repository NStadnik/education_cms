<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use ZipArchive;

final class UpdateInstaller
{
    private const COPY_PATHS = [
        'app',
        'config/app.php',
        'config/database.php',
        'database',
        'public',
        'templates',
        'index.php',
        '.htaccess',
        'README.md',
        'VERSION',
    ];

    private const PRESERVE_PATHS = [
        'config/local.php',
        'storage',
        '.git',
        '.github',
        '.vscode',
    ];

    public function __construct(
        private readonly UpdateChecker $checker,
        private readonly string $basePath
    ) {
    }

    public function install(array $release): array
    {
        if (empty($release['package_url'])) {
            throw new RuntimeException('У релізі немає zip-архіву оновлення.');
        }
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('На сервері немає PHP ZipArchive. Попросіть хостинг увімкнути розширення zip.');
        }

        $workPath = $this->basePath . '/storage/updates';
        $this->ensureDirectory($workPath);

        $version = preg_replace('/[^0-9A-Za-z._-]/', '', (string) $release['version']);
        $stamp = date('Ymd_His');
        $downloadPath = "{$workPath}/education-cms-v{$version}.zip";
        $extractPath = "{$workPath}/extract_{$stamp}";
        $backupPath = "{$workPath}/backups/backup_{$stamp}_v{$version}";

        file_put_contents($downloadPath, $this->checker->download((string) $release['package_url']));
        $this->verifyChecksum($downloadPath, (string) ($release['checksum_url'] ?? ''));

        $this->extract($downloadPath, $extractPath);
        $packageRoot = $this->packageRoot($extractPath);
        $this->backup($packageRoot, $backupPath);

        try {
            $this->copyPackage($packageRoot);
        } catch (\Throwable $exception) {
            $this->restore($backupPath);
            throw $exception;
        }

        return [
            'version' => $version,
            'backup_path' => $backupPath,
        ];
    }

    private function verifyChecksum(string $downloadPath, string $checksumUrl): void
    {
        if ($checksumUrl === '') {
            return;
        }

        $checksumBody = trim($this->checker->download($checksumUrl));
        if (!preg_match('/\b([a-f0-9]{64})\b/i', $checksumBody, $match)) {
            throw new RuntimeException('Файл checksum має некоректний формат.');
        }

        $actual = hash_file('sha256', $downloadPath);
        if (!hash_equals(strtolower($match[1]), strtolower((string) $actual))) {
            throw new RuntimeException('Checksum не збігається. Оновлення зупинено.');
        }
    }

    private function extract(string $downloadPath, string $extractPath): void
    {
        $this->ensureDirectory($extractPath);
        $zip = new ZipArchive();
        if ($zip->open($downloadPath) !== true) {
            throw new RuntimeException('Не вдалося відкрити zip-архів оновлення.');
        }
        if (!$zip->extractTo($extractPath)) {
            $zip->close();
            throw new RuntimeException('Не вдалося розпакувати zip-архів оновлення.');
        }
        $zip->close();
    }

    private function packageRoot(string $extractPath): string
    {
        foreach (self::COPY_PATHS as $path) {
            if (file_exists($extractPath . '/' . $path)) {
                return $extractPath;
            }
        }

        $items = array_values(array_filter(scandir($extractPath) ?: [], static fn (string $item): bool => !in_array($item, ['.', '..'], true)));
        if (count($items) === 1 && is_dir($extractPath . '/' . $items[0])) {
            return $extractPath . '/' . $items[0];
        }

        throw new RuntimeException('Архів оновлення має неочікувану структуру.');
    }

    private function backup(string $packageRoot, string $backupPath): void
    {
        $this->ensureDirectory($backupPath);
        foreach (self::COPY_PATHS as $path) {
            if (!file_exists($packageRoot . '/' . $path) || !file_exists($this->basePath . '/' . $path)) {
                continue;
            }
            $this->copy($this->basePath . '/' . $path, $backupPath . '/' . $path);
        }
    }

    private function copyPackage(string $packageRoot): void
    {
        foreach (self::COPY_PATHS as $path) {
            $source = $packageRoot . '/' . $path;
            if (!file_exists($source)) {
                continue;
            }
            if ($this->preserved($path)) {
                continue;
            }

            $target = $this->basePath . '/' . $path;
            if (is_dir($source)) {
                $this->mirrorDirectory($source, $target, $path);
            } else {
                $this->copy($source, $target);
            }
        }
    }

    private function mirrorDirectory(string $source, string $target, string $relativeRoot): void
    {
        $this->ensureDirectory($target);
        $allowedFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = $relativeRoot . '/' . substr($item->getPathname(), strlen($source) + 1);
            if ($this->preserved($relative)) {
                continue;
            }
            $allowedFiles[$relative] = true;
            $destination = $this->basePath . '/' . $relative;
            if ($item->isDir()) {
                $this->ensureDirectory($destination);
            } else {
                $this->copy($item->getPathname(), $destination);
            }
        }

        $this->removeStaleFiles($target, $relativeRoot, $allowedFiles);
    }

    private function removeStaleFiles(string $target, string $relativeRoot, array $allowedFiles): void
    {
        if (!is_dir($target)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $relative = $relativeRoot . '/' . substr($item->getPathname(), strlen($target) + 1);
            if ($this->preserved($relative)) {
                continue;
            }
            if ($item->isFile() && !isset($allowedFiles[$relative])) {
                @unlink($item->getPathname());
            }
            if ($item->isDir()) {
                $items = scandir($item->getPathname()) ?: [];
                if (count(array_diff($items, ['.', '..'])) === 0) {
                    @rmdir($item->getPathname());
                }
            }
        }
    }

    private function restore(string $backupPath): void
    {
        if (!is_dir($backupPath)) {
            return;
        }

        foreach (self::COPY_PATHS as $path) {
            if (file_exists($backupPath . '/' . $path)) {
                $this->copy($backupPath . '/' . $path, $this->basePath . '/' . $path);
            }
        }
    }

    private function copy(string $source, string $target): void
    {
        if (is_dir($source)) {
            $this->ensureDirectory($target);
            $items = scandir($source) ?: [];
            foreach ($items as $item) {
                if (in_array($item, ['.', '..'], true)) {
                    continue;
                }
                $this->copy($source . '/' . $item, $target . '/' . $item);
            }
            return;
        }

        $this->ensureDirectory(dirname($target));
        if (!copy($source, $target)) {
            throw new RuntimeException('Не вдалося скопіювати файл: ' . $target);
        }
    }

    private function preserved(string $path): bool
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        foreach (self::PRESERVE_PATHS as $preserved) {
            $preserved = trim($preserved, '/');
            if ($path === $preserved || str_starts_with($path, $preserved . '/')) {
                return true;
            }
        }

        return false;
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Не вдалося створити директорію: ' . $path);
        }
    }
}
