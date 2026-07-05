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
        return $this->admin('admin/pages/form', ['title' => 'Сторінка', 'item' => $item]);
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
            $data = [
                $request->input('title'),
                $slug,
                $request->input('excerpt'),
                json_encode($blocks, JSON_UNESCAPED_UNICODE),
                $request->input('status', 'draft'),
                (int) $request->input('sort_order', 0),
                $now,
            ];

            if ($id) {
                $this->db()->execute('update pages set title=?, slug=?, excerpt=?, blocks_json=?, status=?, sort_order=?, updated_at=? where id=?', [...$data, $id]);
            } else {
                $this->db()->execute('insert into pages (title, slug, excerpt, blocks_json, status, sort_order, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?)', [...$data, $now]);
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

    public function settings(): Response
    {
        $this->guard('settings.manage');
        return $this->admin('admin/settings', ['title' => 'Налаштування', 'settings' => $this->siteSettings()]);
    }

    public function settingsSave(Request $request): Response
    {
        $this->guard('settings.manage');
        Csrf::verify();
        try {
            foreach (['institution_name', 'institution_type', 'edrpou', 'address', 'phone', 'email'] as $key) {
                $this->db()->execute('update settings set value = ? where name = ?', [$request->input($key), $key]);
            }

            if ($this->isAjax($request)) {
                return $this->json(['ok' => true, 'message' => 'Налаштування збережено.']);
            }

            redirect('/admin/settings');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
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
            return mb_substr($value, 0, 180);
        }
        return substr($value, 0, 180);
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
