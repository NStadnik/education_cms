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
        [$where, $params] = $this->newsListFilters($request, $query);
        $sort = $this->newsListOrder((string) $request->input('sort', 'newest'));
        $pagination = $this->pagination($request);
        $items = $this->db()->fetchAll(
            'select n.*, group_concat(c.title order by c.sort_order asc, c.title asc separator ", ") as category_titles
             from news n
             left join news_category_links l on l.news_id = n.id
             left join news_categories c on c.id = l.category_id
             ' . $where . '
             group by n.id, n.title, n.slug, n.category, n.body, n.status, n.published_at, n.created_at, n.updated_at
             order by ' . $sort . '
             limit ' . $pagination['limit'] . ' offset ' . $pagination['offset'],
            $params
        );
        $total = (int) ($this->db()->fetch(
            'select count(distinct n.id) as c
             from news n
             left join news_category_links l on l.news_id = n.id
             left join news_categories c on c.id = l.category_id
             ' . $where,
            $params
        )['c'] ?? 0);

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
            'filters' => [
                'q' => $query,
                'status' => (string) $request->input('status', ''),
                'category_id' => (string) $request->input('category_id', ''),
                'sort' => (string) $request->input('sort', 'newest'),
            ],
            'categories' => $this->newsCategoryOptions(),
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
            'selectedCategoryIds' => $id ? $this->newsCategoryIds($id) : [],
        ]);
    }

    public function newsCategories(Request $request): Response
    {
        $this->guard();
        return $this->admin('admin/news/categories', [
            'title' => 'Категорії новин',
            'categories' => $this->newsCategoriesWithCounts(),
            'parentOptions' => $this->newsCategoryOptions(),
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
            $categoryIds = $this->selectedCategoryIds($request);
            if (!$categoryIds) {
                $categoryIds = [$this->ensureNewsCategory('Загальні')];
            }
            $primaryCategory = $this->categoryTitleById($categoryIds[0]) ?: 'Загальні';
            $data = [
                $request->input('title'),
                $slug,
                $primaryCategory,
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
            $this->syncNewsCategories($id, $categoryIds);
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
            $parentId = (int) $request->input('parent_id', 0);
            if ($parentId && (!$this->db()->fetch('select id from news_categories where id = ?', [$parentId]) || $parentId === $id || $this->isCategoryDescendant($parentId, $id))) {
                return $this->json(['ok' => false, 'message' => 'Оберіть іншу батьківську категорію.'], 422);
            }
            $slug = $this->uniqueNewsCategorySlug($this->slug($title), $id);
            $existing = $id ? $this->db()->fetch('select * from news_categories where id = ?', [$id]) : null;

            if ($id && $existing) {
                $this->db()->execute(
                    'update news_categories set parent_id=?, title=?, slug=?, sort_order=?, updated_at=? where id=?',
                    [$parentId ?: null, $title, $slug, $sortOrder, $now, $id]
                );
                if (($existing['title'] ?? '') !== $title) {
                    $this->db()->execute('update news set category=? where category=?', [$title, $existing['title']]);
                }
            } else {
                $this->db()->execute(
                    'insert into news_categories (parent_id, title, slug, sort_order, created_at, updated_at) values (?, ?, ?, ?, ?, ?)',
                    [$parentId ?: null, $title, $slug, $sortOrder, $now, $now]
                );
                $id = (int) $this->db()->lastInsertId();
            }

            $this->audit('save', 'news_category', $id);

            if ($this->isAjax($request)) {
                return $this->json([
                    'ok' => true,
                    'message' => 'Категорію збережено.',
                    'id' => $id,
                    'html' => $this->newsCategoriesRowsHtml(),
                    'options_html' => $this->newsCategoryParentOptionsHtml(),
                    'reset' => !$request->input('id'),
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

            $newsCount = (int) ($this->db()->fetch(
                'select count(distinct n.id) as c
                 from news n
                 left join news_category_links l on l.news_id = n.id
                 where l.category_id = ? or n.category = ?',
                [$id, $category['title']]
            )['c'] ?? 0);
            if ($newsCount > 0) {
                return $this->json(['ok' => false, 'message' => 'Категорія має новини, тому її не можна видалити.'], 422);
            }
            $childrenCount = (int) ($this->db()->fetch('select count(*) as c from news_categories where parent_id = ?', [$id])['c'] ?? 0);
            if ($childrenCount > 0) {
                return $this->json(['ok' => false, 'message' => 'Категорія має підкатегорії, тому її не можна видалити.'], 422);
            }

            $this->db()->execute('delete from news_categories where id = ?', [$id]);
            $this->audit('delete', 'news_category', $id);

            if ($this->isAjax($request)) {
                return $this->json([
                    'ok' => true,
                    'message' => 'Категорію видалено.',
                    'html' => $this->newsCategoriesRowsHtml(),
                    'options_html' => $this->newsCategoryParentOptionsHtml(),
                ]);
            }

            redirect('/admin/news/categories');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
        }
    }

    private function newsCategoryOptions(): array
    {
        $this->ensureNewsCategory('Загальні');
        $categories = $this->orderedNewsCategories(
            $this->db()->fetchAll('select id, parent_id, title as category, sort_order from news_categories order by sort_order asc, title asc')
        );
        if ($categories) {
            return $categories;
        }

        return $this->db()->fetchAll("select null as id, category from news where category is not null and category <> '' group by category order by category asc");
    }

    private function newsCategoriesWithCounts(): array
    {
        $this->ensureNewsCategory('Загальні');

        return $this->orderedNewsCategories($this->db()->fetchAll(
            "select c.*,
                    (
                        select count(distinct n.id)
                        from news n
                        left join news_category_links l on l.news_id = n.id
                        where l.category_id = c.id or n.category = c.title
                    ) as news_count
             from news_categories c
             order by c.sort_order asc, c.title asc"
        ));
    }

    private function newsCategoriesRowsHtml(): string
    {
        return $this->view()->partial('admin/news/category-rows', [
            'categories' => $this->newsCategoriesWithCounts(),
            'parentOptions' => $this->newsCategoryOptions(),
        ]);
    }

    private function newsCategoryParentOptionsHtml(): string
    {
        return $this->view()->partial('admin/news/category-parent-options', [
            'parentOptions' => $this->newsCategoryOptions(),
        ]);
    }

    private function ensureNewsCategory(string $title): int
    {
        $title = trim($title);
        if ($title === '') {
            return 0;
        }

        $existing = $this->db()->fetch('select id from news_categories where title = ?', [$title]);
        if ($existing) {
            return (int) $existing['id'];
        }

        $now = date('c');
        $this->db()->execute(
            'insert into news_categories (parent_id, title, slug, sort_order, created_at, updated_at) values (?, ?, ?, ?, ?, ?)',
            [null, $title, $this->uniqueNewsCategorySlug($this->slug($title)), 100, $now, $now]
        );
        return (int) $this->db()->lastInsertId();
    }

    private function orderedNewsCategories(array $categories, ?int $parentId = null, int $depth = 0): array
    {
        $ordered = [];
        foreach ($categories as $category) {
            $categoryParentId = isset($category['parent_id']) ? (int) $category['parent_id'] : 0;
            if (($parentId === null && $categoryParentId !== 0) || ($parentId !== null && $categoryParentId !== $parentId)) {
                continue;
            }

            $category['depth'] = $depth;
            $category['label'] = str_repeat('— ', $depth) . ($category['title'] ?? $category['category']);
            $ordered[] = $category;
            array_push($ordered, ...$this->orderedNewsCategories($categories, (int) $category['id'], $depth + 1));
        }

        return $ordered;
    }

    private function isCategoryDescendant(int $candidateId, int $categoryId): bool
    {
        if (!$categoryId) {
            return false;
        }

        $current = $this->db()->fetch('select parent_id from news_categories where id = ?', [$candidateId]);
        while ($current && !empty($current['parent_id'])) {
            $parentId = (int) $current['parent_id'];
            if ($parentId === $categoryId) {
                return true;
            }
            $current = $this->db()->fetch('select parent_id from news_categories where id = ?', [$parentId]);
        }

        return false;
    }

    private function selectedCategoryIds(Request $request): array
    {
        $raw = $request->input('category_ids', []);
        if (!is_array($raw)) {
            $raw = [$raw];
        }

        $ids = [];
        foreach ($raw as $id) {
            $id = (int) $id;
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db()->fetchAll(
            'select id from news_categories where id in (' . $placeholders . ') order by sort_order asc, title asc',
            $ids
        );
        $validIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);

        return $validIds;
    }

    private function newsCategoryIds(int $newsId): array
    {
        $rows = $this->db()->fetchAll('select category_id from news_category_links where news_id = ? order by category_id asc', [$newsId]);
        return array_map(static fn (array $row): int => (int) $row['category_id'], $rows);
    }

    private function categoryTitleById(int $id): string
    {
        $row = $this->db()->fetch('select title from news_categories where id = ?', [$id]);
        return (string) ($row['title'] ?? '');
    }

    private function syncNewsCategories(int $newsId, array $categoryIds): void
    {
        $this->db()->execute('delete from news_category_links where news_id = ?', [$newsId]);
        foreach ($categoryIds as $categoryId) {
            $this->db()->execute('insert into news_category_links (news_id, category_id) values (?, ?)', [$newsId, $categoryId]);
        }
    }

    private function newsListFilters(Request $request, string $query): array
    {
        $clauses = [];
        $params = [];

        if ($query !== '') {
            $like = '%' . $query . '%';
            $clauses[] = '(n.title like ? or n.slug like ? or n.category like ? or n.body like ? or n.status like ? or exists (
                select 1
                from news_category_links sl
                join news_categories sc on sc.id = sl.category_id
                where sl.news_id = n.id and sc.title like ?
            ))';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        $status = (string) $request->input('status', '');
        if (in_array($status, ['published', 'draft'], true)) {
            $clauses[] = 'n.status = ?';
            $params[] = $status;
        }

        $categoryId = (int) $request->input('category_id', 0);
        if ($categoryId > 0) {
            $clauses[] = 'exists (
                select 1
                from news_category_links fl
                where fl.news_id = n.id and fl.category_id = ?
            )';
            $params[] = $categoryId;
        }

        return [$clauses ? 'where ' . implode(' and ', $clauses) : '', $params];
    }

    private function newsListOrder(string $sort): string
    {
        return [
            'newest' => 'coalesce(n.published_at, n.created_at) desc, n.id desc',
            'oldest' => 'coalesce(n.published_at, n.created_at) asc, n.id asc',
            'title_asc' => 'n.title asc, n.id desc',
            'title_desc' => 'n.title desc, n.id desc',
            'updated_desc' => 'n.updated_at desc, n.id desc',
            'created_desc' => 'n.created_at desc, n.id desc',
        ][$sort] ?? 'coalesce(n.published_at, n.created_at) desc, n.id desc';
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
