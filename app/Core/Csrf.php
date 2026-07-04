<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function verify(): void
    {
        $token = $_POST['_csrf'] ?? '';
        if (!$token || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
            http_response_code(419);
            if (Debug::enabled(base_path())) {
                exit(
                    'CSRF token mismatch' . "\n\n" .
                    'POST token: ' . ($token ? 'present' : 'missing') . "\n" .
                    'Session token: ' . (!empty($_SESSION['_csrf']) ? 'present' : 'missing') . "\n" .
                    'Session id: ' . session_id() . "\n" .
                    'Session path: ' . session_save_path()
                );
            }
            exit('CSRF token mismatch');
        }
    }
}
