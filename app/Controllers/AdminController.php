<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\Files;
use Throwable;

final class AdminController extends BaseController
{
    private const LIST_LIMIT = 20;

    public function login(): Response
    {
        return $this->render('admin/login', ['title' => 'Вхід'], 'layouts/minimal');
    }

    public function authenticate(Request $request): Response
    {
        Csrf::verify();
        if (Container::get('auth')->attempt((string) $request->input('email'), (string) $request->input('password'))) {
            redirect('/admin');
        }

        return $this->render('admin/login', ['title' => 'Вхід', 'error' => 'Невірний email або пароль.'], 'layouts/minimal');
    }

    public function logout(): Response
    {
        Csrf::verify();
        Container::get('auth')->logout();
        redirect('/');
    }

    public function dashboard(): Response
    {
        $this->guard();
        $stats = [
            'pages' => $this->count('pages'),
            'news' => $this->count('news'),
            'documents' => $this->count('documents'),
            'media' => count(Files::all()),
            'publicFilled' => $this->db()->fetch("select count(distinct public_info_section_id) as c from documents where status = 'published' and public_info_section_id is not null")['c'] ?? 0,
            'publicTotal' => $this->count('public_info_sections'),
        ];
        return $this->admin('admin/dashboard', ['title' => 'Панель керування', 'stats' => $stats]);
    }

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

    public function media(Request $request): Response
    {
        $this->guard('media.manage');
        $query = trim((string) $request->input('q', ''));
        $pagination = $this->pagination($request);
        $list = $this->mediaListPayload($query, $pagination);

        if ($this->isAjaxRequest()) {
            return $this->json($list);
        }

        return $this->admin('admin/media/index', [
            'title' => 'Медіафайли',
            'items' => $list['items'],
            'total' => $list['total'],
            'limit' => $pagination['limit'],
            'stats' => $list['stats'],
            'query' => $query,
            'uploadLimitBytes' => Files::uploadLimitBytes(),
            'uploadLimitLabel' => Files::uploadLimitLabel(),
        ]);
    }

