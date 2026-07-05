<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\Files;
use Throwable;

final class PublicInfoController extends \App\Controllers\AdminBaseController
{
    public function publicInfo(Request $request): Response
    {
        $this->guard();
        $query = trim((string) $request->input('q', ''));
        $pagination = $this->pagination($request);
        [$where, $params] = $this->publicInfoSearchWhere($query);
        $sections = $this->db()->fetchAll(
            'select s.*, count(d.id) as documents_count, sum(case when d.status = \'published\' then 1 else 0 end) as published_documents_count, max(d.updated_at) as last_document_at
             from public_info_sections s
             left join documents d on d.public_info_section_id = s.id
             ' . $where . '
             group by s.id, s.title, s.slug, s.description, s.is_required, s.sort_order
             order by s.sort_order asc
             limit ' . $pagination['limit'] . ' offset ' . $pagination['offset'],
            $params
        );
        $total = (int) ($this->db()->fetch('select count(*) as c from public_info_sections s ' . $where, $params)['c'] ?? 0);
        $documents = $this->publicInfoDocuments($sections);

        if ($this->isAjaxRequest()) {
            return $this->listJson('admin/public-info/rows', ['sections' => $sections, 'documents' => $documents], $pagination, $total);
        }

        $stats = [
            'total' => $this->count('public_info_sections'),
            'filled' => (int) ($this->db()->fetch("select count(distinct public_info_section_id) as c from documents where status = 'published' and public_info_section_id is not null")['c'] ?? 0),
            'required' => (int) ($this->db()->fetch('select count(*) as c from public_info_sections where is_required = 1')['c'] ?? 0),
        ];
        return $this->admin('admin/public-info/index', [
            'title' => 'Публічна інформація',
            'sections' => $sections,
            'documents' => $documents,
            'total' => $total,
            'limit' => $pagination['limit'],
            'stats' => $stats,
        ]);
    }

    public function publicInfoSectionForm(Request $request): Response
    {
        $this->guard();
        $id = (int) $request->input('id', 0);
        $item = $id ? $this->db()->fetch('select * from public_info_sections where id = ?', [$id]) : null;
        return $this->admin('admin/public-info/section-form', ['title' => 'Розділ публічної інформації', 'item' => $item]);
    }

    public function publicInfoDocumentForm(Request $request): Response
    {
        $this->guard();
        $id = (int) $request->input('id', 0);
        $sectionId = (int) $request->input('section_id', 0);
        $item = $id ? $this->db()->fetch('select * from documents where id = ?', [$id]) : null;
        if (!$item && $sectionId) {
            $item = ['public_info_section_id' => $sectionId, 'status' => 'published'];
        }

        return $this->admin('admin/public-info/document-form', [
            'title' => 'Документ публічної інформації',
            'item' => $item,
            'sections' => $this->db()->fetchAll('select id, title from public_info_sections order by sort_order asc'),
        ]);
    }

