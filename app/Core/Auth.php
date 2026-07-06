<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public const ROLE_LABELS = [
        'admin' => 'Адміністратор',
        'editor' => 'Редактор',
        'publisher' => 'Публікатор',
        'finance_editor' => 'Фінансовий редактор',
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
        'finance.manage' => [
            'group' => 'Дані',
            'label' => 'Фінанси',
            'description' => 'Доступ до фінансових матеріалів і службових даних.',
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
            'finance_editor' => ['media.manage', 'finance.manage'],
            'viewer' => [],
        ];
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

        $allowed = self::rolePermissions()[$user['role']] ?? [];
        return in_array('*', $allowed, true) || in_array($permission, $allowed, true);
    }
}
