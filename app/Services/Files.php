<?php

declare(strict_types=1);

namespace App\Services;

final class Files
{
    public static function upload(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload failed');
        }

        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed, true)) {
            throw new \RuntimeException('File type is not allowed');
        }

        $name = date('Y/m/') . bin2hex(random_bytes(12)) . '.' . $extension;
        $target = base_path('storage/uploads/' . $name);
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }
        move_uploaded_file($file['tmp_name'], $target);
        return $name;
    }
}
