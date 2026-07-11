<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;

final class MailConfig
{
    public static function get(): array
    {
        $rows = Container::get('db')->fetchAll("select name,value from settings where name like 'mail_%'");
        $stored = array_column($rows, 'value', 'name');

        return [
            'enabled' => ($stored['mail_enabled'] ?? '0') === '1',
            'notify_news' => ($stored['mail_notify_news'] ?? '1') === '1',
            'notify_forms' => ($stored['mail_notify_forms'] ?? '1') === '1',
            'transport' => (string) ($stored['mail_transport'] ?? 'mail'),
            'from_email' => (string) ($stored['mail_from_email'] ?? ''),
            'from_name' => (string) ($stored['mail_from_name'] ?? ''),
            'reply_to' => (string) ($stored['mail_reply_to'] ?? ''),
            'smtp_host' => (string) ($stored['mail_smtp_host'] ?? ''),
            'smtp_port' => (int) ($stored['mail_smtp_port'] ?? 587),
            'smtp_encryption' => (string) ($stored['mail_smtp_encryption'] ?? 'tls'),
            'smtp_username' => (string) ($stored['mail_smtp_username'] ?? ''),
            'smtp_password' => (string) ($stored['mail_smtp_password'] ?? ''),
        ];
    }
}
