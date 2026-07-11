<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class Mailer
{
    public static function send(string $to, string $subject, string $html): void
    {
        $config = MailConfig::get();
        if (empty($config['enabled'])) { return; }
        foreach ([$to, (string) $config['from_email']] as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email)) {
                throw new RuntimeException('Некоректна email-адреса.');
            }
        }
        $boundaryHeaders = self::headers($config);
        $body = self::body($html);
        if (($config['transport'] ?? 'mail') === 'smtp') {
            self::smtp($config, $to, $subject, $boundaryHeaders, $body);
            return;
        }
        if (!mail($to, self::encode($subject), $body, implode("\r\n", $boundaryHeaders))) {
            throw new RuntimeException('PHP mail() не прийняв повідомлення.');
        }
    }

    private static function headers(array $config): array
    {
        $fromName = trim((string) ($config['from_name'] ?? ''));
        $from = ($fromName !== '' ? self::encode($fromName) . ' ' : '') . '<' . $config['from_email'] . '>';
        $headers = ['From: ' . $from, 'MIME-Version: 1.0', 'Content-Type: text/html; charset=UTF-8', 'Content-Transfer-Encoding: 8bit'];
        if (!empty($config['reply_to']) && filter_var($config['reply_to'], FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $config['reply_to'];
        }
        return $headers;
    }

    private static function body(string $html): string
    {
        return '<!doctype html><html lang="uk"><meta charset="utf-8"><body style="font-family:Arial,sans-serif;color:#172033;line-height:1.5">' . $html . '</body></html>';
    }

    private static function smtp(array $config, string $to, string $subject, array $headers, string $body): void
    {
        $host = (string) $config['smtp_host'];
        $port = (int) $config['smtp_port'];
        $encryption = (string) $config['smtp_encryption'];
        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errno, $error, 10, STREAM_CLIENT_CONNECT);
        if (!$socket) { throw new RuntimeException('Не вдалося підключитися до SMTP: ' . $error); }
        stream_set_timeout($socket, 10);
        try {
            self::expect($socket, [220]);
            self::command($socket, 'EHLO education-cms', [250]);
            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Не вдалося активувати TLS для SMTP.');
                }
                self::command($socket, 'EHLO education-cms', [250]);
            }
            if ((string) ($config['smtp_username'] ?? '') !== '') {
                self::command($socket, 'AUTH LOGIN', [334]);
                self::command($socket, base64_encode((string) $config['smtp_username']), [334]);
                self::command($socket, base64_encode((string) $config['smtp_password']), [235]);
            }
            self::command($socket, 'MAIL FROM:<' . $config['from_email'] . '>', [250]);
            self::command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::command($socket, 'DATA', [354]);
            $message = 'To: <' . $to . ">\r\nSubject: " . self::encode($subject) . "\r\n" . implode("\r\n", $headers) . "\r\n\r\n" . $body;
            $message = preg_replace('/(?m)^\./', '..', str_replace(["\r\n", "\r"], "\n", $message)) ?? $message;
            fwrite($socket, str_replace("\n", "\r\n", $message) . "\r\n.\r\n");
            self::expect($socket, [250]);
            self::command($socket, 'QUIT', [221]);
        } finally { fclose($socket); }
    }

    private static function command($socket, string $command, array $codes): void
    {
        fwrite($socket, $command . "\r\n");
        self::expect($socket, $codes);
    }

    private static function expect($socket, array $codes): void
    {
        $response = '';
        do {
            $line = fgets($socket, 1024);
            if ($line === false) { throw new RuntimeException('SMTP-сервер не відповідає.'); }
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');
        if (!in_array((int) substr($response, 0, 3), $codes, true)) {
            throw new RuntimeException('Помилка SMTP: ' . trim($response));
        }
    }

    private static function encode(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], '', $value)) . '?=';
    }
}
