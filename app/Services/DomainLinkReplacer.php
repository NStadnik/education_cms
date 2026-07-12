<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;
use Throwable;

final class DomainLinkReplacer
{
    private const FIELDS = [
        'settings' => ['value'],
        'pages' => ['excerpt', 'blocks_json'],
        'news' => ['body'],
        'media' => ['caption', 'description'],
        'forms' => ['description', 'fields_json', 'settings_json'],
    ];

    public function __construct(private readonly Database $db)
    {
    }

    public function replace(string $oldUrl, string $newUrl): array
    {
        $oldUrl = $this->normalizeOrigin($oldUrl, 'старого');
        $newUrl = $this->normalizeOrigin($newUrl, 'нового');
        if ($oldUrl === $newUrl) {
            throw new InvalidArgumentException('Старий і новий домени мають відрізнятися.');
        }

        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        $result = ['records' => 0, 'replacements' => 0, 'tables' => []];

        try {
            foreach (self::FIELDS as $table => $fields) {
                $tableRecords = 0;
                foreach ($fields as $field) {
                    $rows = $this->db->fetchAll(
                        "select " . ($table === 'settings' ? 'name' : 'id') . " as row_key, {$field} as content from {$table}"
                    );
                    foreach ($rows as $row) {
                        [$content, $count] = $this->replaceInContent((string) ($row['content'] ?? ''), $oldUrl, $newUrl);
                        if ($count === 0) {
                            continue;
                        }
                        $keyField = $table === 'settings' ? 'name' : 'id';
                        $this->db->execute("update {$table} set {$field}=? where {$keyField}=?", [$content, $row['row_key']]);
                        $result['records']++;
                        $result['replacements'] += $count;
                        $tableRecords++;
                    }
                }
                if ($tableRecords > 0) {
                    $result['tables'][$table] = $tableRecords;
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return $result + ['old_url' => $oldUrl, 'new_url' => $newUrl];
    }

    private function normalizeOrigin(string $url, string $label): string
    {
        $url = rtrim(trim($url), '/');
        $parts = parse_url($url);
        if (!filter_var($url, FILTER_VALIDATE_URL)
            || !is_array($parts)
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])
            || (($parts['path'] ?? '') !== '' && ($parts['path'] ?? '') !== '/')) {
            throw new InvalidArgumentException("Вкажіть коректну адресу {$label} домену без шляху, наприклад https://example.edu.ua.");
        }

        return strtolower((string) $parts['scheme']) . '://' . strtolower((string) $parts['host']) . (isset($parts['port']) ? ':' . (int) $parts['port'] : '');
    }

    private function replaceInContent(string $content, string $oldUrl, string $newUrl): array
    {
        $count = 0;
        $pattern = '~' . preg_quote($oldUrl, '~') . '(?=/|[?#]|$)~i';
        $content = preg_replace($pattern, $newUrl, $content, -1, $plainCount) ?? $content;
        $count += $plainCount;

        // PHP JSON may store URLs with escaped slashes (https:\/\/example.test).
        $escapedOld = str_replace('/', '\\/', $oldUrl);
        $escapedNew = str_replace('/', '\\/', $newUrl);
        $escapedPattern = '~' . preg_quote($escapedOld, '~') . '(?=\\\\/|[?#]|$)~i';
        $content = preg_replace($escapedPattern, $escapedNew, $content, -1, $escapedCount) ?? $content;
        $count += $escapedCount;

        return [$content, $count];
    }
}
