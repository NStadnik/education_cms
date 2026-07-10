<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

final class LcloudApiController extends BaseController
{
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 100;

    public function publications(Request $request): Response
    {
        $rawIds = $request->input('id');
        if ($rawIds !== null && trim((string) $rawIds) !== '') {
            return $this->jsonResponse($this->findByIds((string) $rawIds));
        }

        $start = max(0, (int) $request->input('start', 0));
        $limit = min(self::MAX_LIMIT, max(1, (int) $request->input('limit', self::DEFAULT_LIMIT)));

        $items = $this->db()->fetchAll(
            'select
                n.id,
                coalesce(n.published_at, n.created_at) as date,
                n.title,
                0 as autor,
                n.slug as alt_name,
                0 as comm_num,
                case when n.status = ? then ? else ? end as approve
             from news n
             where n.status in (?, ?)
             order by coalesce(n.published_at, n.created_at) desc, n.id desc
             limit ' . $start . ', ' . $limit,
            ['published', 'publish', 'pending', 'published', 'draft']
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
                0 as autor,
                n.slug as alt_name,
                0 as comm_num,
                case when n.status = ? then ? else ? end as approve
             from news n
             where n.id in (' . $placeholders . ')
             order by coalesce(n.published_at, n.created_at) desc, n.id desc',
            array_merge(['published', 'publish', 'pending'], $ids)
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
                'autor' => '0',
                'alt_name' => (string) ($item['alt_name'] ?? ''),
                'comm_num' => '0',
                'approve' => (string) ($item['approve'] ?? 'pending'),
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
                'Access-Control-Allow-Origin' => '*',
                'Cache-Control' => 'no-store, max-age=0',
            ]
        );
    }
}
