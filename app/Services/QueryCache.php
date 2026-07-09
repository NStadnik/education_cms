<?php

declare(strict_types=1);

namespace App\Services;

final class QueryCache
{
    private const DEFAULT_TTL = 600;

    public static function remember(string $scope, string $sql, array $params, callable $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        $key = self::key($scope, $sql, $params);
        $path = self::path($key);
        if (is_file($path)) {
            $payload = json_decode((string) file_get_contents($path), true);
            if (is_array($payload)
                && (int) ($payload['expires_at'] ?? 0) >= time()
                && array_key_exists('value', $payload)) {
                return $payload['value'];
            }
        }

        $value = $callback();
        self::write($path, [
            'expires_at' => time() + max(1, $ttl),
            'value' => $value,
        ]);

        return $value;
    }

    public static function flush(): void
    {
        self::write(self::versionPath(), (string) time() . '-' . bin2hex(random_bytes(4)));
    }

    private static function key(string $scope, string $sql, array $params): string
    {
        return sha1(json_encode([
            'scope' => $scope,
            'version' => self::version(),
            'sql' => preg_replace('/\s+/', ' ', trim($sql)),
            'params' => $params,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    private static function path(string $key): string
    {
        return self::root() . '/' . substr($key, 0, 2) . '/' . $key . '.json';
    }

    private static function version(): string
    {
        $path = self::versionPath();
        if (!is_file($path)) {
            self::write($path, '1');
        }

        return trim((string) file_get_contents($path)) ?: '1';
    }

    private static function versionPath(): string
    {
        return self::root() . '/version.txt';
    }

    private static function root(): string
    {
        return base_path('storage/cache/sql');
    }

    private static function write(string $path, mixed $payload): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $content = is_string($payload)
            ? $payload
            : (json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        file_put_contents($path, $content, LOCK_EX);
    }
}
