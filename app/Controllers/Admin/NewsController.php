<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\Files;
use Throwable;

final class NewsController extends \App\Controllers\AdminBaseController
{
    public function news(Request $request): Response
    {
        $this->guard();
        $query = trim((string) $request->input('q', ''));
        $pagination = $this->pagination($request);
        [$where, $params] = $this->searchWhere($query, ['title', 'slug', 'body', 'status']);
        $items = $this->db()->fetchAll(
            'select * from news ' . $where . ' order by id desc limit ' . $pagination['limit'] . ' offset ' . $pagination['offset'],
            $params
        );
        $total = (int) ($this->db()->fetch('select count(*) as c from news ' . $where, $params)['c'] ?? 0);

        if ($this->isAjaxRequest()) {
            return $this->listJson('admin/news/rows', ['items' => $items], $pagination, $total);
        }

        $stats = $this->statusStats('news');
        return $this->admin('admin/news/index', [
            'title' => 'Новини',
            'items' => $items,
            'total' => $total,
            'limit' => $pagination['limit'],
            'stats' => $stats,
        ]);
    }

    public function newsForm(Request $request): Response
    {
        $this->guard();
        $id = (int) $request->input('id', 0);
        $item = $id ? $this->db()->fetch('select * from news where id = ?', [$id]) : null;
        return $this->admin('admin/news/form', ['title' => 'Новина', 'item' => $item]);
    }

    public function newsSave(Request $request): Response
    {
        $this->guard('news.manage');
        Csrf::verify();
        try {
            $now = date('c');
            $id = (int) $request->input('id', 0);
            $slug = $this->slug((string) $request->input('slug', $request->input('title')));
            $publishedAt = $request->input('status') === 'published' ? ($request->input('published_at') ?: $now) : null;
            $data = [
                $request->input('title'),
                $slug,
                $request->input('body'),
                $request->input('status', 'draft'),
                $publishedAt,
                $now,
            ];
            if ($id) {
                $this->db()->execute('update news set title=?, slug=?, body=?, status=?, published_at=?, updated_at=? where id=?', [...$data, $id]);
            } else {
                $this->db()->execute('insert into news (title, slug, body, status, published_at, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?)', [...$data, $now]);
                $id = (int) $this->db()->lastInsertId();
            }
            $this->audit('save', 'news', $id);

            if ($this->isAjax($request)) {
                return $this->json([
                    'ok' => true,
                    'message' => 'Новину збережено.',
                    'id' => $id,
                    'published_at' => $publishedAt,
                    'edit_url' => url('/admin/news/edit?id=' . $id),
                    'view_url' => $request->input('status') === 'published' ? url('/news/' . $slug) : null,
                ]);
            }

            redirect('/admin/news');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
        }
    }

    public function newsBulk(Request $request): Response
    {
        $this->guard('news.manage');
        Csrf::verify();
        $ids = $this->bulkIds($request);
        $action = (string) $request->input('bulk_action', '');
        if ($ids && in_array($action, ['publish', 'draft'], true)) {
            $status = $action === 'publish' ? 'published' : 'draft';
            $this->bulkUpdateStatus('news', $ids, $status);
            if ($status === 'published') {
                $this->bulkFillPublishedAt('news', $ids);
            }
            $this->audit('bulk_' . $action, 'news', null, 'ids: ' . implode(',', $ids));
        } elseif ($ids && $action === 'delete') {
            $this->bulkDelete('news', $ids);
            $this->audit('bulk_delete', 'news', null, 'ids: ' . implode(',', $ids));
        }

        if ($this->isAjax($request)) {
            return $this->json($this->adminListPayload('news', $request, 'Групову дію виконано.'));
        }

        redirect('/admin/news');
    }
}
