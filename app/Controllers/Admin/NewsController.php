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
        [$where, $params] = $this->searchWhere($query, ['title', 'slug', 'category', 'body', 'status']);
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
        return $this->admin('admin/news/form', [
            'title' => 'Новина',
            'item' => $item,
            'categories' => $this->newsCategoryOptions(),
        ]);
    }

    public function newsCategories(Request $request): Response
    {
        $this->guard();
        return $this->admin('admin/news/categories', [
            'title' => 'Категорії новин',
            'categories' => $this->newsCategoriesWithCounts(),
        ]);
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
            $category = trim((string) $request->input('category', ''));
            if ($category === '') {
                $category = 'Загальні';
            }
            $this->ensureNewsCategory($category);
            $data = [
                $request->input('title'),
                $slug,
                $category,
                $request->input('body'),
                $request->input('status', 'draft'),
                $publishedAt,
                $now,
            ];
            if ($id) {
                $this->db()->execute('update news set title=?, slug=?, category=?, body=?, status=?, published_at=?, updated_at=? where id=?', [...$data, $id]);
            } else {
                $this->db()->execute('insert into news (title, slug, category, body, status, published_at, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?)', [...$data, $now]);
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

    public function newsCategorySave(Request $request): Response
    {
        $this->guard('news.manage');
        Csrf::verify();

        try {
            $id = (int) $request->input('id', 0);
            $title = trim((string) $request->input('title', ''));
            if ($title === '') {
                return $this->json(['ok' => false, 'message' => 'Вкажіть назву категорії.'], 422);
            }
            $duplicateParams = [$title];
            $duplicateSql = 'select id from news_categories where title = ?';
            if ($id) {
                $duplicateSql .= ' and id <> ?';
                $duplicateParams[] = $id;
            }
            if ($this->db()->fetch($duplicateSql, $duplicateParams)) {
                return $this->json(['ok' => false, 'message' => 'Категорія з такою назвою вже існує.'], 422);
            }

            $now = date('c');
            $sortOrder = (int) $request->input('sort_order', 100);
            $slug = $this->uniqueNewsCategorySlug($this->slug($title), $id);
            $existing = $id ? $this->db()->fetch('select * from news_categories where id = ?', [$id]) : null;

            if ($id && $existing) {
                $this->db()->execute(
                    'update news_categories set title=?, slug=?, sort_order=?, updated_at=? where id=?',
                    [$title, $slug, $sortOrder, $now, $id]
                );
                if (($existing['title'] ?? '') !== $title) {
                    $this->db()->execute('update news set category=? where category=?', [$title, $existing['title']]);
                }
            } else {
                $this->db()->execute(
                    'insert into news_categories (title, slug, sort_order, created_at, updated_at) values (?, ?, ?, ?, ?)',
                    [$title, $slug, $sortOrder, $now, $now]
                );
                $id = (int) $this->db()->lastInsertId();
            }

            $this->audit('save', 'news_category', $id);

            if ($this->isAjax($request)) {
                return $this->json([
                    'ok' => true,
                    'message' => 'Категорію збережено.',
                    'id' => $id,
                    'edit_url' => url('/admin/news/categories'),
                ]);
            }

            redirect('/admin/news/categories');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
        }
    }

    public function newsCategoryDelete(Request $request): Response
    {
        $this->guard('news.manage');
        Csrf::verify();

        try {
            $id = (int) $request->input('id', 0);
            $category = $id ? $this->db()->fetch('select * from news_categories where id = ?', [$id]) : null;
            if (!$category) {
                return $this->json(['ok' => false, 'message' => 'Категорію не знайдено.'], 404);
            }

            $newsCount = (int) ($this->db()->fetch('select count(*) as c from news where category = ?', [$category['title']])['c'] ?? 0);
            if ($newsCount > 0) {
                return $this->json(['ok' => false, 'message' => 'Категорія має новини, тому її не можна видалити.'], 422);
            }

            $this->db()->execute('delete from news_categories where id = ?', [$id]);
            $this->audit('delete', 'news_category', $id);

            if ($this->isAjax($request)) {
                return $this->json(['ok' => true, 'message' => 'Категорію видалено.']);
            }

            redirect('/admin/news/categories');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
        }
    }

    private function newsCategoryOptions(): array
    {
        $categories = $this->db()->fetchAll('select title as category from news_categories order by sort_order asc, title asc');
        if ($categories) {
            return $categories;
        }

        return $this->db()->fetchAll("select distinct category from news where category is not null and category <> '' order by category asc");
    }

    private function newsCategoriesWithCounts(): array
    {
        $this->ensureNewsCategory('Загальні');

        return $this->db()->fetchAll(
            "select c.*, count(n.id) as news_count
             from news_categories c
             left join news n on n.category = c.title
             group by c.id, c.title, c.slug, c.sort_order, c.created_at, c.updated_at
             order by c.sort_order asc, c.title asc"
        );
    }

    private function ensureNewsCategory(string $title): void
    {
        $title = trim($title);
        if ($title === '') {
            return;
        }

        if ($this->db()->fetch('select id from news_categories where title = ?', [$title])) {
            return;
        }

        $now = date('c');
        $this->db()->execute(
            'insert into news_categories (title, slug, sort_order, created_at, updated_at) values (?, ?, ?, ?, ?)',
            [$title, $this->uniqueNewsCategorySlug($this->slug($title)), 100, $now, $now]
        );
    }

    private function uniqueNewsCategorySlug(string $slug, int $ignoreId = 0): string
    {
        $slug = $slug ?: 'category';
        $base = $slug;
        $index = 2;

        while (true) {
            $params = [$slug];
            $sql = 'select id from news_categories where slug = ?';
            if ($ignoreId) {
                $sql .= ' and id <> ?';
                $params[] = $ignoreId;
            }

            if (!$this->db()->fetch($sql, $params)) {
                return $slug;
            }

            $slug = $base . '-' . $index;
            $index++;
        }
    }
}
