<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public const ROLE_LABELS = [
        'admin' => 'Адміністратор',
        'editor' => 'Редактор',
        'publisher' => 'Публікатор',
        'viewer' => 'Переглядач',
    ];

    public const PERMISSION_CATALOG = [
        'pages.manage' => [
            'group' => 'Контент',
            'label' => 'Сторінки',
            'description' => 'Створення, редагування, публікація та видалення сторінок.',
        ],
        'news.manage' => [
            'group' => 'Контент',
            'label' => 'Новини',
            'description' => 'Керування новинами, категоріями та статусами публікації.',
        ],
        'media.manage' => [
            'group' => 'Медіа',
            'label' => 'Медіафайли',
            'description' => 'Перегляд, завантаження, перейменування та видалення файлів.',
        ],
        'users.manage' => [
            'group' => 'Адміністрування',
            'label' => 'Користувачі',
            'description' => 'Створення користувачів, зміна ролей, активація та видалення.',
        ],
        'settings.manage' => [
            'group' => 'Адміністрування',
            'label' => 'Налаштування',
            'description' => 'Загальні налаштування сайту, імпорт і глобальні поля.',
        ],
        'updates.manage' => [
            'group' => 'Система',
            'label' => 'Оновлення',
            'description' => 'Перегляд і запуск оновлень системи.',
        ],
        'content.manage_all' => [
            'group' => 'Контент',
            'label' => 'Чужі матеріали',
            'description' => 'Редагування та видалення сторінок, новин і медіафайлів інших користувачів.',
        ],
    ];

    public function __construct(private readonly Database $db)
    {
    }

    public static function rolePermissions(): array
    {
        return [
            'admin' => ['*'],
            'editor' => ['pages.manage', 'news.manage', 'media.manage'],
            'publisher' => ['pages.manage', 'news.manage', 'media.manage'],
            'viewer' => [],
        ];
    }

    public static function defaultRolesConfig(): array
    {
        $roles = [];
        foreach (self::ROLE_LABELS as $slug => $label) {
            $roles[$slug] = [
                'label' => $label,
                'permissions' => self::rolePermissions()[$slug] ?? [],
            ];
        }

        return $roles;
    }

    public function roles(): array
    {
        $stored = $this->db->fetch('select value from settings where name = ?', ['user_roles']);
        $decoded = json_decode((string) ($stored['value'] ?? ''), true);
        if (!is_array($decoded) || $decoded === []) {
            return $this->defaultRoles();
        }

        $roles = [];
        foreach ($decoded as $key => $role) {
            $slug = $this->normalizeRoleSlug((string) $key);
            if ($slug === '' || $slug === 'super_admin' || !is_array($role)) {
                continue;
            }

            $permissions = $role['permissions'] ?? [];
            if (!is_array($permissions)) {
                $permissions = [];
            }

            $roles[$slug] = [
                'label' => trim((string) ($role['label'] ?? $slug)) ?: $slug,
                'permissions' => $this->sanitizePermissions($permissions),
            ];
        }

        return $roles ?: $this->defaultRoles();
    }

    public function roleLabels(): array
    {
        return array_map(static fn (array $role): string => $role['label'], $this->roles());
    }

    public function rolePermissionsForAll(): array
    {
        return array_map(static fn (array $role): array => $role['permissions'], $this->roles());
    }

    public function saveRoles(array $roles): void
    {
        $clean = [];
        foreach ($roles as $role) {
            if (!is_array($role)) {
                continue;
            }

            $slug = $this->normalizeRoleSlug((string) ($role['slug'] ?? ''));
            if ($slug === '' || $slug === 'super_admin') {
                continue;
            }
            if (isset($clean[$slug])) {
                throw new \InvalidArgumentException('Коди ролей мають бути унікальними.');
            }

            $label = trim((string) ($role['label'] ?? ''));
            $permissions = $role['permissions'] ?? [];
            $clean[$slug] = [
                'label' => $label !== '' ? $label : $slug,
                'permissions' => is_array($permissions) ? $this->sanitizePermissions($permissions) : [],
            ];
        }

        if ($clean === []) {
            throw new \InvalidArgumentException('Залиште щонайменше одну роль.');
        }

        $encoded = json_encode($clean, JSON_UNESCAPED_UNICODE);
        $this->db->execute('delete from settings where name = ?', ['user_roles']);
        $this->db->execute('insert into settings (name, value) values (?, ?)', ['user_roles', $encoded === false ? '{}' : $encoded]);
    }

    public function user(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        return $this->db->fetch('select * from users where id = ?', [$_SESSION['user_id']]);
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->db->fetch('select * from users where email = ? and is_active = 1', [$email]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        $_SESSION['user_id'] = $user['id'];
        session_regenerate_id(true);
        return true;
    }

    public function logout(): void
    {
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
    }

    public function require(): array
    {
        $user = $this->user();
        if (!$user) {
            redirect('/admin/login');
        }
        return $user;
    }

    public function can(string $permission): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }
        if (($user['role'] ?? '') === 'super_admin') {
            return true;
        }

        $allowed = $this->rolePermissionsForAll()[$user['role']] ?? [];
        return in_array('*', $allowed, true) || in_array($permission, $allowed, true);
    }

    private function defaultRoles(): array
    {
        return self::defaultRolesConfig();
    }

    private function sanitizePermissions(array $permissions): array
    {
        if (in_array('*', $permissions, true)) {
            return ['*'];
        }

        return array_values(array_intersect(array_keys(self::PERMISSION_CATALOG), array_map('strval', $permissions)));
    }

    private function normalizeRoleSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9_]+/', '_', $slug) ?? '';
        return trim($slug, '_');
    }
}
