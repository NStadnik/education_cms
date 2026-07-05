<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\Files;
use Throwable;

final class PagesController extends \App\Controllers\AdminBaseController
{
    public function pages(Request $request): Response
    {
        $this->guard();
        $query = trim((string) $request->input('q', ''));
        $pagination = $this->pagination($request);
        [$where, $params] = $this->searchWhere($query, ['title', 'slug', 'excerpt', 'status']);
        $items = $this->db()->fetchAll(
            'select * from pages ' . $where . ' order by sort_order asc, id desc limit ' . $pagination['limit'] . ' offset ' . $pagination['offset'],
            $params
        );
        $total = (int) ($this->db()->fetch('select count(*) as c from pages ' . $where, $params)['c'] ?? 0);

        if ($this->isAjaxRequest()) {
            return $this->listJson('admin/pages/rows', ['items' => $items], $pagination, $total);
        }

        $stats = $this->statusStats('pages');
        return $this->admin('admin/pages/index', [
            'title' => 'Сторінки',
            'items' => $items,
            'total' => $total,
            'limit' => $pagination['limit'],
            'stats' => $stats,
        ]);
    }

    public function pageForm(Request $request): Response
    {
        $this->guard();
        $id = (int) $request->input('id', 0);
        $item = $id ? $this->db()->fetch('select * from pages where id = ?', [$id]) : null;
        return $this->admin('admin/pages/form', ['title' => 'Сторінка', 'item' => $item, 'templates' => $this->pageTemplates()]);
    }

    public function pageSave(Request $request): Response
    {
        $this->guard('pages.manage');
        Csrf::verify();
        try {
            $now = date('c');
            $id = (int) $request->input('id', 0);
            $blocks = $this->blocksFromText((string) $request->input('blocks_text'));
            $slug = $this->slug((string) $request->input('slug', $request->input('title')));
            $template = (string) $request->input('template', 'default');
            if (!array_key_exists($template, $this->pageTemplates())) {
                $template = 'default';
            }
            $data = [
                $request->input('title'),
                $slug,
                $request->input('excerpt'),
                $template,
                json_encode($blocks, JSON_UNESCAPED_UNICODE),
                $request->input('status', 'draft'),
                (int) $request->input('sort_order', 0),
                $now,
            ];

            if ($id) {
                $this->db()->execute('update pages set title=?, slug=?, excerpt=?, template=?, blocks_json=?, status=?, sort_order=?, updated_at=? where id=?', [...$data, $id]);
            } else {
                $this->db()->execute('insert into pages (title, slug, excerpt, template, blocks_json, status, sort_order, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?)', [...$data, $now]);
                $id = (int) $this->db()->lastInsertId();
            }
            $this->audit('save', 'page', $id);

            if ($this->isAjax($request)) {
                return $this->json([
                    'ok' => true,
                    'message' => 'Сторінку збережено.',
                    'id' => $id,
                    'edit_url' => url('/admin/pages/edit?id=' . $id),
                    'view_url' => $request->input('status') === 'published' ? url($slug === 'home' ? '/' : '/page/' . $slug) : null,
                ]);
            }

            redirect('/admin/pages');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
        }
    }

    public function pagesBulk(Request $request): Response
    {
        $this->guard('pages.manage');
        Csrf::verify();
        $ids = $this->bulkIds($request);
        $action = (string) $request->input('bulk_action', '');
        if ($ids && in_array($action, ['publish', 'draft'], true)) {
            $status = $action === 'publish' ? 'published' : 'draft';
            $this->bulkUpdateStatus('pages', $ids, $status);
            $this->audit('bulk_' . $action, 'pages', null, 'ids: ' . implode(',', $ids));
        } elseif ($ids && $action === 'delete') {
            $this->bulkDelete('pages', $ids);
            $this->audit('bulk_delete', 'pages', null, 'ids: ' . implode(',', $ids));
        }

        if ($this->isAjax($request)) {
            return $this->json($this->adminListPayload('pages', $request, 'Групову дію виконано.'));
        }

        redirect('/admin/pages');
    }
}