    public function mediaUpload(Request $request): Response
    {
        $this->guard('media.manage');
        Csrf::verify();

        try {
            $filePath = Files::upload($request->files['file'] ?? []);
            if (!$filePath) {
                throw new \RuntimeException('Оберіть файл для завантаження.');
            }

            $this->audit('upload', 'media', null, $filePath);
            if ($this->isAjax($request)) {
                return $this->json(array_replace($this->mediaListPayload(trim((string) $request->input('q', '')), [
                    'limit' => self::LIST_LIMIT,
                    'offset' => 0,
                ]), [
                    'message' => 'Файл завантажено.',
                    'uploaded_path' => $filePath,
                ]));
            }

            redirect('/admin/media');
        } catch (Throwable $e) {
            if ($this->isAjax($request)) {
                return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            $allItems = Files::all($this->mediaReferences());
            return $this->admin('admin/media/index', [
                'title' => 'Медіафайли',
                'items' => array_slice($allItems, 0, self::LIST_LIMIT),
                'total' => count($allItems),
                'limit' => self::LIST_LIMIT,
                'stats' => $this->mediaStats($allItems),
                'query' => '',
                'uploadLimitBytes' => Files::uploadLimitBytes(),
                'uploadLimitLabel' => Files::uploadLimitLabel(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function mediaDelete(Request $request): Response
    {
        $this->guard('media.manage');
        Csrf::verify();

        $path = Files::normalize((string) $request->input('path', ''));
        $references = $this->mediaReferences();
        if ($path === '' || isset($references[$path])) {
            if ($this->isAjax($request)) {
                return $this->json(['ok' => false, 'message' => 'Файл використовується або не знайдений.'], 422);
            }

            return new Response('Cannot delete file', 422);
        }

        Files::delete($path);
        $this->audit('delete', 'media', null, $path);
        if ($this->isAjax($request)) {
            return $this->json(array_replace($this->mediaListPayload(trim((string) $request->input('q', '')), [
                'limit' => self::LIST_LIMIT,
                'offset' => 0,
            ]), [
                'message' => 'Файл видалено.',
                'deleted_path' => $path,
            ]));
        }

        redirect('/admin/media');
    }

    public function mediaBulk(Request $request): Response
    {
        $this->guard('media.manage');
        Csrf::verify();

        $paths = $request->input('paths', []);
        $paths = is_array($paths) ? $paths : [];
        $paths = array_values(array_unique(array_filter(array_map(static fn ($path): string => Files::normalize((string) $path), $paths))));
        $action = (string) $request->input('bulk_action', '');
        $deleted = 0;

        if ($paths && $action === 'delete') {
            $references = $this->mediaReferences();
            foreach ($paths as $path) {
                if ($path === '' || isset($references[$path])) {
                    continue;
                }
                try {
                    Files::delete($path);
                    $deleted++;
                } catch (Throwable) {
                    continue;
                }
            }
            $this->audit('bulk_delete', 'media', null, 'paths: ' . implode(',', $paths));
        }

        if ($this->isAjax($request)) {
            $message = $deleted > 0 ? 'Файли видалено: ' . $deleted . '.' : 'Немає файлів для видалення.';
            return $this->json($this->adminListPayload('media', $request, $message));
        }

        redirect('/admin/media');
    }

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

    public function users(Request $request): Response
    {
        $this->guard('users.manage');
        $query = trim((string) $request->input('q', ''));
        $pagination = $this->pagination($request);
        [$where, $params] = $this->searchWhere($query, ['name', 'email', 'role']);
        $items = $this->db()->fetchAll(
            'select * from users ' . $where . ' order by id desc limit ' . $pagination['limit'] . ' offset ' . $pagination['offset'],
            $params
        );
        $total = (int) ($this->db()->fetch('select count(*) as c from users ' . $where, $params)['c'] ?? 0);

        if ($this->isAjaxRequest()) {
            return $this->listJson('admin/users/rows', ['items' => $items], $pagination, $total);
        }

        return $this->admin('admin/users/index', [
            'title' => 'Користувачі',
            'items' => $items,
            'total' => $total,
            'limit' => $pagination['limit'],
        ]);
    }

    public function userForm(Request $request): Response
    {
        $this->guard('users.manage');
        $id = (int) $request->input('id', 0);
        $item = $id ? $this->db()->fetch('select * from users where id = ?', [$id]) : null;
        return $this->admin('admin/users/form', ['title' => 'Користувач', 'item' => $item]);
    }

    public function userSave(Request $request): Response
    {
        $this->guard('users.manage');
        Csrf::verify();
        try {
            $id = (int) $request->input('id', 0);
            $password = (string) $request->input('password', '');
            if ($id) {
                $this->db()->execute(
                    'update users set name=?, email=?, role=?, is_active=? where id=?',
                    [$request->input('name'), $request->input('email'), $request->input('role'), $request->input('is_active') ? 1 : 0, $id]
                );
                if ($password !== '') {
                    $this->db()->execute('update users set password_hash=? where id=?', [password_hash($password, PASSWORD_DEFAULT), $id]);
                }
            } else {
                $this->db()->execute(
                    'insert into users (name, email, password_hash, role, is_active, created_at) values (?, ?, ?, ?, 1, ?)',
                    [$request->input('name'), $request->input('email'), password_hash($password, PASSWORD_DEFAULT), $request->input('role'), date('c')]
                );
                $id = (int) $this->db()->lastInsertId();
            }

            if ($this->isAjax($request)) {
                return $this->json(['ok' => true, 'message' => 'Користувача збережено.', 'id' => $id, 'edit_url' => url('/admin/users/edit?id=' . $id)]);
            }

            redirect('/admin/users');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
        }
    }

    public function usersBulk(Request $request): Response
    {
        $this->guard('users.manage');
        Csrf::verify();
        $ids = array_values(array_filter($this->bulkIds($request), static fn (int $id): bool => $id !== (int) ($_SESSION['user_id'] ?? 0)));
        $action = (string) $request->input('bulk_action', '');
        if ($ids && in_array($action, ['activate', 'deactivate'], true)) {
            $this->bulkUpdateUsers($ids, $action === 'activate' ? 1 : 0);
            $this->audit('bulk_' . $action, 'users', null, 'ids: ' . implode(',', $ids));
        } elseif ($ids && $action === 'delete') {
            $this->bulkDelete('users', $ids);
            $this->audit('bulk_delete', 'users', null, 'ids: ' . implode(',', $ids));
        }

        if ($this->isAjax($request)) {
            return $this->json($this->adminListPayload('users', $request, 'Групову дію виконано.'));
        }

        redirect('/admin/users');
    }

    public function settings(): Response
    {
        $this->guard('settings.manage');
        return $this->admin('admin/settings', [
            'title' => 'Налаштування',
            'settings' => $this->siteSettings(),
            'globalFields' => $this->globalFields(),
        ]);
    }

    public function settingsSave(Request $request): Response
    {
        $this->guard('settings.manage');
        Csrf::verify();
        try {
            $this->saveSetting('institution_name', (string) $request->input('institution_name'));
            $globalFields = json_encode($this->normalizeGlobalFields($request), JSON_UNESCAPED_UNICODE);
            $this->saveSetting('global_fields', $globalFields === false ? '[]' : $globalFields);

            if ($this->isAjax($request)) {
                return $this->json(['ok' => true, 'message' => 'Налаштування збережено.']);
            }

            redirect('/admin/settings');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
        }
    }

    public function templates(): Response
    {
        $this->guard('settings.manage');
        return $this->admin('admin/templates/index', [
            'title' => 'Шаблони сайту',
            'settings' => $this->siteSettings(),
            'siteTemplates' => $this->siteTemplates(),
        ]);
    }

    public function templatesSave(Request $request): Response
    {
        $this->guard('settings.manage');
        Csrf::verify();
        try {
            $siteTemplate = (string) $request->input('site_template', 'official');
            if (!array_key_exists($siteTemplate, $this->siteTemplates())) {
                $siteTemplate = 'official';
            }
            $this->saveSetting('site_template', $siteTemplate);

            if ($this->isAjax($request)) {
                return $this->json(['ok' => true, 'message' => 'Шаблон сайту збережено.']);
            }

            redirect('/admin/templates');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
        }
    }

    public function import(): Response
    {
        $this->guard('settings.manage');
        return $this->admin('admin/import', [
            'title' => 'Імпорт',
            'importOptions' => $this->importOptions(),
        ]);
    }

    public function importRun(Request $request): Response
    {
        $this->guard('settings.manage');
        Csrf::verify();
        $transactionStarted = false;
        try {
            $type = (string) $request->input('type', 'news');
            if ((string) $request->input('source', 'file') === 'database') {
                $type = 'wordpress';
            }
            if (!array_key_exists($type, $this->importOptions())) {
                throw new \RuntimeException('Невідомий тип імпорту.');
            }

            $rows = $this->readImportRows($request, false);
            if (!$rows) {
                throw new \RuntimeException('Файл або текст імпорту не містить записів.');
            }

            $this->db()->pdo()->beginTransaction();
            $transactionStarted = true;
            $created = $this->importRows($type, $rows);
            $this->db()->pdo()->commit();
            $transactionStarted = false;
            $this->audit('import', $type, null, 'created: ' . $created);

            $result = [
                'ok' => true,
                'message' => 'Імпорт завершено.',
                'created' => $created,
                'total' => count($rows),
            ];

            if ($this->isAjax($request)) {
                return $this->json($result);
            }

            return $this->admin('admin/import', [
                'title' => 'Імпорт',
                'importOptions' => $this->importOptions(),
                'result' => $result,
            ]);
        } catch (Throwable $e) {
            if ($transactionStarted && $this->db()->pdo()->inTransaction()) {
                $this->db()->pdo()->rollBack();
            }

            if ($this->isAjax($request)) {
                return $this->json(['ok' => false, 'message' => $e->getMessage()], 500);
            }

            return $this->admin('admin/import', [
                'title' => 'Імпорт',
                'importOptions' => $this->importOptions(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function importPreview(Request $request): Response
    {
        $this->guard('settings.manage');
        Csrf::verify();
        try {
            $type = (string) $request->input('type', 'news');
            if ((string) $request->input('source', 'file') === 'database') {
                $type = 'wordpress';
            }
            if (!array_key_exists($type, $this->importOptions())) {
                throw new \RuntimeException('Невідомий тип імпорту.');
            }

            $rows = $this->readImportRows($request, true);
            if (!$rows) {
                throw new \RuntimeException('Джерело імпорту не містить записів для попереднього перегляду.');
            }

            return $this->json([
                'ok' => true,
                'message' => 'Попередній перегляд готовий.',
                'total' => count($rows),
                'summary' => $this->importPreviewSummary($type, $rows),
                'rows' => array_slice($this->importPreviewRows($type, $rows), 0, 10),
            ]);
        } catch (Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function guard(?string $permission = null): void
    {
        Container::get('auth')->require();
        if ($permission && !Container::get('auth')->can($permission)) {
            http_response_code(403);
            exit('Forbidden');
        }
    }

    private function admin(string $template, array $data): Response
    {
        return $this->render($template, array_replace($data, ['user' => Container::get('auth')->user()]), 'layouts/admin');
    }

    private function count(string $table): int
    {
        return (int) ($this->db()->fetch("select count(*) as c from {$table}")['c'] ?? 0);
    }

    private function pagination(Request $request): array
    {
        $limit = (int) $request->input('limit', self::LIST_LIMIT);
        if ($limit <= 0 || $limit > 100) {
            $limit = self::LIST_LIMIT;
        }

        $offset = max(0, (int) $request->input('offset', 0));
        return ['limit' => $limit, 'offset' => $offset];
    }

    private function listJson(string $template, array $data, array $pagination, int $total): Response
    {
        $loaded = $pagination['offset'] + count($data['items'] ?? $data['sections'] ?? []);
        return $this->json([
            'ok' => true,
            'html' => $this->view()->partial($template, $data),
            'total' => $total,
            'next_offset' => $loaded,
            'has_more' => $loaded < $total,
        ]);
    }

    private function adminListPayload(string $resource, Request $request, string $message = ''): array
    {
        $query = trim((string) $request->input('q', ''));
        $pagination = [
            'limit' => (int) ($request->input('limit', self::LIST_LIMIT) ?: self::LIST_LIMIT),
            'offset' => 0,
        ];
        if ($pagination['limit'] <= 0 || $pagination['limit'] > 100) {
            $pagination['limit'] = self::LIST_LIMIT;
        }

        if ($resource === 'media') {
            return array_replace($this->mediaListPayload($query, $pagination), ['message' => $message]);
        }

        if ($resource === 'public-info') {
            [$where, $params] = $this->publicInfoSearchWhere($query);
            $sections = $this->db()->fetchAll(
                'select s.*, count(d.id) as documents_count, sum(case when d.status = \'published\' then 1 else 0 end) as published_documents_count, max(d.updated_at) as last_document_at
                 from public_info_sections s
                 left join documents d on d.public_info_section_id = s.id
                 ' . $where . '
                 group by s.id, s.title, s.slug, s.description, s.is_required, s.sort_order
                 order by s.sort_order asc
                 limit ' . $pagination['limit'] . ' offset 0',
                $params
            );
            $total = (int) ($this->db()->fetch('select count(*) as c from public_info_sections s ' . $where, $params)['c'] ?? 0);
            $documents = $this->publicInfoDocuments($sections);
            $loaded = count($sections);

            return [
                'ok' => true,
                'html' => $this->view()->partial('admin/public-info/rows', ['sections' => $sections, 'documents' => $documents]),
                'total' => $total,
                'next_offset' => $loaded,
                'has_more' => $loaded < $total,
                'message' => $message,
                'stats' => [
                    'total' => $this->count('public_info_sections'),
                    'filled' => (int) ($this->db()->fetch("select count(distinct public_info_section_id) as c from documents where status = 'published' and public_info_section_id is not null")['c'] ?? 0),
                    'required' => (int) ($this->db()->fetch('select count(*) as c from public_info_sections where is_required = 1')['c'] ?? 0),
                ],
            ];
        }

        $map = [
            'pages' => ['table' => 'pages', 'template' => 'admin/pages/rows', 'columns' => ['title', 'slug', 'excerpt', 'status'], 'order' => 'sort_order asc, id desc'],
            'news' => ['table' => 'news', 'template' => 'admin/news/rows', 'columns' => ['title', 'body', 'status', 'published_at'], 'order' => 'id desc'],
            'documents' => ['table' => 'documents', 'template' => 'admin/documents/rows', 'columns' => ['title', 'category', 'description', 'status', 'responsible'], 'order' => 'id desc'],
            'users' => ['table' => 'users', 'template' => 'admin/users/rows', 'columns' => ['name', 'email', 'role'], 'order' => 'id desc'],
        ];
        if (!isset($map[$resource])) {
            return ['ok' => true, 'message' => $message];
        }

        $config = $map[$resource];
        [$where, $params] = $this->searchWhere($query, $config['columns']);
        $items = $this->db()->fetchAll(
            'select * from ' . $config['table'] . ' ' . $where . ' order by ' . $config['order'] . ' limit ' . $pagination['limit'] . ' offset 0',
            $params
        );
        $total = (int) ($this->db()->fetch('select count(*) as c from ' . $config['table'] . ' ' . $where, $params)['c'] ?? 0);
        $loaded = count($items);

        return [
            'ok' => true,
            'html' => $this->view()->partial($config['template'], ['items' => $items]),
            'total' => $total,
            'next_offset' => $loaded,
            'has_more' => $loaded < $total,
            'message' => $message,
            'stats' => $this->adminListStats($resource),
        ];
    }

    private function adminListStats(string $resource): array
    {
        if (in_array($resource, ['pages', 'news'], true)) {
            return $this->statusStats($resource);
        }
        if ($resource === 'documents') {
            return [
                'total' => $this->count('documents'),
                'published' => (int) ($this->db()->fetch("select count(*) as c from documents where status = 'published'")['c'] ?? 0),
                'linked' => (int) ($this->db()->fetch('select count(*) as c from documents where public_info_section_id is not null')['c'] ?? 0),
            ];
        }
        if ($resource === 'users') {
            return ['total' => $this->count('users')];
        }

        return [];
    }

    private function searchWhere(string $query, array $columns): array
    {
        if ($query === '') {
            return ['', []];
        }

        $parts = [];
        $params = [];
        foreach ($columns as $column) {
            $parts[] = $column . ' like ?';
            $params[] = '%' . $query . '%';
        }

        return ['where ' . implode(' or ', $parts), $params];
    }

    private function publicInfoSearchWhere(string $query): array
    {
        if ($query === '') {
            return ['', []];
        }

        $like = '%' . $query . '%';
        return [
            'where s.title like ? or s.slug like ? or s.description like ? or exists (
                select 1 from documents sd
                where sd.public_info_section_id = s.id
                and (sd.title like ? or sd.description like ? or sd.responsible like ?)
            )',
            [$like, $like, $like, $like, $like, $like],
        ];
    }

    private function publicInfoDocuments(array $sections): array
    {
        $ids = [];
        foreach ($sections as $section) {
            $id = (int) ($section['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return $this->db()->fetchAll(
            'select d.*, s.title as section_title
             from documents d
             left join public_info_sections s on s.id = d.public_info_section_id
             where d.public_info_section_id in (' . $placeholders . ')
             order by s.sort_order asc, d.updated_at desc',
            $ids
        );
    }

    private function statusStats(string $table): array
    {
        $total = $this->count($table);
        $published = (int) ($this->db()->fetch("select count(*) as c from {$table} where status = 'published'")['c'] ?? 0);
        return ['total' => $total, 'published' => $published, 'drafts' => $total - $published];
    }

    private function pageTemplates(): array
    {
        return [
            'default' => 'Стандартний',
            'wide' => 'Широкий контент',
            'document' => 'Документ / стаття',
        ];
    }

    private function siteTemplates(): array
    {
        return [
            'official' => [
                'name' => 'Офіційний',
                'description' => 'Стриманий державний стиль із темною навігацією.',
                'accent' => '#1f6feb',
            ],
            'minimal' => [
                'name' => 'Світлий',
                'description' => 'Легкий білий інтерфейс із чітким контентним фокусом.',
                'accent' => '#0b7a55',
            ],
            'contrast' => [
                'name' => 'Контрастний',
                'description' => 'Виразний шаблон для кращої помітності навігації.',
                'accent' => '#9a6700',
            ],
        ];
    }

    private function bulkIds(Request $request): array
    {
        return $this->bulkIdsFrom($request, 'ids');
    }

    private function bulkIdsFrom(Request $request, string $name): array
    {
        $ids = $request->input($name, []);
        if (!is_array($ids)) {
            return [];
        }

        $ids = array_map(static fn ($id): int => (int) $id, $ids);
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        return array_slice($ids, 0, 200);
    }

    private function bulkUpdatePublicInfoRequired(array $ids, int $isRequired): void
    {
        if (!$ids) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->db()->execute(
            "update public_info_sections set is_required = ? where id in ({$placeholders})",
            [$isRequired, ...$ids]
        );
    }

    private function bulkDeletePublicInfoSections(array $ids): int
    {
        if (!$ids) {
            return 0;
        }

        $deleted = 0;
        foreach ($ids as $id) {
            $documents = (int) ($this->db()->fetch('select count(*) as c from documents where public_info_section_id = ?', [$id])['c'] ?? 0);
            if ($documents > 0) {
                continue;
            }
            $this->db()->execute('delete from public_info_sections where id = ?', [$id]);
            $deleted++;
        }

        return $deleted;
    }

    private function bulkUpdateStatus(string $table, array $ids, string $status): void
    {
        $allowedTables = ['pages', 'news', 'documents'];
        if (!in_array($table, $allowedTables, true) || !$ids) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->db()->execute(
            "update {$table} set status = ?, updated_at = ? where id in ({$placeholders})",
            [$status, date('c'), ...$ids]
        );
    }

    private function bulkFillPublishedAt(string $table, array $ids): void
    {
        $allowedTables = ['news', 'documents'];
        if (!in_array($table, $allowedTables, true) || !$ids) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->db()->execute(
            "update {$table} set published_at = ? where id in ({$placeholders}) and (published_at is null or published_at = '')",
            [date('c'), ...$ids]
        );
    }

    private function bulkUpdateUsers(array $ids, int $isActive): void
    {
        if (!$ids) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->db()->execute(
            "update users set is_active = ? where id in ({$placeholders})",
            [$isActive, ...$ids]
        );
    }

    private function bulkDelete(string $table, array $ids): void
    {
        $allowedTables = ['pages', 'news', 'documents', 'users'];
        if (!in_array($table, $allowedTables, true) || !$ids) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->db()->execute("delete from {$table} where id in ({$placeholders})", $ids);
    }

    private function importOptions(): array
    {
        return [
            'news' => [
                'name' => 'Новини',
                'description' => 'Створює новини з назвою, текстом, статусом і датою публікації.',
                'columns' => 'title, body, slug, status, published_at',
            ],
            'pages' => [
                'name' => 'Сторінки',
                'description' => 'Створює сторінки з текстовим блоком або HTML-контентом.',
                'columns' => 'title, body, slug, excerpt, template, status, sort_order',
            ],
            'documents' => [
                'name' => 'Документи',
                'description' => 'Імпортує картки документів без завантаження файлів.',
                'columns' => 'title, category, description, status, responsible, approved_at, published_at, public_info_section',
            ],
            'public_info_sections' => [
                'name' => 'Розділи публічної інформації',
                'description' => 'Додає нові розділи до сторінки публічної інформації.',
                'columns' => 'title, slug, description, is_required, sort_order',
            ],
            'global_fields' => [
                'name' => 'Глобальні поля',
                'description' => 'Додає універсальні поля для футера та загальних даних сайту.',
                'columns' => 'label, value',
            ],
            'wordpress' => [
                'name' => 'WordPress',
                'description' => 'Імпортує записи з таблиці wp_posts: дописи як новини, сторінки як сторінки.',
                'columns' => 'post_title, post_content, post_name, post_type, post_status, post_date',
            ],
        ];
    }

    private function saveSetting(string $name, string $value): void
    {
        $this->db()->execute(
            'insert into settings (name, value) values (?, ?) on duplicate key update value = values(value)',
            [$name, $value]
        );
    }

    private function globalFields(): array
    {
        $settings = $this->siteSettings();
        $fields = json_decode((string) ($settings['global_fields'] ?? '[]'), true);
        return is_array($fields) ? $fields : [];
    }

    private function normalizeGlobalFields(Request $request): array
    {
        $labels = $request->input('global_field_label', []);
        $values = $request->input('global_field_value', []);
        if (!is_array($labels) || !is_array($values)) {
            return [];
        }

        $fields = [];
        foreach ($labels as $index => $label) {
            $label = trim((string) $label);
            $value = trim((string) ($values[$index] ?? ''));
            if ($label === '' && $value === '') {
                continue;
            }

            $fields[] = [
                'label' => $label !== '' ? $label : 'Поле',
                'value' => $value,
            ];
        }

        return $fields;
    }

    private function readImportRows(Request $request, bool $preview = false): array
    {
        if ((string) $request->input('source', 'file') === 'database') {
            return $this->readDatabaseImportRows($request, $preview);
        }

        $source = trim((string) $request->input('import_text', ''));
        $file = $request->files['import_file'] ?? null;
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_file((string) ($file['tmp_name'] ?? ''))) {
            $source = (string) file_get_contents((string) $file['tmp_name']);
        }

        if ($source === '') {
            return [];
        }

        $format = (string) $request->input('format', 'auto');
        $trimmedSource = ltrim($source);
        if ($format === 'json' || ($format === 'auto' && (str_starts_with($trimmedSource, '[') || str_starts_with($trimmedSource, '{')))) {
            return $this->parseImportJson($source, (string) $request->input('type', ''));
        }

        return $this->parseImportCsv($source);
    }

    private function readDatabaseImportRows(Request $request, bool $preview): array
    {
        $profile = (string) $request->input('db_profile', 'wordpress');
        if ($profile !== 'wordpress') {
            throw new \RuntimeException('Поки підтримується тільки профіль WordPress.');
        }

        return $this->readWordPressRows($request, $preview);
    }

    private function readWordPressRows(Request $request, bool $preview): array
    {
        $pdo = $this->externalImportPdo($request);
        $prefix = $this->sanitizeDbIdentifier((string) $request->input('db_prefix', 'wp_'));
        $table = $prefix . 'posts';
        $limit = $preview ? 10 : max(1, min(1000, (int) $request->input('db_limit', 200)));
        $status = (string) $request->input('wp_status', 'publish');
        if (!in_array($status, ['publish', 'draft', 'any'], true)) {
            $status = 'publish';
        }

        $where = "post_type in ('post', 'page')";
        $params = [];
        if ($status !== 'any') {
            $where .= ' and post_status = ?';
            $params[] = $status;
        }

        $mediaMap = [];
        if (!$preview && $request->input('wp_import_media')) {
            $mediaMap = $this->importWordPressMedia($pdo, $prefix, $request);
        }

        $stmt = $pdo->prepare(
            "select ID, post_title, post_name, post_content, post_excerpt, post_type, post_status, post_date
             from {$table}
             where {$where}
             order by post_date desc, ID desc
             limit {$limit}"
        );
        $stmt->execute($params);

        $rows = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $status = (($row['post_status'] ?? '') === 'publish') ? 'published' : 'draft';
            $body = (string) ($row['post_content'] ?? '');
            if ($mediaMap) {
                $body = str_replace(array_keys($mediaMap), array_values($mediaMap), $body);
            }

            $rows[] = [
                '_import_target' => (($row['post_type'] ?? '') === 'page') ? 'pages' : 'news',
                'title' => (string) ($row['post_title'] ?? ''),
                'slug' => (string) ($row['post_name'] ?? ''),
                'body' => $body,
                'excerpt' => (string) ($row['post_excerpt'] ?? ''),
                'status' => $status,
                'published_at' => (string) ($row['post_date'] ?? ''),
                'sort_order' => '0',
            ];
        }

        return $rows;
    }

    private function importWordPressMedia(\PDO $pdo, string $prefix, Request $request): array
    {
        $postsTable = $prefix . 'posts';
        $metaTable = $prefix . 'postmeta';
        $siteUrl = rtrim(trim((string) $request->input('wp_site_url', '')), '/');
        $uploadsPath = rtrim(trim((string) $request->input('wp_uploads_path', '')), '/\\');
        $limit = max(1, min(5000, (int) $request->input('wp_media_limit', 1000)));

        $stmt = $pdo->prepare(
            "select p.ID, p.guid, p.post_title, p.post_name, p.post_mime_type, m.meta_value as attached_file, mm.meta_value as attachment_metadata
             from {$postsTable} p
             left join {$metaTable} m on m.post_id = p.ID and m.meta_key = '_wp_attached_file'
             left join {$metaTable} mm on mm.post_id = p.ID and mm.meta_key = '_wp_attachment_metadata'
             where p.post_type = 'attachment'
             order by p.ID asc
             limit {$limit}"
        );
        $stmt->execute();

        $map = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $attachment) {
            $relativeSource = trim((string) ($attachment['attached_file'] ?? ''), '/');
            $sourceUrl = trim((string) ($attachment['guid'] ?? ''));
            $source = $this->wordPressMediaSource($relativeSource, $sourceUrl, $siteUrl, $uploadsPath);
            if ($source === '') {
                continue;
            }

            $savedPath = $this->saveImportedMedia($source, $relativeSource, $sourceUrl, (int) ($attachment['ID'] ?? 0));
            if ($savedPath === '') {
                continue;
            }

            $newUrl = url('/uploads/' . $savedPath);
            foreach ($this->wordPressMediaAliases($relativeSource, $sourceUrl, $siteUrl, (string) ($attachment['attachment_metadata'] ?? ''), (string) ($attachment['post_name'] ?? ''), (int) ($attachment['ID'] ?? 0)) as $alias) {
                $map[$alias] = $newUrl;
            }
        }

        uksort($map, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        return $map;
    }

    private function wordPressMediaSource(string $relativeSource, string $sourceUrl, string $siteUrl, string $uploadsPath): string
    {
        if ($uploadsPath !== '' && $relativeSource !== '') {
            $local = $uploadsPath . '/' . $relativeSource;
            if (is_file($local)) {
                return $local;
            }
        }

        if ($sourceUrl !== '') {
            return $sourceUrl;
        }

        if ($siteUrl !== '' && $relativeSource !== '') {
            return $siteUrl . '/wp-content/uploads/' . $relativeSource;
        }

        return '';
    }

    private function saveImportedMedia(string $source, string $relativeSource, string $sourceUrl, int $id): string
    {
        $name = basename($relativeSource ?: (parse_url($sourceUrl, PHP_URL_PATH) ?: ''));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp'], true)) {
            return '';
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', pathinfo($name, PATHINFO_FILENAME)) ?: 'file';
        $targetRelative = date('Y/m/') . 'wp-' . $id . '-' . $safeName . '.' . $extension;
        $target = base_path('storage/uploads/' . $targetRelative);
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }
        if (is_file($target)) {
            return $targetRelative;
        }

        $context = stream_context_create(['http' => ['timeout' => 15], 'https' => ['timeout' => 15]]);
        $contents = is_file($source) ? file_get_contents($source) : @file_get_contents($source, false, $context);
        if ($contents === false || $contents === '') {
            return '';
        }

        file_put_contents($target, $contents);
        return $targetRelative;
    }

    private function wordPressMediaAliases(string $relativeSource, string $sourceUrl, string $siteUrl, string $metadata, string $attachmentSlug, int $attachmentId): array
    {
        $aliases = [];
        $relativeFiles = array_merge([$relativeSource], $this->wordPressMetadataFiles($relativeSource, $metadata));
        $baseUrls = array_filter([$siteUrl, $this->wordPressUploadsBaseUrl($sourceUrl)]);

        foreach ($relativeFiles as $relativeFile) {
            $relativeFile = trim($relativeFile, '/');
            if ($relativeFile === '') {
                continue;
            }

            $aliases[] = '/wp-content/uploads/' . $relativeFile;
            foreach ($baseUrls as $baseUrl) {
                foreach ([$baseUrl, $this->toggleScheme($baseUrl)] as $alternateBaseUrl) {
                    if ($alternateBaseUrl !== '') {
                        $aliases[] = rtrim($alternateBaseUrl, '/') . '/wp-content/uploads/' . $relativeFile;
                    }
                }
            }
            if ($sourceUrl !== '' && basename(parse_url($sourceUrl, PHP_URL_PATH) ?: '') === basename($relativeFile)) {
                $aliases[] = $sourceUrl;
                $alternateUrl = $this->toggleScheme($sourceUrl);
                if ($alternateUrl !== '') {
                    $aliases[] = $alternateUrl;
                }
            }
        }
        if ($siteUrl !== '' && $attachmentSlug !== '') {
            foreach ([$siteUrl, $this->toggleScheme($siteUrl)] as $baseUrl) {
                if ($baseUrl !== '') {
                    $aliases[] = rtrim($baseUrl, '/') . '/' . trim($attachmentSlug, '/') . '/';
                    $aliases[] = rtrim($baseUrl, '/') . '/' . trim($attachmentSlug, '/');
                }
            }
        }
        if ($attachmentId > 0) {
            $aliases[] = '/?attachment_id=' . $attachmentId;
            if ($siteUrl !== '') {
                foreach ([$siteUrl, $this->toggleScheme($siteUrl)] as $baseUrl) {
                    if ($baseUrl !== '') {
                        $aliases[] = rtrim($baseUrl, '/') . '/?attachment_id=' . $attachmentId;
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($aliases)));
    }

    private function wordPressUploadsBaseUrl(string $sourceUrl): string
    {
        $marker = '/wp-content/uploads/';
        $position = strpos($sourceUrl, $marker);
        if ($position === false) {
            return '';
        }

        return substr($sourceUrl, 0, $position);
    }

    private function wordPressMetadataFiles(string $relativeSource, string $metadata): array
    {
        if ($relativeSource === '' || $metadata === '') {
            return [];
        }

        $directory = trim(dirname($relativeSource), '.');
        $directory = $directory === '' ? '' : trim($directory, '/') . '/';
        preg_match_all('/"file";s:\d+:"([^"]+)"/', $metadata, $matches);

        $files = [];
        foreach ($matches[1] ?? [] as $file) {
            $file = trim((string) $file, '/');
            if ($file === '' || str_contains($file, '/')) {
                continue;
            }
            $files[] = $directory . $file;
        }

        return array_values(array_unique($files));
    }

    private function toggleScheme(string $url): string
    {
        if (str_starts_with($url, 'http://')) {
            return 'https://' . substr($url, 7);
        }
        if (str_starts_with($url, 'https://')) {
            return 'http://' . substr($url, 8);
        }

        return '';
    }

    private function externalImportPdo(Request $request): \PDO
    {
        $host = trim((string) $request->input('db_host', '127.0.0.1'));
        $port = trim((string) $request->input('db_port', '3306')) ?: '3306';
        $name = trim((string) $request->input('db_name', ''));
        $user = trim((string) $request->input('db_user', ''));
        $password = (string) $request->input('db_password', '');
        $charset = trim((string) $request->input('db_charset', 'utf8mb4')) ?: 'utf8mb4';
        if ($host === '' || $name === '' || $user === '') {
            throw new \RuntimeException('Заповніть host, назву БД і користувача для підключення.');
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);
        $pdo = new \PDO($dsn, $user, $password);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        return $pdo;
    }

    private function sanitizeDbIdentifier(string $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9_]/', '', $value) ?? '';
        return $value !== '' ? $value : 'wp_';
    }

    private function parseImportJson(string $source, string $type): array
    {
        $data = json_decode($source, true);
        if (!is_array($data)) {
            throw new \RuntimeException('JSON має бути масивом записів.');
        }

        if (isset($data[$type]) && is_array($data[$type])) {
            $data = $data[$type];
        }

        $rows = [];
        foreach ($data as $row) {
            if (is_array($row)) {
                $rows[] = $this->normalizeImportRow($row);
            }
        }

        return $rows;
    }

    private function parseImportCsv(string $source): array
    {
        $stream = fopen('php://temp', 'r+');
        if (!$stream) {
            throw new \RuntimeException('Не вдалося прочитати CSV.');
        }

        fwrite($stream, $source);
        rewind($stream);

        $delimiter = $this->detectCsvDelimiter($source);
        $headers = null;
        $rows = [];
        while (($values = fgetcsv($stream, 0, $delimiter)) !== false) {
            if ($values === [null] || $values === false) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map([$this, 'normalizeImportKey'], $values);
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $row[$header] = trim((string) ($values[$index] ?? ''));
            }
            if (array_filter($row, static fn ($value) => $value !== '')) {
                $rows[] = $row;
            }
        }

        fclose($stream);
        return $rows;
    }

    private function detectCsvDelimiter(string $source): string
    {
        $firstLine = strtok($source, "\r\n") ?: '';
        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }

    private function normalizeImportRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[$this->normalizeImportKey((string) $key)] = is_scalar($value) ? trim((string) $value) : $value;
        }

        return $normalized;
    }

    private function normalizeImportKey(string $key): string
    {
        $key = trim($key);
        $key = preg_replace('/^\xEF\xBB\xBF/', '', $key) ?? $key;
        $key = function_exists('mb_strtolower') ? mb_strtolower($key) : strtolower($key);
        $key = preg_replace('/[^a-z0-9а-яіїєґ_]+/u', '_', $key) ?? $key;
        return trim($key, '_');
    }

    private function importRows(string $type, array $rows): int
    {
        return match ($type) {
            'news' => $this->importNewsRows($rows),
            'pages' => $this->importPageRows($rows),
            'documents' => $this->importDocumentRows($rows),
            'public_info_sections' => $this->importPublicInfoSectionRows($rows),
            'global_fields' => $this->importGlobalFieldRows($rows),
            'wordpress' => $this->importWordPressRows($rows),
            default => 0,
        };
    }

    private function importWordPressRows(array $rows): int
    {
        $created = 0;
        $pageRows = [];
        $newsRows = [];
        foreach ($rows as $row) {
            if (($row['_import_target'] ?? '') === 'pages') {
                $pageRows[] = $row;
            } else {
                $newsRows[] = $row;
            }
        }

        if ($newsRows) {
            $created += $this->importNewsRows($newsRows);
        }
        if ($pageRows) {
            $created += $this->importPageRows($pageRows);
        }

        return $created;
    }

    private function importNewsRows(array $rows): int
    {
        $created = 0;
        $now = date('c');
        foreach ($rows as $row) {
            $title = $this->importValue($row, ['title', 'назва', 'заголовок']);
            if ($title === '') {
                continue;
            }

            $status = $this->importStatus($this->importValue($row, ['status', 'статус']), 'draft');
            $publishedAt = $this->importValue($row, ['published_at', 'дата_публікації', 'дата']);
            if ($status === 'published' && $publishedAt === '') {
                $publishedAt = $now;
            }

            $this->db()->execute(
                'insert into news (title, slug, body, status, published_at, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?)',
                [
                    $title,
                    $this->uniqueSlug('news', $this->importValue($row, ['slug', 'адреса']) ?: $title),
                    $this->importValue($row, ['body', 'text', 'контент', 'текст', 'опис']),
                    $status,
                    $publishedAt ?: null,
                    $now,
                    $now,
                ]
            );
            $created++;
        }

        return $created;
    }

    private function importPageRows(array $rows): int
    {
        $created = 0;
        $now = date('c');
        foreach ($rows as $row) {
            $title = $this->importValue($row, ['title', 'назва', 'заголовок']);
            if ($title === '') {
                continue;
            }

            $template = $this->importValue($row, ['template', 'шаблон']) ?: 'default';
            if (!array_key_exists($template, $this->pageTemplates())) {
                $template = 'default';
            }
            $body = $this->importValue($row, ['body', 'blocks_text', 'text', 'контент', 'текст', 'опис']);
            $blocks = json_encode($this->blocksFromText($body), JSON_UNESCAPED_UNICODE);

            $this->db()->execute(
                'insert into pages (title, slug, excerpt, template, blocks_json, status, sort_order, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $title,
                    $this->uniqueSlug('pages', $this->importValue($row, ['slug', 'адреса']) ?: $title),
                    $this->importValue($row, ['excerpt', 'анонс', 'короткий_опис']),
                    $template,
                    $blocks === false ? '[]' : $blocks,
                    $this->importStatus($this->importValue($row, ['status', 'статус']), 'draft'),
                    (int) ($this->importValue($row, ['sort_order', 'порядок']) ?: 0),
                    $now,
                    $now,
                ]
            );
            $created++;
        }

        return $created;
    }

    private function importDocumentRows(array $rows): int
    {
        $created = 0;
        $now = date('c');
        foreach ($rows as $row) {
            $title = $this->importValue($row, ['title', 'назва', 'заголовок']);
            if ($title === '') {
                continue;
            }

            $status = $this->importStatus($this->importValue($row, ['status', 'статус']), 'published');
            $publishedAt = $this->importValue($row, ['published_at', 'дата_публікації', 'дата']);
            if ($status === 'published' && $publishedAt === '') {
                $publishedAt = $now;
            }

            $this->db()->execute(
                'insert into documents (public_info_section_id, title, category, file_path, description, status, responsible, approved_at, published_at, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $this->resolvePublicInfoSectionId($this->importValue($row, ['public_info_section_id', 'public_info_section', 'розділ', 'розділ_публічної_інформації'])),
                    $title,
                    $this->importValue($row, ['category', 'категорія']) ?: 'Загальні документи',
                    null,
                    $this->importValue($row, ['description', 'опис', 'текст']),
                    $status,
                    $this->importValue($row, ['responsible', 'відповідальний']),
                    $this->importValue($row, ['approved_at', 'дата_затвердження']) ?: null,
                    $publishedAt ?: null,
                    $now,
                    $now,
                ]
            );
            $created++;
        }

        return $created;
    }

    private function importPublicInfoSectionRows(array $rows): int
    {
        $created = 0;
        foreach ($rows as $row) {
            $title = $this->importValue($row, ['title', 'назва', 'заголовок']);
            if ($title === '') {
                continue;
            }

            $this->db()->execute(
                'insert into public_info_sections (title, slug, description, is_required, sort_order) values (?, ?, ?, ?, ?)',
                [
                    $title,
                    $this->uniqueSlug('public_info_sections', $this->importValue($row, ['slug', 'адреса']) ?: $title),
                    $this->importValue($row, ['description', 'опис']),
                    $this->importBool($this->importValue($row, ['is_required', 'обовязковий', 'обов_язковий']), true) ? 1 : 0,
                    (int) ($this->importValue($row, ['sort_order', 'порядок']) ?: 0),
                ]
            );
            $created++;
        }

        return $created;
    }

    private function importGlobalFieldRows(array $rows): int
    {
        $fields = $this->globalFields();
        $created = 0;
        foreach ($rows as $row) {
            $label = $this->importValue($row, ['label', 'name', 'title', 'назва', 'поле']);
            $value = $this->importValue($row, ['value', 'значення']);
            if ($label === '' && $value === '') {
                continue;
            }

            $fields[] = ['label' => $label !== '' ? $label : 'Поле', 'value' => $value];
            $created++;
        }

        $encodedFields = json_encode($fields, JSON_UNESCAPED_UNICODE);
        $this->saveSetting('global_fields', $encodedFields === false ? '[]' : $encodedFields);
        return $created;
    }

    private function importPreviewSummary(string $type, array $rows): array
    {
        if ($type !== 'wordpress') {
            return [
                ['label' => 'Записів у перегляді', 'value' => (string) count($rows)],
                ['label' => 'Тип імпорту', 'value' => $this->importOptions()[$type]['name'] ?? $type],
            ];
        }

        $pages = 0;
        $news = 0;
        foreach ($rows as $row) {
            if (($row['_import_target'] ?? '') === 'pages') {
                $pages++;
            } else {
                $news++;
            }
        }

        return [
            ['label' => 'Записів у перегляді', 'value' => (string) count($rows)],
            ['label' => 'Новини', 'value' => (string) $news],
            ['label' => 'Сторінки', 'value' => (string) $pages],
        ];
    }

    private function importPreviewRows(string $type, array $rows): array
    {
        $preview = [];
        foreach ($rows as $row) {
            $target = $type === 'wordpress'
                ? (($row['_import_target'] ?? '') === 'pages' ? 'Сторінка' : 'Новина')
                : ($this->importOptions()[$type]['name'] ?? $type);
            $title = $this->importValue($row, ['title', 'label', 'name', 'назва', 'заголовок', 'поле']);
            $status = $this->importValue($row, ['status', 'статус']);
            $date = $this->importValue($row, ['published_at', 'дата_публікації', 'дата']);
            $text = $this->importValue($row, ['body', 'description', 'value', 'text', 'контент', 'текст', 'опис', 'значення']);

            $preview[] = [
                'target' => $target,
                'title' => $title !== '' ? $title : 'Без назви',
                'status' => $status !== '' ? $status : '-',
                'date' => $date !== '' ? $date : '-',
                'excerpt' => excerpt($text, 120),
            ];
        }

        return $preview;
    }

    private function importValue(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $key = $this->normalizeImportKey($key);
            if (array_key_exists($key, $row) && is_scalar($row[$key])) {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }

    private function importStatus(string $status, string $default): string
    {
        $status = strtolower(trim($status));
        return in_array($status, ['draft', 'published'], true) ? $status : $default;
    }

    private function importBool(string $value, bool $default): bool
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return $default;
        }

        return in_array($value, ['1', 'yes', 'true', 'так', 'да'], true);
    }

    private function resolvePublicInfoSectionId(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        $section = $this->db()->fetch('select id from public_info_sections where title = ? or slug = ? limit 1', [$value, $this->slug($value)]);
        return $section ? (int) $section['id'] : null;
    }

    private function uniqueSlug(string $table, string $value): string
    {
        $base = $this->limitSlug($this->slug($value), 170);
        if ($base === '') {
            $base = 'item';
        }

        $slug = $base;
        $index = 2;
        while ($this->db()->fetch("select id from {$table} where slug = ? limit 1", [$slug])) {
            $suffix = '-' . $index++;
            $slug = $this->limitSlug($base, 180 - $this->textLength($suffix)) . $suffix;
        }

        return $slug;
    }

    private function limitSlug(string $value, int $limit): string
    {
        if (function_exists('mb_substr')) {
            return trim(mb_substr($value, 0, $limit), '-');
        }

        return trim(substr($value, 0, $limit), '-');
    }

    private function textLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    private function mediaReferences(): array
    {
        $references = [];
        $documents = $this->db()->fetchAll('select id, title, file_path from documents where file_path is not null and file_path != ? order by id desc', ['']);
        foreach ($documents as $document) {
            $path = Files::normalize((string) ($document['file_path'] ?? ''));
            if ($path === '') {
                continue;
            }

            $references[$path] = [
                'label' => (string) ($document['title'] ?? 'Документ'),
                'url' => url('/admin/documents/edit?id=' . $document['id']),
            ];
        }

        return $references;
    }

    private function filterMedia(array $items, string $query): array
    {
        if ($query === '') {
            return $items;
        }

        $needle = function_exists('mb_strtolower') ? mb_strtolower($query) : strtolower($query);
        return array_values(array_filter($items, static function (array $item) use ($needle): bool {
            $text = implode(' ', [
                $item['path'] ?? '',
                $item['name'] ?? '',
                $item['extension'] ?? '',
                $item['type'] ?? '',
                $item['reference']['label'] ?? '',
            ]);
            $text = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
            return strpos($text, $needle) !== false;
        }));
    }

    private function mediaListPayload(string $query, array $pagination): array
    {
        $allItems = Files::all($this->mediaReferences());
        $filteredItems = $this->filterMedia($allItems, $query);
        $total = count($filteredItems);
        $items = array_slice($filteredItems, $pagination['offset'], $pagination['limit']);
        $loaded = $pagination['offset'] + count($items);

        return [
            'ok' => true,
            'items' => $items,
            'html' => $this->view()->partial('admin/media/rows', ['items' => $items]),
            'total' => $total,
            'next_offset' => $loaded,
            'has_more' => $loaded < $total,
            'stats' => $this->mediaStats($allItems),
        ];
    }

    private function mediaStats(array $items): array
    {
        $size = 0;
        $images = 0;
        $used = 0;
        foreach ($items as $item) {
            $size += (int) ($item['size'] ?? 0);
            $images += !empty($item['is_image']) ? 1 : 0;
            $used += !empty($item['is_used']) ? 1 : 0;
        }

        return [
            'total' => count($items),
            'images' => $images,
            'unused' => count($items) - $used,
            'size' => $this->formatBytes($size),
        ];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['Б', 'КБ', 'МБ', 'ГБ'];
        $value = (float) $bytes;
        $unit = 0;
        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return ($unit === 0 ? (string) $bytes : number_format($value, 1, '.', '')) . ' ' . $units[$unit];
    }

    private function isAjaxRequest(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    private function isAjax(Request $request): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || (string) $request->input('_ajax', '') === '1';
    }

    private function ajaxError(Request $request, Throwable $e): Response
    {
        if ($this->isAjax($request)) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }

        throw $e;
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9а-яіїєґ]+/u', '-', $value);
        $value = trim($value ?: 'item', '-');
        if (function_exists('mb_substr')) {
            return trim(mb_substr($value, 0, 170), '-');
        }
        return trim(substr($value, 0, 170), '-');
    }

    private function blocksFromText(string $text): array
    {
        if ($text !== strip_tags($text)) {
            $title = 'Текст';
            if (preg_match('/<h[1-4][^>]*>(.*?)<\/h[1-4]>/is', $text, $match)) {
                $title = trim(strip_tags($match[1])) ?: $title;
            }

            return [['type' => 'text', 'title' => $title, 'text' => trim($text)]];
        }

        $blocks = [];
        foreach (preg_split('/\R{2,}/', trim($text)) ?: [] as $part) {
            $lines = preg_split('/\R/', trim($part)) ?: [];
            $title = array_shift($lines) ?: 'Текст';
            $blocks[] = ['type' => 'text', 'title' => $title, 'text' => implode("\n", $lines)];
        }
        return $blocks ?: [['type' => 'text', 'title' => 'Текст', 'text' => '']];
    }
}