    public function publicInfoSave(Request $request): Response
    {
        $this->guard('public_info.manage');
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
                $request->input('public_info_section_id'),
                $request->input('title'),
                'Публічна інформація',
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
            $this->audit('save', 'public_info_document', $id);

            if ($this->isAjax($request)) {
                return $this->json([
                    'ok' => true,
                    'message' => 'Документ публічної інформації збережено.',
                    'id' => $id,
                    'edit_url' => url('/admin/public-info/documents/edit?id=' . $id),
                    'file_url' => $filePath ? url('/uploads/' . $filePath) : null,
                ]);
            }

            redirect('/admin/public-info');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
        }
    }

    public function publicInfoSectionSave(Request $request): Response
    {
        $this->guard('public_info.manage');
        Csrf::verify();

        try {
            $id = (int) $request->input('id', 0);
            $title = trim((string) $request->input('title'));
            if ($title === '') {
                return $this->json(['ok' => false, 'message' => 'Вкажіть назву розділу.'], 422);
            }

            $slug = $this->slug((string) ($request->input('slug') ?: $title));
            $description = (string) $request->input('description', '');
            $isRequired = $request->input('is_required') ? 1 : 0;
            $sortOrder = (int) $request->input('sort_order', 0);

            if ($id) {
                $this->db()->execute(
                    'update public_info_sections set title=?, slug=?, description=?, is_required=?, sort_order=? where id=?',
                    [$title, $slug, $description, $isRequired, $sortOrder, $id]
                );
            } else {
                $this->db()->execute(
                    'insert into public_info_sections (title, slug, description, is_required, sort_order) values (?, ?, ?, ?, ?)',
                    [$title, $slug, $description, $isRequired, $sortOrder]
                );
                $id = (int) $this->db()->lastInsertId();
            }

            $this->audit('save', 'public_info_section', $id);
            $payload = [
                'ok' => true,
                'message' => 'Розділ збережено.',
                'id' => $id,
                'edit_url' => url('/admin/public-info/sections/edit?id=' . $id),
                'section' => [
                    'id' => $id,
                    'title' => $title,
                    'slug' => $slug,
                    'description' => $description,
                    'is_required' => $isRequired,
                    'sort_order' => $sortOrder,
                ],
                'created' => !$request->input('id'),
            ];

            if ($this->isAjax($request)) {
                return $this->json($payload);
            }

            redirect('/admin/public-info');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
        }
    }

    public function publicInfoSectionDelete(Request $request): Response
    {
        $this->guard('public_info.manage');
        Csrf::verify();

        try {
            $id = (int) $request->input('id', 0);
            $documents = (int) ($this->db()->fetch('select count(*) as c from documents where public_info_section_id = ?', [$id])['c'] ?? 0);
            if (!$id) {
                return $this->json(['ok' => false, 'message' => 'Розділ не знайдено.'], 404);
            }
            if ($documents > 0) {
                return $this->json(['ok' => false, 'message' => 'Розділ має документи, тому його не можна видалити.'], 422);
            }

            $this->db()->execute('delete from public_info_sections where id = ?', [$id]);
            $this->audit('delete', 'public_info_section', $id);
            return $this->json(['ok' => true, 'message' => 'Розділ видалено.', 'id' => $id]);
        } catch (Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function publicInfoBulk(Request $request): Response
    {
        $this->guard('public_info.manage');
        Csrf::verify();

        $action = (string) $request->input('bulk_action', '');
        $sectionIds = $this->bulkIdsFrom($request, 'section_ids');
        $documentIds = $this->bulkIdsFrom($request, 'document_ids');
        $message = 'Групову дію виконано.';
        $handled = false;

        if ($documentIds && in_array($action, ['publish_documents', 'draft_documents'], true)) {
            $status = $action === 'publish_documents' ? 'published' : 'draft';
            $this->bulkUpdateStatus('documents', $documentIds, $status);
            if ($status === 'published') {
                $this->bulkFillPublishedAt('documents', $documentIds);
            }
            $this->audit('bulk_' . $status, 'public_info_documents', null, 'ids: ' . implode(',', $documentIds));
            $handled = true;
        } elseif ($documentIds && $action === 'delete_documents') {
            $this->bulkDelete('documents', $documentIds);
            $this->audit('bulk_delete', 'public_info_documents', null, 'ids: ' . implode(',', $documentIds));
            $handled = true;
        } elseif ($sectionIds && in_array($action, ['require_sections', 'optional_sections'], true)) {
            $this->bulkUpdatePublicInfoRequired($sectionIds, $action === 'require_sections' ? 1 : 0);
            $this->audit('bulk_' . $action, 'public_info_sections', null, 'ids: ' . implode(',', $sectionIds));
            $handled = true;
        } elseif ($sectionIds && $action === 'delete_sections') {
            $deleted = $this->bulkDeletePublicInfoSections($sectionIds);
            $message = $deleted > 0 ? 'Розділи видалено: ' . $deleted . '.' : 'Вибрані розділи мають документи або вже видалені.';
            $this->audit('bulk_delete', 'public_info_sections', null, 'ids: ' . implode(',', $sectionIds));
            $handled = true;
        }

        if ($this->isAjax($request)) {
            if (!$handled) {
                return $this->json(['ok' => false, 'message' => 'Оберіть записи відповідного типу для цієї групової дії.'], 422);
            }
            return $this->json($this->adminListPayload('public-info', $request, $message));
        }

        redirect('/admin/public-info');
    }
}
