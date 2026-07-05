<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public function __construct(private readonly Database $db)
    {
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

        $map = [
            'admin' => ['*'],
            'editor' => ['pages.manage', 'news.manage', 'documents.manage', 'media.manage', 'public_info.manage'],
            'publisher' => ['pages.manage', 'news.manage', 'documents.manage', 'media.manage', 'public_info.manage'],
            'finance_editor' => ['documents.manage', 'media.manage', 'public_info.manage', 'finance.manage'],
            'viewer' => [],
        ];

        $allowed = $map[$user['role']] ?? [];
        return in_array('*', $allowed, true) || in_array($permission, $allowed, true);
    }
}
