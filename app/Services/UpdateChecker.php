<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class UpdateChecker
{
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

        return [
            'version' => $version,
            'tag' => (string) ($release['tag_name'] ?? ''),
            'name' => (string) ($release['name'] ?? ''),
            'body' => (string) ($release['body'] ?? ''),
            'html_url' => (string) ($release['html_url'] ?? ''),
            'published_at' => (string) ($release['published_at'] ?? ''),
            'package_url' => $package['browser_download_url'] ?? '',
            'package_name' => $package['name'] ?? '',
            'checksum_url' => $checksum['browser_download_url'] ?? '',
            'checksum_name' => $checksum['name'] ?? '',
            'current_version' => $this->currentVersion,
            'has_update' => version_compare($version, $this->currentVersion, '>'),
        ];
    }

    public function download(string $url): string
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
                CURLOPT_USERAGENT => 'EducationCMS-Updater',
                CURLOPT_HTTPHEADER => ['Accept: application/octet-stream'],
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($body === false || $status >= 400) {
                throw new RuntimeException($error ?: 'GitHub повернув помилку завантаження.');
            }
            return (string) $body;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 120,
                'header' => "User-Agent: EducationCMS-Updater\r\nAccept: application/octet-stream\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RuntimeException('Не вдалося завантажити файл із GitHub.');
        }

        return $body;
    }

    private function json(string $url): array
    {
        $body = $this->download($url);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException('GitHub повернув некоректну JSON-відповідь.');
        }

        return $data;
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
