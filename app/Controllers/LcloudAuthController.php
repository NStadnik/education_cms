<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Services\LcloudConfig;
use Throwable;

final class LcloudAuthController extends BaseController
{
    public function login(Request $request): Response
    {
        $config = $this->config();
        if (empty($config['enabled']) || trim((string) ($config['sso_secret'] ?? '')) === '') {
            return $this->error('Інтеграцію з ЛКЛАУД не налаштовано.', 503);
        }

        try {
            $claims = $this->verifyToken(trim((string) $request->input('token', '')), $config);
            $userId = $this->resolveUser($claims);
            if (!Container::get('auth')->loginById($userId)) {
                throw new \RuntimeException('Обліковий запис викладача неактивний.');
            }
            $this->audit('sso_login', 'external_identity', $userId, 'provider=lcloud; subject=' . (string) $claims['sub']);
            redirect('/admin/news/edit');
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 401);
        }
    }

    private function verifyToken(string $token, array $config): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Некоректний SSO-токен.');
        }
        [$headerPart, $payloadPart, $signaturePart] = $parts;
        $header = json_decode($this->base64UrlDecode($headerPart), true);
        $claims = json_decode($this->base64UrlDecode($payloadPart), true);
        if (!is_array($header) || !is_array($claims) || ($header['alg'] ?? '') !== 'HS256') {
            throw new \RuntimeException('Непідтримуваний формат SSO-токена.');
        }
        $expected = hash_hmac('sha256', $headerPart . '.' . $payloadPart, (string) $config['sso_secret'], true);
        $actual = $this->base64UrlDecode($signaturePart);
        if (!hash_equals($expected, $actual)) {
            throw new \RuntimeException('Підпис SSO-токена недійсний.');
        }

        $now = time();
        $issuer = (string) ($config['issuer'] ?? 'lcloud');
        $audience = (string) ($config['audience'] ?? 'education-cms');
        if (($claims['iss'] ?? '') !== $issuer || ($claims['aud'] ?? '') !== $audience) {
            throw new \RuntimeException('SSO-токен призначений для іншої системи.');
        }
        if ((int) ($claims['exp'] ?? 0) < $now || (int) ($claims['iat'] ?? 0) > $now + 60) {
            throw new \RuntimeException('Строк дії SSO-токена минув.');
        }
        if ((int) ($claims['exp'] ?? 0) - (int) ($claims['iat'] ?? 0) > 300) {
            throw new \RuntimeException('Строк дії SSO-токена завеликий.');
        }
        foreach (['sub', 'jti'] as $required) {
            if (trim((string) ($claims[$required] ?? '')) === '') {
                throw new \RuntimeException('SSO-токен не містить обов’язкових полів.');
            }
        }

        $nonceHash = hash('sha256', (string) $claims['jti']);
        try {
            $this->db()->execute(
                'insert into external_auth_nonces (provider, nonce_hash, expires_at, used_at) values (?, ?, ?, ?)',
                ['lcloud', $nonceHash, date('c', (int) $claims['exp']), date('c')]
            );
        } catch (Throwable) {
            throw new \RuntimeException('SSO-токен уже було використано.');
        }
        $this->db()->execute('delete from external_auth_nonces where expires_at < ?', [date('c', $now - 86400)]);
        return $claims;
    }

    private function resolveUser(array $claims): int
    {
        $subject = trim((string) $claims['sub']);
        $identity = $this->db()->fetch(
            'select u.id, u.is_active from external_identities ei join users u on u.id=ei.user_id where ei.provider=? and ei.external_user_id=?',
            ['lcloud', $subject]
        );
        if ($identity) {
            if (!(int) $identity['is_active']) {
                throw new \RuntimeException('Обліковий запис викладача деактивовано.');
            }
            return (int) $identity['id'];
        }

        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('ЛКЛАУД передав некоректну email-адресу.');
        }
        if ($email !== '' && $this->db()->fetch('select id from users where email=?', [$email])) {
            throw new \RuntimeException('Користувач із цією email-адресою вже існує. Адміністратор має прив’язати облікові записи.');
        }
        if ($email === '') {
            $email = 'lcloud-' . substr(hash('sha256', $subject), 0, 24) . '@invalid.local';
        }
        $name = trim((string) ($claims['name'] ?? '')) ?: 'Викладач ЛКЛАУД';
        $now = date('c');
        $this->db()->pdo()->beginTransaction();
        try {
            $this->db()->execute(
                'insert into users (name,email,password_hash,role,is_active,created_at) values (?,?,?,?,1,?)',
                [$name, $email, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), 'teacher', $now]
            );
            $userId = (int) $this->db()->lastInsertId();
            $this->db()->execute(
                'insert into external_identities (provider,external_user_id,user_id,external_institution_id,created_at,updated_at) values (?,?,?,?,?,?)',
                ['lcloud', $subject, $userId, trim((string) ($claims['institution_id'] ?? '')) ?: null, $now, $now]
            );
            $this->db()->pdo()->commit();
            return $userId;
        } catch (Throwable $e) {
            if ($this->db()->pdo()->inTransaction()) { $this->db()->pdo()->rollBack(); }
            throw $e;
        }
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) { throw new \RuntimeException('Некоректне кодування SSO-токена.'); }
        return $decoded;
    }

    private function config(): array
    {
        return LcloudConfig::get();
    }

    private function error(string $message, int $status): Response
    {
        return new Response('<!doctype html><meta charset="utf-8"><title>ЛКЛАУД</title><p>' . e($message) . '</p>', $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
