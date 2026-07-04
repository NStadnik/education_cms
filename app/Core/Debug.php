<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Debug
{
    public static function enabled(string $basePath): bool
    {
        if (is_file($basePath . '/storage/debug.enabled')) {
            return true;
        }

        $local = $basePath . '/config/local.php';
        if (is_file($local)) {
            $config = require $local;
            return (bool) ($config['app']['debug'] ?? false);
        }

        $config = require $basePath . '/config/app.php';
        return (bool) ($config['debug'] ?? false);
    }

    public static function register(string $basePath): void
    {
        $enabled = self::enabled($basePath);

        ini_set('log_errors', '1');
        ini_set('error_log', $basePath . '/storage/debug.log');
        ini_set('display_errors', $enabled ? '1' : '0');
        error_reporting(E_ALL);

        set_exception_handler(static function (Throwable $e) use ($enabled): void {
            http_response_code(500);
            if (!$enabled) {
                echo 'Server error';
                return;
            }

            echo '<!doctype html><meta charset="utf-8"><title>Debug error</title>';
            echo '<style>body{font-family:system-ui;padding:24px;background:#f7f8fb;color:#162033}pre{white-space:pre-wrap;background:#fff;border:1px solid #d9dee8;padding:16px;border-radius:8px}</style>';
            echo '<h1>Debug error</h1>';
            echo '<p><strong>' . e($e::class) . ':</strong> ' . e($e->getMessage()) . '</p>';
            echo '<pre>' . e($e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString()) . '</pre>';
        });
    }

    public static function info(string $basePath): array
    {
        $sessionPath = session_save_path();

        return [
            'debug_enabled' => self::enabled($basePath) ? 'yes' : 'no',
            'php_version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'base_path' => $basePath,
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'script_name' => $_SERVER['SCRIPT_NAME'] ?? '',
            'session_status' => (string) session_status(),
            'session_id' => session_id(),
            'session_save_path' => $sessionPath,
            'session_path_writable' => $sessionPath ? (is_writable($sessionPath) ? 'yes' : 'no') : 'unknown',
            'storage_writable' => is_writable($basePath . '/storage') ? 'yes' : 'no',
            'config_writable' => is_writable($basePath . '/config') ? 'yes' : 'no',
            'pdo' => extension_loaded('pdo') ? 'yes' : 'no',
            'pdo_mysql' => extension_loaded('pdo_mysql') ? 'yes' : 'no',
            'pdo_sqlite' => extension_loaded('pdo_sqlite') ? 'yes' : 'no',
            'fileinfo' => extension_loaded('fileinfo') ? 'yes' : 'no',
            'json' => extension_loaded('json') ? 'yes' : 'no',
            'debug_log' => $basePath . '/storage/debug.log',
        ];
    }
}
