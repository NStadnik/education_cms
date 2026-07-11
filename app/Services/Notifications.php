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
                $message = '<h2>Новина на модерації</h2><p><strong>' . e($title) . '</strong></p><p><a href="' . e($link) . '">Переглянути матеріал</a></p>';
            } else {
                $author = $db->fetch('select email from users where id=? and is_active=1', [(int) ($news['created_by'] ?? 0)]);
                $recipients = !empty($author['email']) ? [(string) $author['email']] : [];
                $labels = ['request_changes' => 'Новину повернуто на доопрацювання', 'publish' => 'Новину опубліковано', 'unpublish' => 'Новину знято з публікації'];
                $subject = ($labels[$action] ?? 'Статус новини змінено') . ': ' . $title;
                $message = '<h2>' . e($labels[$action] ?? 'Статус новини змінено') . '</h2><p><strong>' . e($title) . '</strong></p>'
                    . ($comment !== '' ? '<p>Коментар: ' . nl2br(e($comment)) . '</p>' : '') . '<p><a href="' . e($link) . '">Відкрити матеріал</a></p>';
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
            foreach ($fields as $field) {
                $key = (string) ($field['id'] ?? '');
                $value = $answers[$key] ?? '';
                if (is_array($value)) { $value = implode(', ', $value); }
                $rows .= '<tr><th style="text-align:left;padding:6px;border-bottom:1px solid #ddd">' . e((string) ($field['label'] ?? $key)) . '</th><td style="padding:6px;border-bottom:1px solid #ddd">' . nl2br(e((string) $value)) . '</td></tr>';
            }
            $link = self::absoluteUrl('/admin/forms/submissions?form_id=' . (int) $form['id']);
            Mailer::send($recipient, 'Нова відповідь: ' . (string) $form['title'], '<h2>Отримано нову відповідь</h2><p><strong>' . e((string) $form['title']) . '</strong></p><table>' . $rows . '</table><p><a href="' . e($link) . '">Переглянути відповіді</a></p>');
        });
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
