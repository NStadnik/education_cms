<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;

final class AdminHelp
{
    private array $topics;

    public function __construct(private readonly Auth $auth)
    {
        $topics = require base_path('config/admin_help.php');
        $this->topics = is_array($topics) ? $topics : [];
    }

    public function topic(string $key): ?array
    {
        if (!isset($this->topics[$key]) || !$this->isVisible($this->topics[$key])) {
            return null;
        }

        return $this->withKey($key, $this->topics[$key]);
    }

    public function topicForPath(string $path): string
    {
        $bestKey = 'dashboard';
        $bestLength = -1;

        foreach ($this->topics as $key => $topic) {
            if (!$this->isVisible($topic)) {
                continue;
            }

            $topicPath = (string) ($topic['path'] ?? '');
            $matches = ($topic['match'] ?? 'exact') === 'prefix'
                ? ($path === $topicPath || str_starts_with($path, rtrim($topicPath, '/') . '/'))
                : $path === $topicPath;

            if ($matches && strlen($topicPath) > $bestLength) {
                $bestKey = (string) $key;
                $bestLength = strlen($topicPath);
            }
        }

        return $bestKey;
    }

    public function search(string $query): array
    {
        $query = $this->lower(trim($query));
        if ($query === '') {
            return $this->visibleTopics();
        }

        $results = [];
        foreach ($this->visibleTopics() as $topic) {
            $haystack = [$topic['title'] ?? '', $topic['intro'] ?? '', ...($topic['keywords'] ?? [])];
            foreach ($topic['sections'] ?? [] as $section) {
                $haystack[] = $section['title'] ?? '';
                $haystack[] = $section['body'] ?? '';
                array_push($haystack, ...($section['steps'] ?? []));
            }

            if (str_contains($this->lower(implode(' ', array_map('strval', $haystack))), $query)) {
                $results[] = $topic;
            }
        }

        return $results;
    }

    public function related(array $topic): array
    {
        $related = [];
        foreach ($topic['related'] ?? [] as $key) {
            $item = $this->topic((string) $key);
            if ($item !== null) {
                $related[] = $item;
            }
        }

        return $related;
    }

    private function visibleTopics(): array
    {
        $visible = [];
        foreach ($this->topics as $key => $topic) {
            if ($this->isVisible($topic)) {
                $visible[] = $this->withKey((string) $key, $topic);
            }
        }
        return $visible;
    }

    private function isVisible(array $topic): bool
    {
        $permissions = $topic['permissions'] ?? [];
        if (!is_array($permissions) || $permissions === []) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($this->auth->can((string) $permission)) {
                return true;
            }
        }
        return false;
    }

    private function withKey(string $key, array $topic): array
    {
        return array_replace($topic, ['key' => $key]);
    }

    private function lower(string $value): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtr(strtolower($value), [
            'А' => 'а', 'Б' => 'б', 'В' => 'в', 'Г' => 'г', 'Ґ' => 'ґ', 'Д' => 'д',
            'Е' => 'е', 'Є' => 'є', 'Ж' => 'ж', 'З' => 'з', 'И' => 'и', 'І' => 'і',
            'Ї' => 'ї', 'Й' => 'й', 'К' => 'к', 'Л' => 'л', 'М' => 'м', 'Н' => 'н',
            'О' => 'о', 'П' => 'п', 'Р' => 'р', 'С' => 'с', 'Т' => 'т', 'У' => 'у',
            'Ф' => 'ф', 'Х' => 'х', 'Ц' => 'ц', 'Ч' => 'ч', 'Ш' => 'ш', 'Щ' => 'щ',
            'Ь' => 'ь', 'Ю' => 'ю', 'Я' => 'я',
        ]);
    }
}
