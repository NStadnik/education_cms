<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\QueryCache;
use PDO;
use RuntimeException;

final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private readonly array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo) {
            return $this->pdo;
        }

        if (($this->config['driver'] ?? 'mysql') !== 'mysql') {
            throw new RuntimeException('Підтримується тільки MySQL/MariaDB.');
        }

        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('На сервері не увімкнено PHP-розширення pdo_mysql. Увімкніть його на хостингу.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $this->config['host'],
            $this->config['port'] ?? '3306',
            $this->config['name'],
            $this->config['charset'] ?? 'utf8mb4'
        );
        $this->pdo = new PDO($dsn, $this->config['user'], $this->config['password']);

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $this->pdo;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function cachedFetch(string $scope, string $sql, array $params = [], int $ttl = 600): ?array
    {
        $row = QueryCache::remember($scope, $sql, $params, fn (): ?array => $this->fetch($sql, $params), $ttl);
        return is_array($row) ? $row : null;
    }

    public function cachedFetchAll(string $scope, string $sql, array $params = [], int $ttl = 600): array
    {
        $rows = QueryCache::remember($scope, $sql, $params, fn (): array => $this->fetchAll($sql, $params), $ttl);
        return is_array($rows) ? $rows : [];
    }

    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->pdo()->prepare($sql);
        $ok = $stmt->execute($params);
        if ($ok && $this->invalidatesPublicQueryCache($sql)) {
            QueryCache::flush();
        }

        return $ok;
    }

    public function executeAffected(string $sql, array $params = []): int
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $affected = $stmt->rowCount();
        if ($affected > 0 && $this->invalidatesPublicQueryCache($sql)) {
            QueryCache::flush();
        }

        return $affected;
    }

    public function lastInsertId(): string
    {
        return $this->pdo()->lastInsertId();
    }

    private function invalidatesPublicQueryCache(string $sql): bool
    {
        if (!preg_match('/^\s*(insert|update|delete|replace|truncate|alter|drop|create)\b/i', $sql)) {
            return false;
        }

        return preg_match('/\b(settings|pages|news|news_categories|news_category_links|media)\b/i', $sql) === 1;
    }
}
