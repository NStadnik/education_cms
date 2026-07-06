<?php

declare(strict_types=1);

namespace App\Services;

final class Thumbnails
{
    private const MAX_SIZE = 1600;
    private const MIN_SIZE = 1;
    private const SUPPORTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public static function make(string $path, int $width, int $height, string $fit = 'crop'): array
    {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('PHP GD extension is required for thumbnails.');
        }

        $path = Files::normalize($path);
        if ($path === '') {
            throw new \RuntimeException('Image not found.');
        }

        $source = base_path('storage/uploads/' . $path);
        if (!is_file($source)) {
            throw new \RuntimeException('Image not found.');
        }

        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if (!in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
            throw new \RuntimeException('Unsupported image type.');
        }

        $width = self::clamp($width);
        $height = self::clamp($height);
        $fit = in_array($fit, ['crop', 'contain'], true) ? $fit : 'crop';
        $targetExtension = $extension === 'jpeg' ? 'jpg' : $extension;
        if ($targetExtension === 'webp' && !function_exists('imagewebp')) {
            $targetExtension = 'jpg';
        }

        $cachePath = self::cachePath($path, $width, $height, $fit, $targetExtension, (int) filemtime($source));
        if (!is_file($cachePath)) {
            self::render($source, $cachePath, $extension, $targetExtension, $width, $height, $fit);
        }

        return [
            'path' => $cachePath,
            'mime' => self::mime($targetExtension),
        ];
    }

    private static function render(string $source, string $target, string $sourceExtension, string $targetExtension, int $width, int $height, string $fit): void
    {
        [$sourceWidth, $sourceHeight] = getimagesize($source) ?: [0, 0];
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            throw new \RuntimeException('Invalid image.');
        }

        $image = self::load($source, $sourceExtension);
        $canvas = imagecreatetruecolor($width, $height);
        if (!$image || !$canvas) {
            throw new \RuntimeException('Could not create thumbnail.');
        }

        if (in_array($targetExtension, ['png', 'webp'], true)) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
        } else {
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        }

        if ($fit === 'contain') {
            $scale = min($width / $sourceWidth, $height / $sourceHeight);
            $targetWidth = max(1, (int) round($sourceWidth * $scale));
            $targetHeight = max(1, (int) round($sourceHeight * $scale));
            $targetX = (int) floor(($width - $targetWidth) / 2);
            $targetY = (int) floor(($height - $targetHeight) / 2);
            imagecopyresampled($canvas, $image, $targetX, $targetY, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
        } else {
            $scale = max($width / $sourceWidth, $height / $sourceHeight);
            $cropWidth = max(1, (int) round($width / $scale));
            $cropHeight = max(1, (int) round($height / $scale));
            $sourceX = (int) floor(($sourceWidth - $cropWidth) / 2);
            $sourceY = (int) floor(($sourceHeight - $cropHeight) / 2);
            imagecopyresampled($canvas, $image, 0, 0, $sourceX, $sourceY, $width, $height, $cropWidth, $cropHeight);
        }

        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }

        self::save($canvas, $target, $targetExtension);
        imagedestroy($image);
        imagedestroy($canvas);
    }

    private static function load(string $path, string $extension): \GdImage|false
    {
        return match ($extension) {
            'jpg', 'jpeg' => imagecreatefromjpeg($path),
            'png' => imagecreatefrompng($path),
            'webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false,
            default => false,
        };
    }

    private static function save(\GdImage $image, string $path, string $extension): void
    {
        $ok = match ($extension) {
            'png' => imagepng($image, $path, 6),
            'webp' => imagewebp($image, $path, 82),
            default => imagejpeg($image, $path, 84),
        };
        if (!$ok) {
            throw new \RuntimeException('Could not save thumbnail.');
        }
    }

    private static function cachePath(string $path, int $width, int $height, string $fit, string $extension, int $modifiedAt): string
    {
        $hash = sha1($path . '|' . $width . '|' . $height . '|' . $fit . '|' . $modifiedAt);
        return base_path('storage/cache/thumbs/' . substr($hash, 0, 2) . '/' . $hash . '.' . $extension);
    }

    private static function clamp(int $value): int
    {
        if ($value <= 0) {
            return 320;
        }
        return max(self::MIN_SIZE, min(self::MAX_SIZE, $value));
    }

    private static function mime(string $extension): string
    {
        return match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}
