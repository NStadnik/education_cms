<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\Files;
use Throwable;

final class DocumentsController extends \App\Controllers\AdminBaseController
{
    public function documents(Request $request): Response
    {
        $this->guard();
        $query = trim((string) $request->input('q', ''));
        $pagination = $this->pagination($request);
        [$where, $params] = $this->searchWhere($query, ['title', 'category', 'description', 'status', 'responsible']);
        $items = $this->db()->fetchAll(
            'select * from documents ' . $where . ' order by id desc limit ' . $pagination['limit'] . ' offset ' . $pagination['offset'],
            $params
        );
        $total = (int) ($this->db()->fetch('select count(*) as c from documents ' . $where, $params)['c'] ?? 0);
        $sections = $this->db()->fetchAll('select id, title from public_info_sections order by sort_order asc');

        if ($this->isAjaxRequest()) {
            return $this->listJson('admin/documents/rows', ['items' => $items], $pagination, $total);
        }

        $stats = [
            'total' => $this->count('documents'),
            'published' => (int) ($this->db()->fetch("select count(*) as c from documents where status = 'published'")['c'] ?? 0),
            'linked' => (int) ($this->db()->fetch('select count(*) as c from documents where public_info_section_id is not null')['c'] ?? 0),
        ];
        return $this->admin('admin/documents/index', [
            'title' => 'Документи',
            'items' => $items,
            'sections' => $sections,
            'total' => $total,
            'limit' => $pagination['limit'],
            'stats' => $stats,
        ]);
    }

    public function documentForm(Request $request): Response
    {
        $this->guard();
        $id = (int) $request->input('id', 0);
        $item = $id ? $this->db()->fetch('select * from documents where id = ?', [$id]) : null;
        return $this->admin('admin/documents/form', [
            'title' => 'Документ',
            'item' => $item,
            'sections' => $this->db()->fetchAll('select id, title from public_info_sections order by sort_order asc'),
        ]);
    }

    public function documentSave(Request $request): Response
    {
        $this->guard('documents.manage');
        Csrf::verify();
        try {
            $id = (int) $request->input('id', 0);
            $existing = $id ? $this->db()->fetch('select * from documents where id = ?', [$id]) : null;
            $filePath = Files::upload($request->files['file'] ?? []);
            if (!$filePath && $existing) {
                $filePath = $existing['file_path'] ?? null;
            }
            $now = date('c');
            $publishedAt = $request->input('published_at') ?: ($request->input('status') === 'published' ? $now : null);
            $data = [
                $request->input('public_info_section_id') ?: null,
                $request->input('title'),
                $request->input('category'),
                $filePath,
                $request->input('description'),
                $request->input('status', 'published'),
                $request->input('responsible'),
                $request->input('approved_at'),
                $publishedAt,
                $now,
            ];

            if ($id && $existing) {
                $this->db()->execute(
                    'update documents set public_info_section_id=?, title=?, category=?, file_path=?, description=?, status=?, responsible=?, approved_at=?, published_at=?, updated_at=? where id=?',
                    [...$data, $id]
                );
            } else {
                $this->db()->execute(
                    'insert into documents (public_info_section_id, title, category, file_path, description, status, responsible, approved_at, published_at, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [...$data, $now]
                );
                $id = (int) $this->db()->lastInsertId();
            }
            $this->audit('save', 'document', $id);

            if ($this->isAjax($request)) {
                return $this->json([
                    'ok' => true,
                    'message' => 'Документ збережено.',
                    'id' => $id,
                    'edit_url' => url('/admin/documents/edit?id=' . $id),
                    'file_url' => $filePath ? url('/uploads/' . $filePath) : null,
                ]);
            }

            redirect('/admin/documents');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
        }
    }

    public function documentsBulk(Request $request): Response
    {
        $this->guard('documents.manage');
        Csrf::verify();
        $ids = $this->bulkIds($request);
        $action = (string) $request->input('bulk_action', '');
        if ($ids && in_array($action, ['publish', 'draft'], true)) {
            $status = $action === 'publish' ? 'published' : 'draft';
            $this->bulkUpdateStatus('documents', $ids, $status);
            if ($status === 'published') {
                $this->bulkFillPublishedAt('documents', $ids);
            }
            $this->audit('bulk_' . $action, 'documents', null, 'ids: ' . implode(',', $ids));
        } elseif ($ids && $action === 'delete') {
            $this->bulkDelete('documents', $ids);
            $this->audit('bulk_delete', 'documents', null, 'ids: ' . implode(',', $ids));
        }

        if ($this->isAjax($request)) {
            return $this->json($this->adminListPayload('documents', $request, 'Групову дію виконано.'));
        }

        redirect('/admin/documents');
    }
}
