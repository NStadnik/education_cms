<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use Throwable;

final class Notifications
{
    public static function news(array $news, string $action, string $comment = ''): void
    {
        if (empty(MailConfig::get()['notify_news'])) { return; }
        self::safely(function () use ($news, $action, $comment): void {
            $db = Container::get('db');
            $title = (string) ($news['title'] ?? 'Новина');
            $link = self::absoluteUrl('/admin/news/edit?id=' . (int) $news['id']);
            if ($action === 'submit') {
                $recipients = self::usersWithAnyPermission(['news.review', 'news.publish']);
                $subject = 'Нова новина очікує модерації: ' . $title;
                $author = $db->fetch('select name,email from users where id=?', [(int) ($news['created_by'] ?? 0)]);
                $message = self::heading('Новина очікує перевірки', 'Матеріал надіслано до редакційної черги.')
                    . self::statusCard('На модерації', '#b45309', $title, 'Автор: ' . (string) ($author['name'] ?? 'Не вказано'))
                    . self::button($link, 'Переглянути та модерувати');
            } else {
                $author = $db->fetch('select email from users where id=? and is_active=1', [(int) ($news['created_by'] ?? 0)]);
                $recipients = !empty($author['email']) ? [(string) $author['email']] : [];
                $labels = ['request_changes' => 'Новину повернуто на доопрацювання', 'publish' => 'Новину опубліковано', 'unpublish' => 'Новину знято з публікації'];
                $subject = ($labels[$action] ?? 'Статус новини змінено') . ': ' . $title;
                $colors = ['request_changes' => '#b45309', 'publish' => '#15803d', 'unpublish' => '#475569'];
                $statuses = ['request_changes' => 'Потрібне доопрацювання', 'publish' => 'Опубліковано', 'unpublish' => 'Чернетка'];
                $message = self::heading($labels[$action] ?? 'Статус новини змінено', 'Редакційний статус вашого матеріалу оновлено.')
                    . self::statusCard($statuses[$action] ?? 'Оновлено', $colors[$action] ?? '#123b70', $title, '')
                    . ($comment !== '' ? '<div style="margin:20px 0;padding:16px 18px;border-left:4px solid #f59e0b;background:#fffbeb;border-radius:6px"><div style="margin-bottom:5px;font-size:12px;font-weight:700;text-transform:uppercase;color:#92400e">Коментар модератора</div><div style="color:#451a03">' . nl2br(e($comment)) . '</div></div>' : '')
                    . self::button($link, 'Відкрити матеріал');
            }
            foreach (array_unique($recipients) as $email) {
                try { Mailer::send($email, $subject, $message); }
                catch (Throwable $e) { error_log('[mail notification] ' . $email . ': ' . $e->getMessage()); }
            }
        });
    }

    public static function formSubmission(array $form, array $fields, array $answers): void
    {
        if (empty(MailConfig::get()['notify_forms'])) { return; }
        self::safely(function () use ($form, $fields, $answers): void {
            $owner = Container::get('db')->fetch('select email from users where id=? and is_active=1', [(int) ($form['created_by'] ?? 0)]);
            $mailConfig = MailConfig::get();
            $recipient = trim((string) ($owner['email'] ?? ''));
            if ($recipient === '') { $recipient = trim((string) ($mailConfig['reply_to'] ?? '')); }
            if ($recipient === '') { $recipient = trim((string) ($mailConfig['from_email'] ?? '')); }
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) { return; }
            $rows = '';
            $rowIndex = 0;
            foreach ($fields as $field) {
                $key = (string) ($field['id'] ?? '');
                $value = $answers[$key] ?? '';
                if (is_array($value)) { $value = implode(', ', $value); }
                $background = $rowIndex++ % 2 === 0 ? '#ffffff' : '#f8fafc';
                $rows .= '<tr style="background:' . $background . '"><th valign="top" style="width:38%;text-align:left;padding:11px 12px;border-bottom:1px solid #e2e8f0;color:#475569;font-size:13px">' . e((string) ($field['label'] ?? $key)) . '</th><td valign="top" style="padding:11px 12px;border-bottom:1px solid #e2e8f0;color:#172033;font-size:14px">' . ($value !== '' ? nl2br(e((string) $value)) : '<span style="color:#94a3b8">Не заповнено</span>') . '</td></tr>';
            }
            $link = self::absoluteUrl('/admin/forms/submissions?form_id=' . (int) $form['id']);
            $message = self::heading('Отримано нову відповідь', 'Форма «' . (string) $form['title'] . '» щойно отримала нове заповнення.')
                . '<div style="margin:20px 0;border:1px solid #dbe3ef;border-radius:8px;overflow:hidden"><table role="presentation" width="100%" cellspacing="0" cellpadding="0">' . $rows . '</table></div>'
                . self::button($link, 'Переглянути всі відповіді');
            Mailer::send($recipient, 'Нова відповідь: ' . (string) $form['title'], $message);
        });
    }

    private static function heading(string $title, string $subtitle): string
    {
        return '<h1 style="margin:0 0 8px;font-size:25px;line-height:1.25;color:#0f2745">' . e($title) . '</h1><p style="margin:0 0 22px;color:#64748b;font-size:15px">' . e($subtitle) . '</p>';
    }

    private static function statusCard(string $status, string $color, string $title, string $meta): string
    {
        return '<div style="margin:18px 0;padding:18px;border:1px solid #dbe3ef;border-radius:8px;background:#f8fafc"><span style="display:inline-block;padding:4px 9px;border-radius:999px;background:' . e($color) . ';color:#ffffff;font-size:11px;font-weight:700;text-transform:uppercase">' . e($status) . '</span><div style="margin-top:11px;font-size:18px;font-weight:700;color:#172033">' . e($title) . '</div>' . ($meta !== '' ? '<div style="margin-top:5px;color:#64748b;font-size:13px">' . e($meta) . '</div>' : '') . '</div>';
    }

    private static function button(string $url, string $label): string
    {
        return '<table role="presentation" cellspacing="0" cellpadding="0" style="margin-top:22px"><tr><td style="border-radius:7px;background:#1769aa"><a href="' . e($url) . '" style="display:inline-block;padding:12px 20px;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px">' . e($label) . '</a></td></tr></table>';
    }

    private static function usersWithAnyPermission(array $permissions): array
    {
        $auth = Container::get('auth');
        $roles = $auth->rolePermissionsForAll();
        $emails = [];
        foreach (Container::get('db')->fetchAll('select email,role from users where is_active=1') as $user) {
            $allowed = $roles[$user['role']] ?? [];
            if ($user['role'] === 'super_admin' || in_array('*', $allowed, true) || array_intersect($permissions, $allowed)) { $emails[] = (string) $user['email']; }
        }
        return $emails;
    }

    private static function absoluteUrl(string $path): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
        return $scheme . '://' . $host . url($path);
    }

    private static function safely(callable $callback): void
    {
        if (empty(MailConfig::get()['enabled'])) { return; }
        try { $callback(); } catch (Throwable $e) { error_log('[mail notification] ' . $e->getMessage()); }
    }
}
