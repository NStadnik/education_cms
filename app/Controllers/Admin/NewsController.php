<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\Files;
use App\Services\MediaMetadata;
use App\Services\Notifications;
use Throwable;

final class NewsController extends \App\Controllers\AdminBaseController
{
    public function news(Request $request): Response
    {
        $this->guardNewsAccessPermission();
        $query = trim((string) $request->input('q', ''));
        [$where, $params] = $this->newsListFilters($request, $query);
        $sort = $this->newsListOrder((string) $request->input('sort', 'newest'));
        $pagination = $this->pagination($request);
        $items = $this->db()->fetchAll(
            'select n.*, (select u.name from users u where u.id = n.created_by limit 1) as author_name, group_concat(c.title order by c.sort_order asc, c.title asc separator ", ") as category_titles
             from news n
             left join news_category_links l on l.news_id = n.id
             left join news_categories c on c.id = l.category_id
             ' . $where . '
             group by n.id, n.created_by, n.title, n.slug, n.category, n.image_path, n.body, n.status, n.published_at, n.created_at, n.updated_at
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
            return $this->listJson('admin/news/rows', ['items' => $items, 'canModerate' => $this->canReviewNews() || $this->canPublishNews()], $pagination, $total);
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
            'canPublish' => $this->canPublishNews(),
            'canReview' => $this->canReviewNews(),
            'canManage' => Container::get('auth')->can('news.manage'),
            'canManageCategories' => Container::get('auth')->can('news.categories.manage'),
        ]);
    }

    public function newsForm(Request $request): Response
    {
        $this->guardNewsAccessPermission();
        $id = (int) $request->input('id', 0);
        $item = $id ? $this->db()->fetch('select n.*, u.name as author_name from news n left join users u on u.id = n.created_by where n.id = ?', [$id]) : null;
        if ($item && !$this->canAccessNews($item)) {
            return \App\Controllers\ErrorController::response(403);
        }
        return $this->admin('admin/news/form', [
            'title' => 'Новина',
            'item' => $item,
            'categories' => $this->newsCategoryOptions(),
            'selectedCategoryIds' => $id ? $this->newsCategoryIds($id) : [],
            'canReview' => $this->canReviewNews(),
            'canPublish' => $this->canPublishNews(),
            'canEdit' => $item ? $this->canEditNews($item) : Container::get('auth')->can('news.manage'),
            'moderationEvents' => $id ? $this->newsModerationEvents($id) : [],
            'canManageCategories' => Container::get('auth')->can('news.categories.manage'),
            'viewStats' => $id ? $this->newsViewStats($id) : [],
        ]);
    }

    public function newsCategories(Request $request): Response
    {
        $this->guard('news.categories.manage');
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
        $transactionStarted = false;
        $submittedForReview = false;
        try {
            $now = date('c');
            $id = (int) $request->input('id', 0);
            $existing = $id ? $this->db()->fetch('select * from news where id = ?', [$id]) : null;
            if ($id && (!$existing || !$this->canEditNews($existing))) {
                throw new \RuntimeException('Доступ заборонено. Матеріал у цьому статусі не можна редагувати.');
            }
            $title = trim((string) $request->input('title', ''));
            $body = trim((string) $request->input('body', ''));
            if ($title === '' || $body === '') {
                throw new \RuntimeException('Заповніть назву та текст новини.');
            }
            $slugInput = trim((string) $request->input('slug', ''));
            $slug = $this->slug($slugInput !== '' ? $slugInput : $title);
            $status = (string) ($existing['status'] ?? 'draft');
            $publishedAt = $existing['published_at'] ?? null;
            $categoryIds = $this->selectedCategoryIds($request);
            if (!$categoryIds) {
                $categoryIds = [$this->ensureNewsCategory('Загальні')];
            }
            $primaryCategory = $this->categoryTitleById($categoryIds[0]) ?: 'Загальні';
            $imagePath = $this->newsImagePath($request, $id);
            $data = [
                $title,
                $slug,
                $primaryCategory,
                $imagePath,
                $body,
                $status,
                $publishedAt,
                $now,
            ];
            $this->db()->pdo()->beginTransaction();
            $transactionStarted = true;
            if ($id) {
                $version = (int) $request->input('version', 0);
                $affected = $this->db()->executeAffected(
                    'update news set title=?, slug=?, category=?, image_path=?, body=?, status=?, published_at=?, updated_at=?, version=version+1 where id=? and version=?',
                    [...$data, $id, $version]
                );
                if ($version <= 0 || $affected !== 1) {
                    throw new \RuntimeException('Новину вже змінив інший користувач. Оновіть сторінку й повторіть дію.');
                }
            } else {
                $this->db()->execute('insert into news (created_by, title, slug, category, image_path, body, status, published_at, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$this->currentUserId(), ...$data, $now]);
                $id = (int) $this->db()->lastInsertId();
            }
            $this->syncNewsCategories($id, $categoryIds);
            if ($request->input('submit_for_review') && in_array($status, ['draft', 'changes_requested'], true)) {
                $saved = $this->db()->fetch('select * from news where id = ?', [$id]);
                if (!$saved) {
                    throw new \RuntimeException('Не вдалося підготувати новину до модерації.');
                }
                $this->applyNewsTransition($saved, 'submit', '');
                $status = 'pending_review';
                $submittedForReview = true;
            }
            $this->audit('save', 'news', $id);
            $savedVersion = (int) ($this->db()->fetch('select version from news where id = ?', [$id])['version'] ?? 1);
            $this->db()->pdo()->commit();
            $transactionStarted = false;
            if ($submittedForReview) {
                $notificationItem = $this->db()->fetch('select * from news where id=?', [$id]);
                if ($notificationItem) { Notifications::news($notificationItem, 'submit'); }
            }

            if ($this->isAjax($request)) {
                return $this->json([
                    'ok' => true,
                    'message' => 'Новину збережено.',
                    'id' => $id,
                    'version' => $savedVersion,
                    'published_at' => $publishedAt,
                    'moderation_transition' => $submittedForReview ? 'submit' : null,
                    'edit_url' => url('/admin/news/edit?id=' . $id),
                    'redirect_url' => ($existing || $request->input('submit_for_review')) ? url('/admin/news/edit?id=' . $id) : null,
                    'view_url' => $status === 'published' ? url('/news/' . $slug) : null,
                ]);
            }

            redirect('/admin/news');
        } catch (Throwable $e) {
            if ($transactionStarted && $this->db()->pdo()->inTransaction()) {
                $this->db()->pdo()->rollBack();
            }
            return $this->ajaxError($request, $e);
        }
    }

    public function newsSubmit(Request $request): Response
    {
        return $this->moderateNews($request, 'submit');
    }

    public function newsRequestChanges(Request $request): Response
    {
        return $this->moderateNews($request, 'request_changes');
    }

    public function newsPublish(Request $request): Response
    {
        return $this->moderateNews($request, 'publish');
    }

    public function newsUnpublish(Request $request): Response
    {
        return $this->moderateNews($request, 'unpublish');
    }

    public function newsBulk(Request $request): Response
    {
        $this->guardNewsAccessPermission();
        Csrf::verify();
        $action = (string) $request->input('bulk_action', '');
        $ids = $this->bulkIds($request);
        if ($action !== 'publish') {
            $ids = $this->filterStrictOwnedNewsIds($ids);
        }
        if ($ids && $action === 'submit' && Container::get('auth')->can('news.manage')) {
            $ids = $this->filterNewsIdsByStatuses($ids, ['draft', 'changes_requested']);
            foreach ($ids as $id) {
                $item = $this->db()->fetch('select * from news where id = ?', [$id]);
                if ($item) {
                    $this->applyNewsTransition($item, 'submit', '');
                    Notifications::news($item, 'submit');
                }
            }
            $this->audit('bulk_submit', 'news', null, 'ids: ' . implode(',', $ids));
        } elseif ($ids && $action === 'publish' && $this->canPublishNews()) {
            $ids = $this->filterNewsIdsByStatuses($ids, ['pending_review']);
            foreach ($ids as $id) {
                $item = $this->db()->fetch('select * from news where id = ?', [$id]);
                if ($item) {
                    $this->applyNewsTransition($item, 'publish', '');
                    Notifications::news($item, 'publish');
                }
            }
            $this->audit('bulk_publish', 'news', null, 'ids: ' . implode(',', $ids));
        } elseif ($ids && $action === 'delete' && Container::get('auth')->can('news.manage')) {
            $ids = $this->filterNewsIdsByStatuses($ids, ['draft', 'changes_requested']);
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
        $this->guard('news.categories.manage');
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
        $this->guard('news.categories.manage');
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
                    ) as news_count,
                    (
                        select count(*)
                        from news_categories child
                        where child.parent_id = c.id
                    ) as children_count
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

    private function newsImagePath(Request $request, int $newsId): ?string
    {
        $current = '';
        if ($newsId > 0) {
            $row = $this->db()->fetch('select image_path from news where id = ?', [$newsId]);
            $current = Files::normalize((string) ($row['image_path'] ?? ''));
        }

        $this->assertNewsImageUpload($request->files['image'] ?? []);
        $uploaded = Files::upload($request->files['image'] ?? [], [
            'uploaded_by' => (string) $this->currentUserId(),
        ]);
        if (!$uploaded) {
            if ((string) $request->input('remove_image', '') === '1') {
                return null;
            }

            $selected = Files::normalize((string) $request->input('image_path', ''));
            if ($selected !== '') {
                if ($selected !== $current) {
                    $this->assertNewsImagePath($selected);
                }
                return $selected;
            }

            return $current !== '' ? $current : null;
        }

        $extension = strtolower(pathinfo($uploaded, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            Files::delete($uploaded);
            throw new \RuntimeException('Головне зображення має бути JPG, PNG або WebP.');
        }
        return $uploaded;
    }

    private function assertNewsImageUpload(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return;
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new \RuntimeException('Головне зображення має бути JPG, PNG або WebP.');
        }
    }

    private function assertNewsImagePath(string $path): void
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) || !is_file(base_path('storage/uploads/' . $path))) {
            throw new \RuntimeException('Оберіть зображення з медіафайлів.');
        }
        if (!$this->canManageAllContent()) {
            $item = Files::fromMetadata([MediaMetadata::details($path)], $this->mediaReferences())[0] ?? null;
            if (!$item || !$this->canManageMediaItem($item)) {
                throw new \RuntimeException('Оберіть власне зображення з медіафайлів.');
            }
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

        [$ownerWhere, $ownerParams] = $this->ownedContentWhere('n', 'news');
        if ($ownerWhere !== '') {
            $clauses[] = $ownerWhere;
            array_push($params, ...$ownerParams);
        }

        $status = (string) $request->input('status', '');
        if (in_array($status, ['published', 'draft', 'pending_review', 'changes_requested'], true)) {
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
            'popular' => 'n.views_count desc, n.id desc',
            'moderation' => 'n.submitted_at asc, n.id asc',
        ][$sort] ?? 'coalesce(n.published_at, n.created_at) desc, n.id desc';
    }

    private function newsViewStats(int $newsId): array
    {
        $days = $this->db()->fetchAll(
            'select view_date, views_count from news_view_stats
             where news_id = ? and view_date >= date_sub(current_date, interval 29 day)
             order by view_date asc',
            [$newsId]
        );
        $last30Days = array_sum(array_map(static fn (array $day): int => (int) $day['views_count'], $days));
        $today = date('Y-m-d');
        $todayViews = 0;
        foreach ($days as $day) {
            if ((string) $day['view_date'] === $today) { $todayViews = (int) $day['views_count']; }
        }

        return ['days' => $days, 'today' => $todayViews, 'last_30_days' => $last30Days];
    }

    private function canReviewNews(): bool
    {
        return Container::get('auth')->can('news.review');
    }

    private function guardNewsAccessPermission(): void
    {
        $this->guard();
        $auth = Container::get('auth');
        if ($auth->can('news.manage') || $auth->can('news.review') || $auth->can('news.publish')) {
            return;
        }

        \App\Controllers\ErrorController::response(403)->send();
        exit;
    }

    private function canPublishNews(): bool
    {
        return Container::get('auth')->can('news.publish');
    }

    private function canAccessNews(array $item): bool
    {
        return $this->canReviewNews()
            || $this->canPublishNews()
            || $this->canManageAllContent()
            || (int) ($item['created_by'] ?? 0) === $this->currentUserId();
    }

    private function canEditNews(array $item): bool
    {
        if (!$this->canAccessNews($item) || !Container::get('auth')->can('news.manage')) {
            return false;
        }

        $status = (string) ($item['status'] ?? 'draft');
        if ($status === 'published') {
            return $this->canPublishNews();
        }
        if ($status === 'pending_review') {
            return $this->canReviewNews() || $this->canPublishNews();
        }

        return (int) ($item['created_by'] ?? 0) === $this->currentUserId() || $this->canReviewNews() || $this->canManageAllContent();
    }

    private function moderateNews(Request $request, string $action): Response
    {
        $this->guardNewsAccessPermission();
        Csrf::verify();
        $transactionStarted = false;

        try {
            $id = (int) $request->input('id', 0);
            $item = $id ? $this->db()->fetch('select * from news where id = ?', [$id]) : null;
            if (!$item || !$this->canAccessNews($item)) {
                throw new \RuntimeException('Новину не знайдено або доступ заборонено.');
            }

            $version = (int) $request->input('version', 0);
            if ($version <= 0 || $version !== (int) ($item['version'] ?? 1)) {
                throw new \RuntimeException('Новину вже змінив інший користувач. Оновіть сторінку й повторіть дію.');
            }

            $comment = trim((string) $request->input('review_comment', ''));
            if ($action === 'request_changes' && (!$this->canReviewNews() || $comment === '')) {
                throw new \RuntimeException($comment === '' ? 'Додайте коментар із переліком необхідних змін.' : 'Недостатньо прав для модерації.');
            }
            if (in_array($action, ['publish', 'unpublish'], true) && !$this->canPublishNews()) {
                throw new \RuntimeException('Недостатньо прав для публікації.');
            }
            if ($action === 'submit' && !in_array((string) $item['status'], ['draft', 'changes_requested'], true)) {
                throw new \RuntimeException('Цю новину неможливо надіслати на модерацію.');
            }
            if ($action === 'submit' && !Container::get('auth')->can('news.manage')) {
                throw new \RuntimeException('Недостатньо прав для надсилання новини.');
            }

            $publishedAt = trim((string) $request->input('published_at', ''));
            if ($action === 'publish' && $publishedAt !== '' && strtotime($publishedAt) === false) {
                throw new \RuntimeException('Вкажіть коректну дату публікації.');
            }
            if ($action === 'publish' && $publishedAt !== '' && (int) strtotime($publishedAt) > time()) {
                throw new \RuntimeException('Запланована публікація ще не підтримується. Оберіть поточну або минулу дату.');
            }
            if ($action === 'publish' && (trim((string) ($item['title'] ?? '')) === '' || trim((string) ($item['body'] ?? '')) === '')) {
                throw new \RuntimeException('Новина без назви або тексту не може бути опублікована.');
            }

            $this->db()->pdo()->beginTransaction();
            $transactionStarted = true;
            $this->applyNewsTransition($item, $action, $comment, $publishedAt);
            $this->audit($action, 'news', $id, $comment);
            $this->db()->pdo()->commit();
            $transactionStarted = false;
            Notifications::news($item, $action, $comment);

            if ($this->isAjax($request)) {
                return $this->json([
                    'ok' => true,
                    'message' => 'Статус новини оновлено.',
                    'edit_url' => url('/admin/news/edit?id=' . $id),
                    'redirect_url' => url('/admin/news/edit?id=' . $id),
                ]);
            }

            redirect('/admin/news/edit?id=' . $id);
        } catch (Throwable $e) {
            if ($transactionStarted && $this->db()->pdo()->inTransaction()) {
                $this->db()->pdo()->rollBack();
            }
            return $this->ajaxError($request, $e);
        }
    }

    private function applyNewsTransition(array $item, string $action, string $comment, string $publishedAt = ''): void
    {
        $from = (string) ($item['status'] ?? 'draft');
        $allowed = [
            'submit' => ['from' => ['draft', 'changes_requested'], 'to' => 'pending_review'],
            'request_changes' => ['from' => ['pending_review'], 'to' => 'changes_requested'],
            'publish' => ['from' => ['pending_review'], 'to' => 'published'],
            'unpublish' => ['from' => ['published'], 'to' => 'draft'],
        ];
        if (!isset($allowed[$action]) || !in_array($from, $allowed[$action]['from'], true)) {
            throw new \RuntimeException('Недопустимий перехід статусу новини.');
        }

        $to = $allowed[$action]['to'];
        $now = date('c');
        $userId = $this->currentUserId();
        if ($action === 'submit') {
            $sql = 'update news set status=?, submitted_at=?, submitted_by=?, reviewed_at=null, reviewed_by=null, review_comment=null, updated_at=?, version=version+1 where id=? and version=?';
            $params = [$to, $now, $userId, $now, $item['id'], $item['version']];
        } elseif ($action === 'request_changes') {
            $sql = 'update news set status=?, reviewed_at=?, reviewed_by=?, review_comment=?, updated_at=?, version=version+1 where id=? and version=?';
            $params = [$to, $now, $userId, $comment, $now, $item['id'], $item['version']];
        } elseif ($action === 'publish') {
            $publicationDate = $publishedAt !== '' ? date('c', (int) strtotime($publishedAt)) : $now;
            $sql = 'update news set status=?, published_at=?, reviewed_at=?, reviewed_by=?, review_comment=null, updated_at=?, version=version+1 where id=? and version=?';
            $params = [$to, $publicationDate, $now, $userId, $now, $item['id'], $item['version']];
        } else {
            $sql = 'update news set status=?, published_at=null, reviewed_at=?, reviewed_by=?, review_comment=?, updated_at=?, version=version+1 where id=? and version=?';
            $params = [$to, $now, $userId, $comment, $now, $item['id'], $item['version']];
        }

        if ($this->db()->executeAffected($sql, $params) !== 1) {
            throw new \RuntimeException('Новину вже змінив інший користувач. Оновіть сторінку й повторіть дію.');
        }
        $this->db()->execute(
            'insert into news_moderation_events (news_id, user_id, action, from_status, to_status, comment, created_at) values (?, ?, ?, ?, ?, ?, ?)',
            [$item['id'], $userId, $action, $from, $to, $comment !== '' ? $comment : null, $now]
        );
    }

    private function filterNewsIdsByStatuses(array $ids, array $statuses): array
    {
        if (!$ids || !$statuses) {
            return [];
        }
        $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
        $statusPlaceholders = implode(',', array_fill(0, count($statuses), '?'));
        $rows = $this->db()->fetchAll(
            "select id from news where id in ({$idPlaceholders}) and status in ({$statusPlaceholders})",
            [...$ids, ...$statuses]
        );
        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    private function filterStrictOwnedNewsIds(array $ids): array
    {
        if (!$ids || $this->canManageAllContent()) {
            return $ids;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db()->fetchAll(
            "select id from news where id in ({$placeholders}) and created_by = ?",
            [...$ids, $this->currentUserId()]
        );
        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    private function newsModerationEvents(int $newsId): array
    {
        return $this->db()->fetchAll(
            'select e.*, u.name as user_name from news_moderation_events e left join users u on u.id = e.user_id where e.news_id = ? order by e.id desc limit 20',
            [$newsId]
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
