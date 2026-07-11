<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\LcloudConfig;

final class LcloudApiController extends BaseController
{
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 100;

    public function publications(Request $request): Response
    {
        if (!$this->isAuthorized()) {
            return $this->jsonError('Необхідна авторизація ЛКЛАУД.', 401);
        }
        $rawIds = $request->input('id');
        if ($rawIds !== null && trim((string) $rawIds) !== '') {
            return $this->jsonResponse($this->findByIds((string) $rawIds));
        }

        $start = max(0, (int) $request->input('start', 0));
        $limit = min(self::MAX_LIMIT, max(1, (int) $request->input('limit', self::DEFAULT_LIMIT)));

        $authorId = trim((string) $request->input('author_id', ''));
        $authorWhere = $authorId !== '' ? ' and exists (select 1 from external_identities fa where fa.user_id=n.created_by and fa.provider=? and fa.external_user_id=?)' : '';
        $params = ['lcloud', 'published', 'publish', 'pending', 'published'];
        if ($authorId !== '') { array_push($params, 'lcloud', $authorId); }
        $items = $this->db()->fetchAll(
            'select
                n.id,
                coalesce(n.published_at, n.created_at) as date,
                n.title,
                (select ei.external_user_id from external_identities ei where ei.user_id=n.created_by and ei.provider=? limit 1) as autor,
                n.slug as alt_name,
                0 as comm_num,
                case when n.status = ? then ? else ? end as approve
             from news n
             where n.status = ?' . $authorWhere . '
             order by coalesce(n.published_at, n.created_at) desc, n.id desc
             limit ' . $start . ', ' . $limit,
            $params
        );

        return $this->jsonResponse($this->normalizeItems($items));
    }

    private function findByIds(string $rawIds): array
    {
        $decoded = json_decode($rawIds, true);
        if (!is_array($decoded)) {
            $decoded = json_decode(urldecode($rawIds), true);
        }

        if (!is_array($decoded)) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $value): int => filter_var($value, FILTER_VALIDATE_INT) !== false
                    ? (int) $value
                    : 0,
                $decoded
            ),
            static fn (int $value): bool => $value > 0
        )));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $items = $this->db()->fetchAll(
            'select
                n.id,
                coalesce(n.published_at, n.created_at) as date,
                n.title,
                (select ei.external_user_id from external_identities ei where ei.user_id=n.created_by and ei.provider=? limit 1) as autor,
                n.slug as alt_name,
                0 as comm_num,
                case when n.status = ? then ? else ? end as approve
             from news n
             where n.id in (' . $placeholders . ') and n.status = ?
             order by coalesce(n.published_at, n.created_at) desc, n.id desc',
            array_merge(['lcloud', 'published', 'publish', 'pending'], $ids, ['published'])
        );

        return $this->normalizeItems($items);
    }

    private function normalizeItems(array $items): array
    {
        return array_map(static function (array $item): array {
            $timestamp = strtotime((string) ($item['date'] ?? ''));

            return [
                'id' => (int) ($item['id'] ?? 0),
                'date' => $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : (string) ($item['date'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'autor' => (string) ($item['autor'] ?? '0'),
                'external_author_id' => ($item['autor'] ?? null) !== null ? (string) $item['autor'] : null,
                'alt_name' => (string) ($item['alt_name'] ?? ''),
                'comm_num' => '0',
                'approve' => (string) ($item['approve'] ?? 'pending'),
                'view_url' => url('/news/' . (string) ($item['alt_name'] ?? '')),
                'edit_url' => url('/admin/news/edit?id=' . (int) ($item['id'] ?? 0)),
            ];
        }, $items);
    }

    private function jsonResponse(array $items): Response
    {
        return new Response(
            json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
            200,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Access-Control-Allow-Origin' => $this->allowedOrigin(),
                'Cache-Control' => 'no-store, max-age=0',
            ]
        );
    }

    private function isAuthorized(): bool
    {
        $expected = trim((string) ($this->config()['api_key'] ?? ''));
        if ($expected === '') {
            return true; // Backward compatibility until an API key is configured.
        }
        $provided = trim((string) ($_SERVER['HTTP_X_LCLOUD_KEY'] ?? ''));
        $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        if ($provided === '' && str_starts_with($authorization, 'Bearer ')) {
            $provided = trim(substr($authorization, 7));
        }
        return $provided !== '' && hash_equals($expected, $provided);
    }

    private function allowedOrigin(): string
    {
        $configured = trim((string) ($this->config()['allowed_origin'] ?? ''));
        return $configured !== '' ? $configured : '*';
    }

    private function config(): array
    {
        return LcloudConfig::get();
    }

    private function jsonError(string $message, int $status): Response
    {
        return new Response(json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE) ?: '{}', $status, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }
}
