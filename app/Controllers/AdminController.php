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
            'publicFilled' => $this->db()->fetch("select count(distinct public_info_section_id) as c from documents where status = 'published' and public_info_section_id is not null")['c'] ?? 0,
            'publicTotal' => $this->count('public_info_sections'),
        ];
        return $this->admin('admin/dashboard', ['title' => 'Панель керування', 'stats' => $stats]);
    }

    public function pages(): Response
    {
        $this->guard();
        return $this->admin('admin/pages/index', [
            'title' => 'Сторінки',
            'items' => $this->db()->fetchAll('select * from pages order by sort_order asc, id desc'),
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
        $now = date('c');
        $id = (int) $request->input('id', 0);
        $blocks = $this->blocksFromText((string) $request->input('blocks_text'));
        $data = [
            $request->input('title'),
            $this->slug((string) $request->input('slug', $request->input('title'))),
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
        redirect('/admin/pages');
    }

    public function news(): Response
    {
        $this->guard();
        return $this->admin('admin/news/index', [
            'title' => 'Новини',
            'items' => $this->db()->fetchAll('select * from news order by id desc'),
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
        $now = date('c');
        $id = (int) $request->input('id', 0);
        $publishedAt = $request->input('status') === 'published' ? ($request->input('published_at') ?: $now) : null;
        $data = [
            $request->input('title'),
            $this->slug((string) $request->input('slug', $request->input('title'))),
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
        redirect('/admin/news');
    }

    public function documents(): Response
    {
        $this->guard();
        return $this->admin('admin/documents/index', [
            'title' => 'Документи',
            'items' => $this->db()->fetchAll('select * from documents order by id desc'),
            'sections' => $this->db()->fetchAll('select id, title from public_info_sections order by sort_order asc'),
        ]);
    }

    public function documentSave(Request $request): Response
    {
        $this->guard('documents.manage');
        Csrf::verify();
        $filePath = Files::upload($request->files['file'] ?? []);
        $now = date('c');
        $this->db()->execute(
            'insert into documents (public_info_section_id, title, category, file_path, description, status, responsible, approved_at, published_at, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $request->input('public_info_section_id') ?: null,
                $request->input('title'),
                $request->input('category'),
                $filePath,
                $request->input('description'),
                $request->input('status', 'published'),
                $request->input('responsible'),
                $request->input('approved_at'),
                $request->input('published_at') ?: ($request->input('status') === 'published' ? $now : null),
                $now,
                $now,
            ]
        );
        $this->audit('create', 'document', (int) $this->db()->lastInsertId());
        redirect('/admin/documents');
    }

    public function publicInfo(): Response
    {
        $this->guard();
        return $this->admin('admin/public-info/index', [
            'title' => 'Публічна інформація',
            'sections' => $this->db()->fetchAll(
                'select s.*, count(d.id) as documents_count, sum(case when d.status = \'published\' then 1 else 0 end) as published_documents_count, max(d.updated_at) as last_document_at
                 from public_info_sections s
                 left join documents d on d.public_info_section_id = s.id
                 group by s.id, s.title, s.slug, s.description, s.is_required, s.sort_order
                 order by s.sort_order asc'
            ),
            'documents' => $this->db()->fetchAll(
                'select d.*, s.title as section_title
                 from documents d
                 left join public_info_sections s on s.id = d.public_info_section_id
                 where d.public_info_section_id is not null
                 order by s.sort_order asc, d.updated_at desc'
            ),
        ]);
    }

    public function publicInfoSave(Request $request): Response
    {
        $this->guard('public_info.manage');
        Csrf::verify();
        $filePath = Files::upload($request->files['file'] ?? []);
        $now = date('c');
        $this->db()->execute(
            'insert into documents (public_info_section_id, title, category, file_path, description, status, responsible, approved_at, published_at, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $request->input('public_info_section_id'),
                $request->input('title'),
                'Публічна інформація',
                $filePath,
                $request->input('description'),
                $request->input('status', 'published'),
                $request->input('responsible'),
                $request->input('approved_at'),
                $request->input('published_at') ?: ($request->input('status') === 'published' ? $now : null),
                $now,
                $now,
            ]
        );
        $this->audit('create', 'public_info_document', (int) $this->db()->lastInsertId());
        redirect('/admin/public-info');
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
            return $this->json([
                'ok' => true,
                'message' => 'Розділ збережено.',
                'section' => [
                    'id' => $id,
                    'title' => $title,
                    'slug' => $slug,
                    'description' => $description,
                    'is_required' => $isRequired,
                    'sort_order' => $sortOrder,
                ],
                'created' => !$request->input('id'),
            ]);
        } catch (Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 500);
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

    public function users(): Response
    {
        $this->guard('users.manage');
        return $this->admin('admin/users/index', [
            'title' => 'Користувачі',
            'items' => $this->db()->fetchAll('select * from users order by id desc'),
        ]);
    }

    public function userSave(Request $request): Response
    {
        $this->guard('users.manage');
        Csrf::verify();
        $this->db()->execute(
            'insert into users (name, email, password_hash, role, is_active, created_at) values (?, ?, ?, ?, 1, ?)',
            [$request->input('name'), $request->input('email'), password_hash((string) $request->input('password'), PASSWORD_DEFAULT), $request->input('role'), date('c')]
        );
        redirect('/admin/users');
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
        foreach (['institution_name', 'institution_type', 'edrpou', 'address', 'phone', 'email'] as $key) {
            $this->db()->execute('update settings set value = ? where name = ?', [$request->input($key), $key]);
        }
        redirect('/admin/settings');
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
        $blocks = [];
        foreach (preg_split('/\R{2,}/', trim($text)) ?: [] as $part) {
            $lines = preg_split('/\R/', trim($part)) ?: [];
            $title = array_shift($lines) ?: 'Текст';
            $blocks[] = ['type' => 'text', 'title' => $title, 'text' => implode("\n", $lines)];
        }
        return $blocks ?: [['type' => 'text', 'title' => 'Текст', 'text' => '']];
    }
}
