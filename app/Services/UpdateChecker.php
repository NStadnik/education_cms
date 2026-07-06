<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class UpdateChecker
{
    private const USER_AGENT = 'EducationCMS-Updater';

    public function __construct(
        private readonly string $owner,
        private readonly string $repo,
        private readonly string $currentVersion
    ) {
    }

    public function latest(): array
    {
        $url = "https://api.github.com/repos/{$this->owner}/{$this->repo}/releases/latest";
        $release = $this->json($url);
        $version = ltrim((string) ($release['tag_name'] ?? ''), 'vV');
        if ($version === '') {
            throw new RuntimeException('GitHub Release не містить номера версії.');
        }

        $assets = is_array($release['assets'] ?? null) ? $release['assets'] : [];
        $package = $this->findAsset($assets, '/education-cms-v?' . preg_quote($version, '/') . '\.zip$/i')
            ?? $this->findAsset($assets, '/\.zip$/i');
        $checksum = $this->findAsset($assets, '/\.sha256$/i');
        $packageUrl = (string) ($package['browser_download_url'] ?? '');
        $packageName = (string) ($package['name'] ?? '');
        $packageSource = 'asset';
        if ($packageUrl === '' && !empty($release['zipball_url'])) {
            $packageUrl = (string) $release['zipball_url'];
            $packageName = 'GitHub source zip';
            $packageSource = 'zipball';
        }

        return [
            'version' => $version,
            'tag' => (string) ($release['tag_name'] ?? ''),
            'name' => (string) ($release['name'] ?? ''),
            'body' => (string) ($release['body'] ?? ''),
            'html_url' => (string) ($release['html_url'] ?? ''),
            'published_at' => (string) ($release['published_at'] ?? ''),
            'package_url' => $packageUrl,
            'package_name' => $packageName,
            'package_source' => $packageSource,
            'checksum_url' => $checksum['browser_download_url'] ?? '',
            'checksum_name' => $checksum['name'] ?? '',
            'current_version' => $this->currentVersion,
            'has_update' => version_compare($version, $this->currentVersion, '>'),
        ];
    }

    public function download(string $url): string
    {
        if (preg_match('#^https://api\.github\.com/repos/[^/]+/[^/]+/(?:zipball|tarball)(?:/|$)#i', $url)) {
            return $this->request($url, 'application/vnd.github+json');
        }

        return $this->request($url, 'application/octet-stream');
    }

    private function json(string $url): array
    {
        $body = $this->request($url, 'application/vnd.github+json');
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException('GitHub повернув некоректну JSON-відповідь.');
        }

        return $data;
    }

    private function request(string $url, string $accept): string
    {
        if (!preg_match('#^https://(?:api\.)?github\.com/#i', $url)
            && !preg_match('#^https://github\.com/#i', $url)
            && !preg_match('#^https://objects\.githubusercontent\.com/#i', $url)
            && !preg_match('#^https://release-assets\.githubusercontent\.com/#i', $url)) {
            throw new RuntimeException('Дозволені лише GitHub URL.');
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_USERAGENT => self::USER_AGENT,
                CURLOPT_HTTPHEADER => [
                    'Accept: ' . $accept,
                    'X-GitHub-Api-Version: 2022-11-28',
                ],
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($body === false || $status >= 400) {
                throw new RuntimeException($this->errorMessage($url, $status, is_string($body) ? $body : '', $error));
            }
            return (string) $body;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 120,
                'ignore_errors' => true,
                'header' => "User-Agent: " . self::USER_AGENT . "\r\nAccept: {$accept}\r\nX-GitHub-Api-Version: 2022-11-28\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $status = $this->statusFromHeaders($http_response_header ?? []);
        if ($body === false) {
            throw new RuntimeException($this->errorMessage($url, $status, '', ''));
        }
        if ($status >= 400) {
            throw new RuntimeException($this->errorMessage($url, $status, (string) $body, ''));
        }

        return $body;
    }

    private function errorMessage(string $url, int $status, string $body, string $transportError): string
    {
        $data = json_decode($body, true);
        $githubMessage = is_array($data) ? (string) ($data['message'] ?? '') : '';

        if (str_contains($url, '/releases/latest') && $status === 404) {
            return 'GitHub не знайшов опублікований Release для ' . $this->owner . '/' . $this->repo . '. Створіть release з тегом v' . $this->currentVersion . ' або новішим і додайте zip-архів оновлення.';
        }
        if ($status === 403 && stripos($githubMessage, 'rate limit') !== false) {
            return 'GitHub тимчасово обмежив запити без авторизації (HTTP 403 rate limit). Спробуйте пізніше.';
        }
        if ($status === 403) {
            return 'GitHub відхилив запит (HTTP 403). Перевірте доступність репозиторію або обмеження хостингу.';
        }
        if ($status === 404) {
            return 'GitHub не знайшов потрібний файл або репозиторій (HTTP 404). Перевірте URL релізу та назви asset-файлів.';
        }
        if ($status >= 400) {
            return 'GitHub повернув HTTP ' . $status . ($githubMessage !== '' ? ': ' . $githubMessage : '.');
        }
        if ($transportError !== '') {
            return 'Не вдалося підключитися до GitHub: ' . $transportError;
        }

        return 'Не вдалося завантажити дані з GitHub. Перевірте, чи хостинг дозволяє вихідні HTTPS-запити.';
    }

    private function statusFromHeaders(array $headers): int
    {
        $status = 0;
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/i', (string) $header, $match)) {
                $status = (int) $match[1];
            }
        }

        return $status;
    }

    private function findAsset(array $assets, string $pattern): ?array
    {
        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }
            $name = (string) ($asset['name'] ?? '');
            if ($name !== '' && preg_match($pattern, $name)) {
                return $asset;
            }
        }

        return null;
    }
}
