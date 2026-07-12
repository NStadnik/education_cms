<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\Files;
use App\Services\MediaMetadata;
use App\Services\SiteThemes;
use Throwable;

abstract class AdminBaseController extends BaseController
{
    protected const LIST_LIMIT = 20;
    protected const IMPORT_TITLE_LIMIT = 220;
    protected const WP_CONTENT_MEDIA_LIMIT = 20;
    protected const WP_CONTENT_MEDIA_SECONDS = 20;
    protected const WP_MEDIA_BATCH_SECONDS = 15;
    protected const IMPORT_DOWNLOAD_TIMEOUT = 8;

    protected array $lastImportStats = [];
    protected array $lastWordPressMediaPaths = [];
    protected ?array $importedMediaPathIndex = null;

    protected function guard(?string $permission = null): void
    {
        Container::get('auth')->require();
        if ($permission && !Container::get('auth')->can($permission)) {
            if ($this->isAjaxRequest()) {
                (new Response(json_encode(['ok' => false, 'message' => 'Доступ заборонено.'], JSON_UNESCAPED_UNICODE), 403, [
                    'Content-Type' => 'application/json; charset=UTF-8',
                ]))->send();
                exit;
            }

            ErrorController::response(403)->send();
            exit;
        }
    }

    protected function admin(string $template, array $data): Response
    {
        return $this->render($template, array_replace($data, ['user' => Container::get('auth')->user()]), 'layouts/admin');
    }

    protected function count(string $table): int
    {
        return (int) ($this->db()->fetch("select count(*) as c from {$table}")['c'] ?? 0);
    }

    protected function pagination(Request $request): array
    {
        $limit = (int) $request->input('limit', self::LIST_LIMIT);
        if ($limit <= 0 || $limit > 100) {
            $limit = self::LIST_LIMIT;
        }

        $offset = max(0, (int) $request->input('offset', 0));
        return ['limit' => $limit, 'offset' => $offset];
    }

    protected function listJson(string $template, array $data, array $pagination, int $total): Response
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

    protected function adminListPayload(string $resource, Request $request, string $message = ''): array
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
            return array_replace($this->mediaListPayload($query, $pagination, (string) $request->input('folder', '')), ['message' => $message]);
        }

        $map = [
            'pages' => ['table' => 'pages', 'template' => 'admin/pages/rows', 'columns' => ['title', 'slug', 'excerpt', 'status'], 'order' => 'sort_order asc, id desc'],
            'news' => ['table' => 'news', 'template' => 'admin/news/rows', 'columns' => ['title', 'category', 'body', 'status', 'published_at'], 'order' => 'id desc'],
            'users' => ['table' => 'users', 'template' => 'admin/users/rows', 'columns' => ['name', 'email', 'role'], 'order' => 'id desc'],
        ];
        if (!isset($map[$resource])) {
            return ['ok' => true, 'message' => $message];
        }

        $config = $map[$resource];
        [$where, $params] = $this->searchWhere($query, $config['columns']);
        $order = $config['order'];
        if ($resource === 'pages') {
            $clauses = [];
            $params = [];
            if ($query !== '') {
                $clauses[] = '(title like ? or slug like ? or excerpt like ? or status like ?)';
                array_push($params, '%' . $query . '%', '%' . $query . '%', '%' . $query . '%', '%' . $query . '%');
            }
            [$ownerWhere, $ownerParams] = $this->ownedContentWhere();
            if ($ownerWhere !== '') {
                $clauses[] = $ownerWhere;
                array_push($params, ...$ownerParams);
            }
            $status = (string) $request->input('status', '');
            if (in_array($status, ['published', 'draft'], true)) {
                $clauses[] = 'status = ?';
                $params[] = $status;
            }
            $template = (string) $request->input('template', '');
            if ($template !== '' && array_key_exists($template, $this->pageTemplates())) {
                $clauses[] = 'template = ?';
                $params[] = $template;
            }
            $where = $clauses ? 'where ' . implode(' and ', $clauses) : '';
            $order = [
                'order_asc' => 'sort_order asc, id desc',
                'order_desc' => 'sort_order desc, id desc',
                'title_asc' => 'title asc, id desc',
                'title_desc' => 'title desc, id desc',
                'updated_desc' => 'updated_at desc, id desc',
                'created_desc' => 'created_at desc, id desc',
            ][(string) $request->input('sort', 'order_asc')] ?? $config['order'];
        }
        if ($resource === 'news') {
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
            $newsWhere = $clauses ? 'where ' . implode(' and ', $clauses) : '';
            $newsOrder = [
                'newest' => 'coalesce(n.published_at, n.created_at) desc, n.id desc',
                'oldest' => 'coalesce(n.published_at, n.created_at) asc, n.id asc',
                'title_asc' => 'n.title asc, n.id desc',
                'title_desc' => 'n.title desc, n.id desc',
                'updated_desc' => 'n.updated_at desc, n.id desc',
                'created_desc' => 'n.created_at desc, n.id desc',
                'popular' => 'n.views_count desc, n.id desc',
                'moderation' => 'n.submitted_at asc, n.id asc',
            ][(string) $request->input('sort', 'newest')] ?? $config['order'];
            $items = $this->db()->fetchAll(
                'select n.*, (select u.name from users u where u.id = n.created_by limit 1) as author_name, group_concat(c.title order by c.sort_order asc, c.title asc separator ", ") as category_titles
                 from news n
                 left join news_category_links l on l.news_id = n.id
                 left join news_categories c on c.id = l.category_id
                 ' . $newsWhere . '
                 group by n.id, n.created_by, n.title, n.slug, n.category, n.image_path, n.body, n.status, n.published_at, n.created_at, n.updated_at
                 order by ' . $newsOrder . '
                 limit ' . $pagination['limit'] . ' offset 0',
                $params
            );
            $total = (int) ($this->db()->fetch(
                'select count(distinct n.id) as c
                 from news n
                 left join news_category_links l on l.news_id = n.id
                 left join news_categories c on c.id = l.category_id
                 ' . $newsWhere,
                $params
            )['c'] ?? 0);
        } else {
            $items = $this->db()->fetchAll(
                'select * from ' . $config['table'] . ' ' . $where . ' order by ' . $order . ' limit ' . $pagination['limit'] . ' offset 0',
                $params
            );
            $total = (int) ($this->db()->fetch('select count(*) as c from ' . $config['table'] . ' ' . $where, $params)['c'] ?? 0);
        }
        $loaded = count($items);

        return [
            'ok' => true,
            'html' => $this->view()->partial($config['template'], [
                'items' => $items,
                'roleLabels' => $resource === 'users' ? Container::get('auth')->roleLabels() : [],
                'canModerate' => $resource === 'news' && (Container::get('auth')->can('news.review') || Container::get('auth')->can('news.publish')),
            ]),
            'total' => $total,
            'next_offset' => $loaded,
            'has_more' => $loaded < $total,
            'message' => $message,
            'stats' => $this->adminListStats($resource),
        ];
    }

    protected function adminListStats(string $resource): array
    {
        if (in_array($resource, ['pages', 'news'], true)) {
            return $this->statusStats($resource);
        }
        if ($resource === 'users') {
            return ['total' => $this->count('users')];
        }

        return [];
    }

    protected function searchWhere(string $query, array $columns): array
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

    protected function statusStats(string $table): array
    {
        $where = '';
        $params = [];
        if (in_array($table, ['pages', 'news'], true)) {
            [$ownerWhere, $ownerParams] = $this->ownedContentWhere('', $table);
            if ($ownerWhere !== '') {
                $where = ' where ' . $ownerWhere;
                $params = $ownerParams;
            }
        }

        $total = (int) ($this->db()->fetch("select count(*) as c from {$table}" . $where, $params)['c'] ?? 0);
        $publishedWhere = $where === '' ? " where status = 'published'" : $where . " and status = 'published'";
        $published = (int) ($this->db()->fetch("select count(*) as c from {$table}" . $publishedWhere, $params)['c'] ?? 0);
        $countStatus = function (string $status) use ($table, $where, $params): int {
            $statusWhere = $where === '' ? ' where status = ?' : $where . ' and status = ?';
            return (int) ($this->db()->fetch("select count(*) as c from {$table}" . $statusWhere, [...$params, $status])['c'] ?? 0);
        };
        $pending = $table === 'news' ? $countStatus('pending_review') : 0;
        $changes = $table === 'news' ? $countStatus('changes_requested') : 0;
        $drafts = $countStatus('draft');
        return ['total' => $total, 'published' => $published, 'drafts' => $drafts, 'pending_review' => $pending, 'changes_requested' => $changes];
    }

    protected function pageTemplates(): array
    {
        return [
            'default' => 'Стандартний',
            'wide' => 'Широкий контент',
        ];
    }

    protected function siteTemplates(): array
    {
        return SiteThemes::all();
    }

    protected function bulkIds(Request $request): array
    {
        return $this->bulkIdsFrom($request, 'ids');
    }

    protected function bulkIdsFrom(Request $request, string $name): array
    {
        $ids = $request->input($name, []);
        if (!is_array($ids)) {
            return [];
        }

        $ids = array_map(static fn ($id): int => (int) $id, $ids);
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        return array_slice($ids, 0, 200);
    }

    protected function bulkUpdateStatus(string $table, array $ids, string $status): void
    {
        $allowedTables = ['pages', 'news'];
        if (!in_array($table, $allowedTables, true) || !$ids) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->db()->execute(
            "update {$table} set status = ?, updated_at = ? where id in ({$placeholders})",
            [$status, date('c'), ...$ids]
        );
    }

    protected function bulkFillPublishedAt(string $table, array $ids): void
    {
        $allowedTables = ['news'];
        if (!in_array($table, $allowedTables, true) || !$ids) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->db()->execute(
            "update {$table} set published_at = ? where id in ({$placeholders}) and (published_at is null or published_at = '')",
            [date('c'), ...$ids]
        );
    }

    protected function bulkUpdateUsers(array $ids, int $isActive): void
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

    protected function bulkDelete(string $table, array $ids): void
    {
        $allowedTables = ['pages', 'news', 'users'];
        if (!in_array($table, $allowedTables, true) || !$ids) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->db()->execute("delete from {$table} where id in ({$placeholders})", $ids);
    }

    protected function currentUserId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    protected function canManageAllContent(): bool
    {
        return Container::get('auth')->can('content.manage_all');
    }

    protected function ownedContentWhere(string $alias = '', string $resource = ''): array
    {
        if ($this->canManageAllContent() || ($resource === 'news' && (Container::get('auth')->can('news.review') || Container::get('auth')->can('news.publish')))) {
            return ['', []];
        }

        $column = ($alias !== '' ? $alias . '.' : '') . 'created_by';
        return [$column . ' = ?', [$this->currentUserId()]];
    }

    protected function filterOwnedContentIds(string $table, array $ids): array
    {
        if ($this->canManageAllContent() || ($table === 'news' && (Container::get('auth')->can('news.review') || Container::get('auth')->can('news.publish'))) || !$ids || !in_array($table, ['pages', 'news'], true)) {
            return $ids;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db()->fetchAll(
            "select id from {$table} where id in ({$placeholders}) and created_by = ?",
            [...$ids, $this->currentUserId()]
        );

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    protected function importOptions(): array
    {
        return [
            'news' => [
                'name' => 'Новини',
                'description' => 'Створює новини з назвою, текстом, статусом і датою публікації.',
                'columns' => 'title, body, slug, status, published_at, image_path',
            ],
            'pages' => [
                'name' => 'Сторінки',
                'description' => 'Створює сторінки з текстовим блоком або HTML-контентом.',
                'columns' => 'title, body, slug, excerpt, template, status, sort_order',
            ],
            'global_fields' => [
                'name' => 'Глобальні поля',
                'description' => 'Додає універсальні поля для футера та загальних даних сайту.',
                'columns' => 'label, value',
            ],
            'wordpress' => [
                'name' => 'WordPress',
                'description' => 'Імпортує WordPress дописи, сторінки, рубрики, медіа, головні зображення та builder-контент.',
                'columns' => 'post_title, post_content, post_name, post_type, post_status, post_date, _thumbnail_id, _elementor_data, nimble, categories',
            ],
        ];
    }

    protected function saveSetting(string $name, string $value): void
    {
        $this->db()->execute(
            'insert into settings (name, value) values (?, ?) on duplicate key update value = values(value)',
            [$name, $value]
        );
    }

    protected function globalFields(): array
    {
        $settings = $this->siteSettings();
        $fields = json_decode((string) ($settings['global_fields'] ?? '[]'), true);
        return is_array($fields) ? $fields : [];
    }

    protected function normalizeGlobalFields(Request $request): array
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

    protected function readImportRows(Request $request, bool $preview = false): array
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

    protected function readDatabaseImportRows(Request $request, bool $preview): array
    {
        $profile = (string) $request->input('db_profile', 'wordpress');
        if ($profile !== 'wordpress') {
            throw new \RuntimeException('Поки підтримується тільки профіль WordPress.');
        }

        return $this->readWordPressRows($request, $preview);
    }

    protected function readWordPressRows(Request $request, bool $preview): array
    {
        $pdo = $this->externalImportPdo($request);
        $prefix = $this->sanitizeDbIdentifier((string) $request->input('db_prefix', 'wp_'));
        $table = $prefix . 'posts';
        $limit = $preview ? 10 : max(1, min(1000, (int) $request->input('db_limit', 200)));
        $offset = $preview ? 0 : max(0, (int) $request->input('db_offset', 0));
        [$where, $params] = $this->wordPressPostsWhere($request);

        $countStmt = $pdo->prepare("select count(*) as c from {$table} p where {$where}");
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch(\PDO::FETCH_ASSOC)['c'] ?? 0);

        $mediaMap = [];
        $mediaPaths = [];
        if (!$preview && $this->shouldImportWordPressMedia($request) && !$request->input('wp_media_replace_only')) {
            $mediaMap = $this->importWordPressMedia($pdo, $prefix, $request, !$request->input('wp_media_replace_only'));
            $mediaPaths = $this->lastWordPressMediaPaths;
        }

        $stmt = $pdo->prepare(
            "select p.ID, p.post_title, p.post_name, p.post_content, p.post_excerpt, p.post_type, p.post_status, p.post_date,
                    pm.meta_value as thumbnail_id
             from {$table} p
             left join {$prefix}postmeta pm on pm.post_id = p.ID and pm.meta_key = '_thumbnail_id'
             where {$where}
             order by p.post_date desc, p.ID desc
             limit {$limit} offset {$offset}"
        );
        $stmt->execute($params);

        $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $thumbnailIds = array_values(array_unique(array_filter(array_map(static fn (array $row): int => (int) ($row['thumbnail_id'] ?? 0), $posts))));
        $missingThumbnailIds = array_values(array_diff($thumbnailIds, array_keys($mediaPaths)));
        if (!$preview && $missingThumbnailIds) {
            $mediaPaths += $this->wordPressMediaPathsForAttachments(
                $pdo,
                $prefix,
                $missingThumbnailIds,
                $request,
                $this->shouldImportWordPressMedia($request) && !$request->input('wp_media_replace_only')
            );
        }
        $wpCategories = $this->wordPressCategoriesForPosts($pdo, $prefix, array_map(static fn (array $row): int => (int) $row['ID'], $posts));
        $builderMeta = $this->wordPressBuilderMeta($pdo, $prefix, array_map(static fn (array $row): int => (int) $row['ID'], $posts));
        $contentMediaRemaining = $this->wordPressContentMediaLimit($request);
        $contentMediaDeadline = microtime(true) + $this->wordPressContentMediaSeconds($request);

        $rows = [];
        foreach ($posts as $row) {
            $status = (($row['post_status'] ?? '') === 'publish') ? 'published' : 'draft';
            $body = (string) ($row['post_content'] ?? '');
            if (($row['post_type'] ?? '') === 'page') {
                $builderBody = $this->wordPressBuilderContent($builderMeta[(int) ($row['ID'] ?? 0)] ?? []);
                if ($builderBody !== '') {
                    $body = trim($body) !== '' ? trim($body) . "\n\n" . $builderBody : $builderBody;
                }
            }
            if ($mediaMap) {
                $body = str_replace(array_keys($mediaMap), array_values($mediaMap), $body);
            }
            if (!$preview && $body !== '' && $this->shouldImportWordPressMedia($request)) {
                $body = $this->replaceWordPressContentMediaUrls($body, $request, $contentMediaRemaining, $contentMediaDeadline);
            }
            $body = $this->normalizeImportedContent($body);
            $categories = $wpCategories[(int) ($row['ID'] ?? 0)] ?? [];
            $categoryNames = array_map(static fn (array $category): string => (string) $category['title'], $categories);
            $thumbnailId = (int) ($row['thumbnail_id'] ?? 0);

            $rows[] = [
                '_import_target' => (($row['post_type'] ?? '') === 'page') ? 'pages' : 'news',
                '_wp_categories' => $categories,
                '_wp_legacy_slug' => $this->limitSlug($this->slug((string) ($row['post_name'] ?? '')), 180),
                'image_path' => $thumbnailId > 0 ? (string) ($mediaPaths[$thumbnailId] ?? '') : '',
                'title' => (string) ($row['post_title'] ?? ''),
                'slug' => $this->wordPressSlug((string) ($row['post_name'] ?? '')),
                'body' => $body,
                'excerpt' => (string) ($row['post_excerpt'] ?? ''),
                'status' => $status,
                'published_at' => (string) ($row['post_date'] ?? ''),
                'category' => implode(', ', $categoryNames),
                'sort_order' => '0',
            ];
        }

        $this->lastImportStats = array_replace($this->lastImportStats, [
            'posts_total' => $total,
            'posts_offset' => $offset,
            'posts_limit' => $limit,
            'posts_loaded' => count($rows),
            'posts_next_offset' => $offset + count($rows),
            'posts_has_more' => ($offset + count($rows)) < $total,
        ]);

        return $rows;
    }

    protected function wordPressBuilderMeta(\PDO $pdo, string $prefix, array $postIds): array
    {
        $postIds = array_values(array_unique(array_filter(array_map('intval', $postIds))));
        if (!$postIds) {
            return [];
        }

        $metaTable = $prefix . 'postmeta';
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $pdo->prepare(
            "select post_id, meta_key, meta_value
             from {$metaTable}
             where post_id in ({$placeholders})
               and (meta_key = '_elementor_data' or meta_key like '%nimble%')"
        );
        $stmt->execute($postIds);

        $meta = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $postId = (int) ($row['post_id'] ?? 0);
            $key = (string) ($row['meta_key'] ?? '');
            if (!$postId || $key === '') {
                continue;
            }
            $meta[$postId][$key] = (string) ($row['meta_value'] ?? '');
        }

        return $meta;
    }

    protected function normalizeImportedContent(string $html): string
    {
        $html = trim($html);
        if ($html === '' || !$this->looksLikeWordPressContent($html)) {
            return $html;
        }

        $html = preg_replace('/<!--\s*\/?wp:[\s\S]*?-->/u', '', $html) ?? $html;
        $html = preg_replace('/(?:\xC2\xA0|&nbsp;|&#160;)+/i', ' ', $html) ?? $html;

        if (!class_exists(\DOMDocument::class)) {
            return $this->normalizeImportedContentFallback($html);
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="import-content-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return trim($html);
        }

        $root = $dom->getElementById('import-content-root');
        if (!$root) {
            return trim($html);
        }

        $this->normalizeWordPressGalleries($dom, $root);
        $this->normalizeWordPressImageFigures($dom, $root);
        $this->normalizeWordPressBulletParagraphs($dom, $root);
        $this->normalizeImportedClasses($root);

        return $this->domInnerHtml($root);
    }

    protected function looksLikeWordPressContent(string $html): bool
    {
        return str_contains($html, '<!-- wp:')
            || str_contains($html, 'wp-block-')
            || str_contains($html, 'wp-image-')
            || preg_match('/<p\b[^>]*>\s*(?:&middot;|&#183;|·|•)\s*/iu', $html) === 1;
    }

    protected function normalizeImportedContentFallback(string $html): string
    {
        $html = preg_replace('/\sclass=(["\'])(?=[^"\']*\bwp-)[^"\']*\1/i', '', $html) ?? $html;
        $html = preg_replace('/<figure\b[^>]*class=(["\'])[^"\']*\bwp-block-gallery\b[^"\']*\1[^>]*>/i', '<div class="rich-gallery rich-gallery-cols-3">', $html) ?? $html;
        $html = preg_replace('/<\/figure>\s*$/i', '</div>', trim($html)) ?? $html;
        return trim($html);
    }

    protected function normalizeWordPressGalleries(\DOMDocument $dom, \DOMElement $root): void
    {
        $xpath = new \DOMXPath($dom);
        $galleries = iterator_to_array($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " wp-block-gallery ")]', $root) ?: []);
        foreach ($galleries as $gallery) {
            if (!$gallery instanceof \DOMElement || !$gallery->parentNode) {
                continue;
            }

            $images = iterator_to_array($xpath->query('.//img', $gallery) ?: []);
            if (!$images) {
                continue;
            }

            $container = $dom->createElement('div');
            $container->setAttribute('class', 'rich-gallery rich-gallery-cols-' . $this->importedGalleryColumns($gallery, count($images)));
            foreach ($images as $image) {
                if ($image instanceof \DOMElement) {
                    $container->appendChild($this->importedImageFigure($dom, $image));
                }
            }

            $gallery->parentNode->replaceChild($container, $gallery);
        }
    }

    protected function importedGalleryColumns(\DOMElement $gallery, int $imageCount): int
    {
        $classes = ' ' . $gallery->getAttribute('class') . ' ';
        if (preg_match('/\bcolumns-([2-4])\b/', $classes, $match) === 1) {
            return (int) $match[1];
        }

        if ($imageCount <= 2) {
            return 2;
        }

        return $imageCount === 4 ? 4 : 3;
    }

    protected function normalizeWordPressImageFigures(\DOMDocument $dom, \DOMElement $root): void
    {
        $xpath = new \DOMXPath($dom);
        $figures = iterator_to_array($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " wp-block-image ")]', $root) ?: []);
        foreach ($figures as $figure) {
            if (!$figure instanceof \DOMElement) {
                continue;
            }

            $figure->setAttribute('class', $this->mergeImportedClasses($figure->getAttribute('class'), ['rich-media-block']));
            $image = $xpath->query('.//img', $figure)->item(0);
            if ($image instanceof \DOMElement) {
                $image->removeAttribute('class');
                $this->normalizeImportedImageLink($dom, $image);
            }
        }
    }

    protected function importedImageFigure(\DOMDocument $dom, \DOMElement $sourceImage): \DOMElement
    {
        $figure = $dom->createElement('figure');
        $href = $this->importedImageHref($sourceImage);
        $image = $dom->createElement('img');
        foreach (['src', 'alt', 'width', 'height'] as $attribute) {
            if ($sourceImage->hasAttribute($attribute)) {
                $image->setAttribute($attribute, $sourceImage->getAttribute($attribute));
            }
        }
        if ($href !== '') {
            $image->setAttribute('src', $this->importedImagePreviewSrc($href));
        }
        if (!$image->hasAttribute('alt')) {
            $image->setAttribute('alt', '');
        }
        if ($href !== '') {
            $link = $dom->createElement('a');
            $link->setAttribute('href', $href);
            $link->appendChild($image);
            $figure->appendChild($link);
        } else {
            $figure->appendChild($image);
        }
        return $figure;
    }

    protected function normalizeImportedImageLink(\DOMDocument $dom, \DOMElement $image): void
    {
        $href = $this->importedImageHref($image);
        if ($href === '') {
            return;
        }

        $image->setAttribute('src', $this->importedImagePreviewSrc($href));
        $parent = $image->parentNode;
        if ($parent instanceof \DOMElement && strtolower($parent->tagName) === 'a') {
            $parent->setAttribute('href', $href);
            return;
        }

        $link = $dom->createElement('a');
        $link->setAttribute('href', $href);
        $parent?->insertBefore($link, $image);
        $link->appendChild($image);
    }

    protected function importedImageHref(\DOMElement $image): string
    {
        $src = trim($image->getAttribute('src'));
        $parent = $image->parentNode;
        $href = '';
        if ($parent instanceof \DOMElement && strtolower($parent->tagName) === 'a') {
            $href = trim($parent->getAttribute('href'));
        }

        if ($src !== '' && $this->importedImageUrlIsUsable($src)) {
            return $src;
        }

        if ($href !== '' && $this->importedImageUrlIsUsable($href)) {
            return $href;
        }

        return $src !== '' ? $src : $href;
    }

    protected function importedImageUrlIsUsable(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $url;
        }

        return preg_match('/\.(?:jpe?g|png|webp)$/i', $path) === 1;
    }

    protected function importedImagePreviewSrc(string $url): string
    {
        if (!str_starts_with($url, '/uploads/')) {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return $url;
        }

        $marker = '/uploads/';
        if (!str_starts_with($path, $marker)) {
            return $url;
        }

        $relative = Files::normalize(rawurldecode(substr($path, strlen($marker))));
        if ($relative === '') {
            return $url;
        }

        return url('/thumb/' . $relative . '?w=960&h=720&fit=contain');
    }

    protected function normalizeWordPressBulletParagraphs(\DOMDocument $dom, \DOMElement $root): void
    {
        $this->normalizeWordPressBulletParagraphsInNode($dom, $root);
    }

    protected function normalizeWordPressBulletParagraphsInNode(\DOMDocument $dom, \DOMNode $parent): void
    {
        $children = iterator_to_array($parent->childNodes);
        $list = null;
        foreach ($children as $child) {
            if (!$child instanceof \DOMElement) {
                if ($child->nodeType !== XML_TEXT_NODE || trim((string) $child->nodeValue) !== '') {
                    $list = null;
                }
                continue;
            }

            $this->normalizeWordPressBulletParagraphsInNode($dom, $child);
            if (strtolower($child->tagName) !== 'p') {
                $list = null;
                continue;
            }

            $html = $this->domInnerHtml($child);
            $stripped = preg_replace('/^\s*(?:&middot;|&#183;|·|•)\s*/iu', '', $html);
            if ($stripped === null || $stripped === $html) {
                $list = null;
                continue;
            }

            if (!$list || !$list->parentNode) {
                $list = $dom->createElement('ul');
                $parent->insertBefore($list, $child);
            }

            $li = $dom->createElement('li');
            foreach (iterator_to_array($child->childNodes) as $paragraphChild) {
                $li->appendChild($paragraphChild->cloneNode(true));
            }
            $this->stripLeadingBulletMarker($li);
            $list->appendChild($li);
            $parent->removeChild($child);
        }
    }

    protected function stripLeadingBulletMarker(\DOMNode $node): bool
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text = (string) $child->nodeValue;
                if (trim($text) === '') {
                    $child->nodeValue = '';
                    continue;
                }

                $clean = preg_replace('/^\s*(?:·|•)\s*/u', '', $text, 1, $count);
                if ($count > 0) {
                    $child->nodeValue = $clean ?? $text;
                    return true;
                }

                return false;
            }

            if ($child instanceof \DOMElement && $this->stripLeadingBulletMarker($child)) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeImportedClasses(\DOMElement $root): void
    {
        $nodes = [$root];
        foreach ($root->getElementsByTagName('*') as $node) {
            $nodes[] = $node;
        }

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement || !$node->hasAttribute('class')) {
                continue;
            }

            $classes = $this->filterImportedClasses($node->getAttribute('class'));
            if ($classes === '') {
                $node->removeAttribute('class');
            } else {
                $node->setAttribute('class', $classes);
            }
        }
    }

    protected function filterImportedClasses(string $classes): string
    {
        $keep = [];
        foreach (preg_split('/\s+/', trim($classes)) ?: [] as $class) {
            $class = preg_replace('/[^a-zA-Z0-9_-]/', '', $class) ?? '';
            if ($class === ''
                || str_starts_with($class, 'wp-')
                || str_starts_with($class, 'wp_')
                || preg_match('/^(wp-image-\d+|size-\w+|columns-\w+|is-\w+|has-nested-images)$/', $class) === 1) {
                continue;
            }
            $keep[$class] = true;
        }

        return implode(' ', array_keys($keep));
    }

    protected function mergeImportedClasses(string $classes, array $extra): string
    {
        $filtered = $this->filterImportedClasses($classes);
        $merged = array_filter(array_merge(preg_split('/\s+/', $filtered) ?: [], $extra));
        return implode(' ', array_values(array_unique($merged)));
    }

    protected function domInnerHtml(\DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument?->saveHTML($child) ?: '';
        }

        return trim($html);
    }

    protected function wordPressBuilderContent(array $meta): string
    {
        $parts = [];
        foreach ($meta as $key => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            if ($key === '_elementor_data') {
                $parts[] = $this->elementorContent($value);
                continue;
            }

            if (stripos((string) $key, 'nimble') !== false) {
                $parts[] = $this->genericBuilderContent($value);
            }
        }

        return $this->joinImportedBuilderParts($parts);
    }

    protected function elementorContent(string $json): string
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return '';
        }

        $parts = [];
        $this->collectElementorContent($data, $parts);
        return $this->joinImportedBuilderParts($parts);
    }

    protected function collectElementorContent(array $nodes, array &$parts): void
    {
        $contentKeys = [
            'title',
            'editor',
            'text',
            'caption',
            'description',
            'button_text',
            'html',
            'shortcode',
        ];

        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            $widgetType = (string) ($node['widgetType'] ?? '');
            $settings = is_array($node['settings'] ?? null) ? $node['settings'] : [];
            if ($settings) {
                foreach ($contentKeys as $contentKey) {
                    if (!isset($settings[$contentKey]) || !is_scalar($settings[$contentKey])) {
                        continue;
                    }
                    $parts[] = $this->cleanImportedBuilderText((string) $settings[$contentKey], $contentKey);
                }
                $this->collectGenericBuilderContent($settings, $parts);

                if ($widgetType === 'image' && isset($settings['image']['url']) && is_scalar($settings['image']['url'])) {
                    $url = trim((string) $settings['image']['url']);
                    if ($url !== '') {
                        $caption = isset($settings['caption']) && is_scalar($settings['caption']) ? (string) $settings['caption'] : '';
                        $parts[] = '<p><img src="' . e($url) . '" alt="' . e(strip_tags($caption)) . '"></p>';
                    }
                }
            }

            if (!empty($node['elements']) && is_array($node['elements'])) {
                $this->collectElementorContent($node['elements'], $parts);
            }
        }
    }

    protected function genericBuilderContent(string $value): string
    {
        $decoded = $this->decodeWordPressBuilderValue($value);
        if (is_array($decoded)) {
            $parts = [];
            $this->collectGenericBuilderContent($decoded, $parts);
            return $this->joinImportedBuilderParts($parts);
        }

        return $this->cleanImportedBuilderText($value);
    }

    protected function decodeWordPressBuilderValue(string $value): mixed
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
            $json = json_decode($trimmed, true);
            if (is_array($json)) {
                return $json;
            }
        }

        if (preg_match('/^[aOsibd]:/i', $trimmed) === 1) {
            $data = @unserialize($trimmed, ['allowed_classes' => false]);
            if (is_array($data)) {
                return $data;
            }
        }

        return $trimmed;
    }

    protected function collectGenericBuilderContent(array $data, array &$parts, string $parentKey = ''): void
    {
        $contentKeys = [
            'content',
            'text',
            'html',
            'editor',
            'title',
            'heading',
            'label',
            'subtitle',
            'description',
            'body',
            'caption',
            'button_text',
            'link_text',
        ];

        foreach ($data as $key => $value) {
            $key = is_scalar($key) ? strtolower((string) $key) : '';
            if (is_array($value)) {
                $this->collectGenericBuilderContent($value, $parts, $key);
                continue;
            }

            if (!is_scalar($value)) {
                continue;
            }

            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }

            $isContentKey = in_array($key, $contentKeys, true)
                || str_contains($key, 'content')
                || str_contains($key, 'text')
                || str_contains($key, 'title')
                || str_contains($key, 'html');
            if (!$isContentKey || str_contains($key, 'color') || str_contains($key, 'style') || str_contains($key, 'class')) {
                continue;
            }

            $parts[] = $this->cleanImportedBuilderText($text, $key ?: $parentKey);
        }
    }

    protected function cleanImportedBuilderText(string $text, string $key = ''): string
    {
        $text = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($text === '' || preg_match('/^\s*(default|none|null|false|true)\s*$/i', $text)) {
            return '';
        }

        if ($key === 'shortcode' && str_starts_with($text, '[')) {
            return '';
        }

        if ($text !== strip_tags($text)) {
            return trim($text);
        }

        if (preg_match('/^https?:\/\//i', $text)) {
            return '';
        }

        return '<p>' . nl2br(e($text), false) . '</p>';
    }

    protected function joinImportedBuilderParts(array $parts): string
    {
        $clean = [];
        $seen = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            $key = preg_replace('/\s+/', ' ', strip_tags($part)) ?: $part;
            $key = function_exists('mb_strtolower') ? mb_strtolower($key, 'UTF-8') : strtolower($key);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $clean[] = $part;
        }

        return implode("\n\n", $clean);
    }

    protected function wordPressCategoriesForPosts(\PDO $pdo, string $prefix, array $postIds): array
    {
        $postIds = array_values(array_unique(array_filter($postIds)));
        if (!$postIds) {
            return [];
        }

        $termsTable = $prefix . 'terms';
        $taxonomyTable = $prefix . 'term_taxonomy';
        $relationshipsTable = $prefix . 'term_relationships';

        $allStmt = $pdo->query(
            "select tt.term_taxonomy_id, tt.term_id, tt.parent, t.name, t.slug
             from {$taxonomyTable} tt
             inner join {$termsTable} t on t.term_id = tt.term_id
             where tt.taxonomy = 'category'"
        );
        $categoriesByTermId = [];
        $taxonomyToTermId = [];
        foreach ($allStmt->fetchAll(\PDO::FETCH_ASSOC) as $category) {
            $termId = (int) ($category['term_id'] ?? 0);
            $taxonomyId = (int) ($category['term_taxonomy_id'] ?? 0);
            if (!$termId || !$taxonomyId) {
                continue;
            }
            $categoriesByTermId[$termId] = [
                'term_id' => $termId,
                'taxonomy_id' => $taxonomyId,
                'title' => (string) ($category['name'] ?? ''),
                'slug' => $this->wordPressSlug((string) ($category['slug'] ?? '')),
                'parent_term_id' => (int) ($category['parent'] ?? 0),
            ];
            $taxonomyToTermId[$taxonomyId] = $termId;
        }

        if (!$categoriesByTermId) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $pdo->prepare(
            "select tr.object_id, tr.term_taxonomy_id
             from {$relationshipsTable} tr
             inner join {$taxonomyTable} tt on tt.term_taxonomy_id = tr.term_taxonomy_id and tt.taxonomy = 'category'
             where tr.object_id in ({$placeholders})
             order by tr.object_id asc, tt.parent asc, tt.term_id asc"
        );
        $stmt->execute($postIds);

        $result = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $postId = (int) ($row['object_id'] ?? 0);
            $termId = $taxonomyToTermId[(int) ($row['term_taxonomy_id'] ?? 0)] ?? 0;
            if (!$postId || !$termId || empty($categoriesByTermId[$termId])) {
                continue;
            }

            $chain = $this->wordPressCategoryChain($categoriesByTermId, $termId);
            $category = end($chain);
            if ($category) {
                $category['ancestors'] = array_slice($chain, 0, -1);
                $result[$postId][$category['term_id']] = $category;
            }
        }

        return array_map(static fn (array $categories): array => array_values($categories), $result);
    }

    protected function wordPressCategoryChain(array $categoriesByTermId, int $termId): array
    {
        $chain = [];
        $seen = [];
        while ($termId && isset($categoriesByTermId[$termId]) && !isset($seen[$termId])) {
            $seen[$termId] = true;
            $category = $categoriesByTermId[$termId];
            array_unshift($chain, [
                'term_id' => (int) $category['term_id'],
                'title' => (string) $category['title'],
                'slug' => (string) $category['slug'],
                'parent_term_id' => (int) $category['parent_term_id'],
            ]);
            $termId = (int) $category['parent_term_id'];
        }

        return $chain;
    }

    protected function wordPressPostsWhere(Request $request): array
    {
        $status = (string) $request->input('wp_status', 'publish');
        if (!in_array($status, ['publish', 'draft', 'any'], true)) {
            $status = 'publish';
        }

        $postType = (string) $request->input('wp_post_type', 'any');
        if (!in_array($postType, ['post', 'page', 'any'], true)) {
            $postType = 'any';
        }

        $where = $postType === 'any' ? "p.post_type in ('post', 'page')" : 'p.post_type = ?';
        $params = $postType === 'any' ? [] : [$postType];
        if ($status !== 'any') {
            $where .= ' and p.post_status = ?';
            $params[] = $status;
        }

        $search = trim((string) $request->input('wp_search', ''));
        if ($search !== '') {
            $where .= ' and (p.post_title like ? or p.post_content like ? or p.post_name like ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }

        $dateFrom = trim((string) $request->input('wp_date_from', ''));
        if ($dateFrom !== '') {
            $where .= ' and p.post_date >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }

        $dateTo = trim((string) $request->input('wp_date_to', ''));
        if ($dateTo !== '') {
            $where .= ' and p.post_date <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        return [$where, $params];
    }

    protected function wordPressSlug(string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return '';
        }

        $decoded = rawurldecode($slug);
        if ($decoded !== $slug) {
            return $decoded;
        }

        $parts = explode('-', $slug);
        if (count($parts) > 1 && count(array_filter($parts, static fn (string $part): bool => preg_match('/^[a-f0-9]{2}$/i', $part) === 1)) === count($parts)) {
            $bytes = '';
            foreach ($parts as $part) {
                $bytes .= chr(hexdec($part));
            }
            if (preg_match('//u', $bytes) === 1) {
                return $bytes;
            }
        }

        return $slug;
    }

    protected function shouldImportWordPressMedia(Request $request): bool
    {
        if ((string) $request->input('wp_import_scope', 'all') === 'posts') {
            return false;
        }

        return (bool) $request->input('wp_import_media');
    }

    protected function runWordPressMediaBatch(Request $request): array
    {
        $pdo = $this->externalImportPdo($request);
        $prefix = $this->sanitizeDbIdentifier((string) $request->input('db_prefix', 'wp_'));
        $this->importWordPressMedia($pdo, $prefix, $request);

        return $this->lastImportStats;
    }

    protected function importWordPressMedia(\PDO $pdo, string $prefix, Request $request, bool $download = true): array
    {
        $postsTable = $prefix . 'posts';
        $metaTable = $prefix . 'postmeta';
        $siteUrl = rtrim(trim((string) $request->input('wp_site_url', '')), '/');
        $uploadsPath = rtrim(trim((string) $request->input('wp_uploads_path', '')), '/\\');
        $offset = max(0, (int) $request->input('wp_media_offset', 0));
        $maxLimit = $download ? 20 : 50000;
        $limitInput = $request->input('wp_media_map_limit', $request->input('wp_media_limit', 1000));
        $limit = max(1, min($maxLimit, (int) $limitInput));
        $seconds = $download ? max(5, min(45, (int) $request->input('wp_media_seconds', self::WP_MEDIA_BATCH_SECONDS))) : 0;
        $deadline = $seconds > 0 ? microtime(true) + $seconds : 0.0;
        $this->lastWordPressMediaPaths = [];

        $countStmt = $pdo->query("select count(*) as c from {$postsTable} where post_type = 'attachment'");
        $total = (int) ($countStmt->fetch(\PDO::FETCH_ASSOC)['c'] ?? 0);

        $stmt = $pdo->prepare(
            "select p.ID, p.guid, p.post_title, p.post_name, p.post_mime_type, p.post_excerpt, p.post_content,
                    m.meta_value as attached_file, mm.meta_value as attachment_metadata, alt.meta_value as alt_text
             from {$postsTable} p
             left join {$metaTable} m on m.post_id = p.ID and m.meta_key = '_wp_attached_file'
             left join {$metaTable} mm on mm.post_id = p.ID and mm.meta_key = '_wp_attachment_metadata'
             left join {$metaTable} alt on alt.post_id = p.ID and alt.meta_key = '_wp_attachment_image_alt'
             where p.post_type = 'attachment'
             order by p.ID asc
             limit {$limit} offset {$offset}"
        );
        $stmt->execute();

        $map = [];
        $loaded = 0;
        $saved = 0;
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $attachment) {
            $loaded++;
            $relativeSource = trim((string) ($attachment['attached_file'] ?? ''), '/');
            $sourceUrl = trim((string) ($attachment['guid'] ?? ''));
            $source = $this->wordPressMediaSource($relativeSource, $sourceUrl, $siteUrl, $uploadsPath);
            if ($source === '') {
                continue;
            }

            $savedPath = $this->saveImportedMedia($source, $relativeSource, $sourceUrl, (int) ($attachment['ID'] ?? 0), $download, $deadline);
            if ($savedPath === '') {
                continue;
            }
            $saved++;
            $this->lastWordPressMediaPaths[(int) ($attachment['ID'] ?? 0)] = $savedPath;
            $this->saveImportedWordPressMediaMetadata($savedPath, $attachment);

            $newUrl = url('/uploads/' . $savedPath);
            foreach ($this->wordPressMediaAliases($relativeSource, $sourceUrl, $siteUrl, (string) ($attachment['attachment_metadata'] ?? ''), (string) ($attachment['post_name'] ?? ''), (int) ($attachment['ID'] ?? 0)) as $alias) {
                $map[$alias] = $newUrl;
            }

            if ($deadline > 0 && microtime(true) >= $deadline) {
                break;
            }
        }

        uksort($map, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $this->lastImportStats = array_replace($this->lastImportStats, [
            'media_total' => $total,
            'media_offset' => $offset,
            'media_limit' => $limit,
            'media_loaded' => $loaded,
            'media_imported' => $saved,
            'media_failed' => max(0, $loaded - $saved),
            'media_next_offset' => $offset + $loaded,
            'media_has_more' => ($offset + $loaded) < $total,
            'media_time_limited' => $deadline > 0 && microtime(true) >= $deadline,
        ]);
        return $map;
    }

    protected function wordPressMediaPathsForAttachments(\PDO $pdo, string $prefix, array $attachmentIds, Request $request, bool $download): array
    {
        $attachmentIds = array_values(array_unique(array_filter(array_map('intval', $attachmentIds))));
        if (!$attachmentIds) {
            return [];
        }

        $postsTable = $prefix . 'posts';
        $metaTable = $prefix . 'postmeta';
        $siteUrl = rtrim(trim((string) $request->input('wp_site_url', '')), '/');
        $uploadsPath = rtrim(trim((string) $request->input('wp_uploads_path', '')), '/\\');
        $placeholders = implode(',', array_fill(0, count($attachmentIds), '?'));
        $stmt = $pdo->prepare(
            "select p.ID, p.guid, p.post_title, p.post_excerpt, p.post_content,
                    m.meta_value as attached_file, alt.meta_value as alt_text
             from {$postsTable} p
             left join {$metaTable} m on m.post_id = p.ID and m.meta_key = '_wp_attached_file'
             left join {$metaTable} alt on alt.post_id = p.ID and alt.meta_key = '_wp_attachment_image_alt'
             where p.post_type = 'attachment' and p.ID in ({$placeholders})"
        );
        $stmt->execute($attachmentIds);

        $paths = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $attachment) {
            $id = (int) ($attachment['ID'] ?? 0);
            $relativeSource = trim((string) ($attachment['attached_file'] ?? ''), '/');
            $sourceUrl = trim((string) ($attachment['guid'] ?? ''));
            $source = $this->wordPressMediaSource($relativeSource, $sourceUrl, $siteUrl, $uploadsPath);
            if (!$id || $source === '') {
                continue;
            }

            $savedPath = $this->saveImportedMedia($source, $relativeSource, $sourceUrl, $id, $download);
            if ($savedPath !== '') {
                $paths[$id] = $savedPath;
                $this->saveImportedWordPressMediaMetadata($savedPath, $attachment);
            }
        }

        return $paths;
    }

    protected function saveImportedWordPressMediaMetadata(string $path, array $attachment): void
    {
        $path = Files::normalize($path);
        if ($path === '') {
            return;
        }

        $imported = array_replace($this->wordPressMediaMetadataFromAttachment($attachment), [
            'original_name' => basename((string) ($attachment['attached_file'] ?? '')),
            'uploaded_by' => (string) $this->currentUserId(),
        ]);

        try {
            $current = MediaMetadata::get($path);
            foreach ($imported as $key => $value) {
                if ($value !== '') {
                    $current[$key] = $value;
                }
            }

            MediaMetadata::save($path, $current);
        } catch (Throwable) {
            return;
        }
    }

    protected function wordPressMediaMetadataFromAttachment(array $attachment): array
    {
        return MediaMetadata::normalizeEntry([
            'title' => $this->cleanWordPressMediaMetadata((string) ($attachment['post_title'] ?? '')),
            'alt_text' => $this->cleanWordPressMediaMetadata((string) ($attachment['alt_text'] ?? '')),
            'caption' => $this->cleanWordPressMediaMetadata((string) ($attachment['post_excerpt'] ?? '')),
            'description' => $this->cleanWordPressMediaMetadata((string) ($attachment['post_content'] ?? ''), 1000),
        ]);
    }

    protected function cleanWordPressMediaMetadata(string $value, int $limit = 160): string
    {
        $value = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $value = $limit > 160
            ? (preg_replace("/[ \t\r]+/", ' ', $value) ?? '')
            : (preg_replace('/\s+/', ' ', $value) ?? '');
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit, 'UTF-8');
        }

        return substr($value, 0, $limit);
    }

    protected function importWordPressMenus(Request $request, string $mode = 'create'): array
    {
        $pdo = $this->externalImportPdo($request);
        $prefix = $this->sanitizeDbIdentifier((string) $request->input('db_prefix', 'wp_'));
        $menus = $this->wordPressMenus($pdo, $prefix);
        if (!$menus) {
            return ['menus_imported' => 0, 'menu_items_imported' => 0];
        }

        $settings = $this->siteSettings();
        $templateKey = preg_replace('/[^a-z0-9_-]/i', '', (string) ($settings['site_template'] ?? 'official')) ?: 'official';
        $theme = SiteThemes::get($templateKey);
        $template = (string) ($theme['key'] ?? $templateKey);
        $layouts = json_decode((string) ($settings['site_template_layouts'] ?? ''), true);
        $layouts = is_array($layouts) ? $layouts : [];
        $layout = is_array($layouts[$template] ?? null) ? $layouts[$template] : [];
        $header = is_array($layout['header'] ?? null) ? $layout['header'] : [];

        $menuValues = array_values($menus);
        $header['links'] = $menuValues[0]['links'] ?? [];
        if (!empty($menuValues[1]['links'])) {
            $header['secondary_enabled'] = true;
            $header['secondary_links'] = $menuValues[1]['links'];
        } elseif ($mode !== 'update') {
            $header['secondary_links'] = [];
        }

        $layout['header'] = array_replace([
            'variant' => 'default',
            'show_brand' => true,
            'mobile_variant' => 'drawer',
            'mobile_source' => 'main',
            'mobile_label' => 'Меню',
            'mobile_show_brand' => true,
            'mobile_show_cta' => true,
        ], $header);
        $layout['footer'] = is_array($layout['footer'] ?? null) ? $layout['footer'] : [];
        $layouts[$template] = $layout;

        $encoded = json_encode($layouts, JSON_UNESCAPED_UNICODE);
        $this->saveSetting('site_template_layouts', $encoded === false ? '{}' : $encoded);

        return [
            'menus_imported' => count($menus),
            'menu_items_imported' => array_sum(array_map(static fn (array $menu): int => (int) ($menu['count'] ?? 0), $menus)),
        ];
    }

    protected function wordPressMenus(\PDO $pdo, string $prefix): array
    {
        $postsTable = $prefix . 'posts';
        $termsTable = $prefix . 'terms';
        $taxonomyTable = $prefix . 'term_taxonomy';
        $relationshipsTable = $prefix . 'term_relationships';
        $metaTable = $prefix . 'postmeta';
        $rows = $pdo->query(
            "select tt.term_taxonomy_id, t.name as menu_name, t.slug as menu_slug,
                    p.ID, p.post_title, p.menu_order
             from {$termsTable} t
             inner join {$taxonomyTable} tt on tt.term_id = t.term_id and tt.taxonomy = 'nav_menu'
             inner join {$relationshipsTable} tr on tr.term_taxonomy_id = tt.term_taxonomy_id
             inner join {$postsTable} p on p.ID = tr.object_id and p.post_type = 'nav_menu_item'
             where p.post_status in ('publish', 'draft')
             order by tt.term_taxonomy_id asc, p.menu_order asc, p.ID asc"
        )->fetchAll(\PDO::FETCH_ASSOC);
        if (!$rows) {
            return [];
        }

        $itemIds = array_map(static fn (array $row): int => (int) $row['ID'], $rows);
        $meta = $this->wordPressPostMeta($pdo, $metaTable, $itemIds);
        $objectIds = [];
        foreach ($itemIds as $itemId) {
            $objectId = (int) ($meta[$itemId]['_menu_item_object_id'] ?? 0);
            if ($objectId > 0) {
                $objectIds[] = $objectId;
            }
        }
        $objects = $this->wordPressMenuObjects($pdo, $prefix, $objectIds);

        $menus = [];
        foreach ($rows as $row) {
            $itemId = (int) ($row['ID'] ?? 0);
            $menuId = (int) ($row['term_taxonomy_id'] ?? 0);
            if (!$itemId || !$menuId) {
                continue;
            }
            $menuMeta = $meta[$itemId] ?? [];
            $objectId = (int) ($menuMeta['_menu_item_object_id'] ?? 0);
            $objectKey = (string) ($menuMeta['_menu_item_object'] ?? '') . ':' . $objectId;
            $item = [
                'id' => $itemId,
                'parent_id' => (int) ($menuMeta['_menu_item_menu_item_parent'] ?? 0),
                'label' => $this->wpImportLimitString((string) ($row['post_title'] ?? ''), 80),
                'url' => $this->wordPressMenuItemUrl($menuMeta, $objects[$objectKey] ?? $objects['id:' . $objectId] ?? null),
                'children' => [],
            ];
            if ($item['label'] === '') {
                $item['label'] = $this->wpImportLimitString((string) (($objects[$objectKey]['title'] ?? $objects['id:' . $objectId]['title'] ?? '') ?: 'Пункт меню'), 80);
            }
            $menus[$menuId]['name'] = (string) ($row['menu_name'] ?? '');
            $menus[$menuId]['items'][$itemId] = $item;
        }

        $result = [];
        foreach ($menus as $menuId => $menu) {
            $items = $menu['items'] ?? [];
            foreach ($items as $itemId => $item) {
                $parentId = (int) ($item['parent_id'] ?? 0);
                if ($parentId > 0 && isset($items[$parentId])) {
                    $items[$parentId]['children'][] = $itemId;
                }
            }
            $links = [];
            foreach ($items as $itemId => $item) {
                if ((int) ($item['parent_id'] ?? 0) === 0) {
                    $links[] = $this->wordPressMenuLink($itemId, $items);
                }
            }
            $result[$menuId] = [
                'name' => (string) ($menu['name'] ?? ''),
                'links' => array_slice(array_values(array_filter($links)), 0, 16),
                'count' => count($items),
            ];
        }

        return $result;
    }

    protected function wordPressPostMeta(\PDO $pdo, string $metaTable, array $postIds): array
    {
        $postIds = array_values(array_unique(array_filter(array_map('intval', $postIds))));
        if (!$postIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $pdo->prepare("select post_id, meta_key, meta_value from {$metaTable} where post_id in ({$placeholders})");
        $stmt->execute($postIds);
        $meta = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $meta[(int) $row['post_id']][(string) $row['meta_key']] = (string) $row['meta_value'];
        }
        return $meta;
    }

    protected function wordPressMenuObjects(\PDO $pdo, string $prefix, array $objectIds): array
    {
        $objectIds = array_values(array_unique(array_filter(array_map('intval', $objectIds))));
        if (!$objectIds) {
            return [];
        }
        $postsTable = $prefix . 'posts';
        $termsTable = $prefix . 'terms';
        $placeholders = implode(',', array_fill(0, count($objectIds), '?'));
        $objects = [];
        $stmt = $pdo->prepare("select ID, post_title, post_name, post_type from {$postsTable} where ID in ({$placeholders})");
        $stmt->execute($objectIds);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $postId = (int) $row['ID'];
            $postType = (string) ($row['post_type'] ?? '');
            $object = [
                'kind' => 'post',
                'type' => $postType,
                'title' => (string) ($row['post_title'] ?? ''),
                'slug' => $this->wordPressSlug((string) ($row['post_name'] ?? '')),
            ];
            $objects[$postType . ':' . $postId] = $object;
            $objects['id:' . $postId] = $object;
        }
        $stmt = $pdo->prepare("select term_id, name, slug from {$termsTable} where term_id in ({$placeholders})");
        $stmt->execute($objectIds);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $termId = (int) $row['term_id'];
            $object = [
                'kind' => 'term',
                'type' => 'category',
                'title' => (string) ($row['name'] ?? ''),
                'slug' => $this->wordPressSlug((string) ($row['slug'] ?? '')),
            ];
            $objects['category:' . $termId] = $object;
            $objects['id:' . $termId] ??= $object;
        }
        return $objects;
    }

    protected function wordPressMenuItemUrl(array $meta, ?array $object): string
    {
        $type = (string) ($meta['_menu_item_type'] ?? '');
        if ($type === 'custom') {
            return $this->wpImportUrl((string) ($meta['_menu_item_url'] ?? '#'));
        }
        if (!$object) {
            return '#';
        }
        if (($object['kind'] ?? '') === 'term') {
            return url('/news?category=' . rawurlencode((string) ($object['title'] ?? '')));
        }
        $slug = (string) ($object['slug'] ?? '');
        if (($object['type'] ?? '') === 'page') {
            return $slug === 'home' ? url('/') : url('/page/' . $slug);
        }
        if (($object['type'] ?? '') === 'post') {
            return url('/news/' . $slug);
        }
        return '#';
    }

    protected function wordPressMenuLink(int $itemId, array $items): ?array
    {
        if (!isset($items[$itemId])) {
            return null;
        }
        $item = $items[$itemId];
        $childIds = is_array($item['children'] ?? null) ? $item['children'] : [];
        $childLinks = [];
        foreach ($childIds as $childId) {
            $child = $this->wordPressMenuLink((int) $childId, $items);
            if ($child) {
                $childLinks[] = $child;
            }
        }
        $hasGrandchildren = false;
        foreach ($childIds as $childId) {
            if (!empty($items[(int) $childId]['children'])) {
                $hasGrandchildren = true;
                break;
            }
        }
        $link = [
            'type' => 'link',
            'label' => $this->wpImportLimitString((string) ($item['label'] ?? ''), 80),
            'url' => $this->wpImportUrl((string) ($item['url'] ?? '#')),
            'icon' => '',
            'children' => [],
            'columns' => [],
        ];
        if (!$hasGrandchildren) {
            $link['children'] = array_slice($childLinks, 0, 12);
            return $link;
        }

        $link['type'] = 'section';
        $link['url'] = '#';
        $columns = [];
        foreach ($childIds as $childId) {
            if (empty($items[(int) $childId])) {
                continue;
            }
            $childItem = $items[(int) $childId];
            $columnChildren = [];
            if ((string) ($childItem['url'] ?? '#') !== '#') {
                $columnChildren[] = [
                    'type' => 'link',
                    'label' => $this->wpImportLimitString((string) ($childItem['label'] ?? ''), 80),
                    'url' => $this->wpImportUrl((string) ($childItem['url'] ?? '#')),
                    'icon' => '',
                    'children' => [],
                    'columns' => [],
                ];
            }
            foreach (($childItem['children'] ?? []) as $grandchildId) {
                $grandchild = $this->wordPressMenuLink((int) $grandchildId, $items);
                if ($grandchild) {
                    $columnChildren[] = $grandchild;
                }
            }
            $columns[] = [
                'title' => $this->wpImportLimitString((string) ($childItem['label'] ?? ''), 80),
                'children' => array_slice($columnChildren, 0, 12),
            ];
        }
        $link['columns'] = array_slice($columns, 0, 4);
        return $link;
    }

    protected function wpImportLimitString(string $value, int $limit): string
    {
        $value = trim($value);
        if (function_exists('mb_substr')) {
            return trim(mb_substr($value, 0, $limit, 'UTF-8'));
        }
        return trim(substr($value, 0, $limit));
    }

    protected function wpImportUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '#';
        }
        if (str_starts_with($url, '/') || preg_match('/^https?:\/\//i', $url) || str_starts_with($url, '#')) {
            return $this->wpImportLimitString($url, 240);
        }
        return '#';
    }

    protected function wordPressMediaSource(string $relativeSource, string $sourceUrl, string $siteUrl, string $uploadsPath): string
    {
        if ($uploadsPath !== '' && $relativeSource !== '') {
            $local = $uploadsPath . '/' . $relativeSource;
            if (is_file($local)) {
                return $local;
            }

            $originalRelative = $this->wordPressOriginalImageRelative($relativeSource);
            if ($originalRelative !== $relativeSource) {
                $localOriginal = $uploadsPath . '/' . $originalRelative;
                if (is_file($localOriginal)) {
                    return $localOriginal;
                }
            }
        }

        if ($sourceUrl !== '' && preg_match('/^https?:\/\//i', $sourceUrl)) {
            return $sourceUrl;
        }

        if ($siteUrl !== '' && $relativeSource !== '') {
            return $siteUrl . '/wp-content/uploads/' . $relativeSource;
        }

        return '';
    }

    protected function wordPressOriginalImageRelative(string $relative): string
    {
        $extension = pathinfo($relative, PATHINFO_EXTENSION);
        if ($extension === '' || !in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return $relative;
        }

        $dirname = trim(str_replace('\\', '/', pathinfo($relative, PATHINFO_DIRNAME)), '.');
        $filename = pathinfo($relative, PATHINFO_FILENAME);
        $original = preg_replace('/-\d{2,5}x\d{2,5}$/', '', $filename) ?? $filename;
        if ($original === $filename) {
            return $relative;
        }

        return Files::normalize(($dirname !== '' ? $dirname . '/' : '') . $original . '.' . $extension);
    }

    protected function wordPressContentMediaLimit(Request $request): int
    {
        $limit = (int) $request->input('wp_content_media_limit', self::WP_CONTENT_MEDIA_LIMIT);
        return max(0, min(100, $limit));
    }

    protected function wordPressContentMediaSeconds(Request $request): int
    {
        $seconds = (int) $request->input('wp_content_media_seconds', self::WP_CONTENT_MEDIA_SECONDS);
        return max(5, min(60, $seconds));
    }

    protected function replaceWordPressContentMediaUrls(string $content, Request $request, int &$remainingDownloads, float $deadline): string
    {
        $urls = $this->wordPressContentMediaUrls($content);
        if (!$urls) {
            return $content;
        }

        $siteUrl = rtrim(trim((string) $request->input('wp_site_url', '')), '/');
        $uploadsPath = rtrim(trim((string) $request->input('wp_uploads_path', '')), '/\\');
        $replacements = [];
        foreach ($urls as $url) {
            $relative = $this->wordPressUploadsRelativeFromUrl($url);
            if ($relative === '') {
                continue;
            }
            $targetRelative = $this->wordPressOriginalImageRelative($relative);
            $sourceUrl = $this->absoluteWordPressMediaUrl($url, $siteUrl);

            $savedPath = $this->saveImportedMedia('', $targetRelative, $sourceUrl, $this->wordPressContentMediaId($targetRelative), false);
            if ($savedPath === '') {
                if ($remainingDownloads <= 0 || microtime(true) >= $deadline) {
                    $this->lastImportStats['content_media_deferred'] = (int) ($this->lastImportStats['content_media_deferred'] ?? 0) + 1;
                    continue;
                }

                $remainingDownloads--;
                $source = $this->wordPressMediaSource($relative, $sourceUrl, $siteUrl, $uploadsPath);
                if ($source === '') {
                    continue;
                }

                $savedPath = $this->saveImportedMedia($source, $targetRelative, $sourceUrl, $this->wordPressContentMediaId($targetRelative));
            }
            if ($savedPath === '') {
                continue;
            }

            $this->lastImportStats['content_media_imported'] = (int) ($this->lastImportStats['content_media_imported'] ?? 0) + 1;
            $newUrl = url('/uploads/' . $savedPath);
            $variants = $this->wordPressContentUrlVariants($url, $siteUrl);
            if ($targetRelative !== $relative) {
                $variants = array_merge($variants, $this->wordPressContentUrlVariants('/wp-content/uploads/' . $targetRelative, $siteUrl));
            }
            foreach ($variants as $variant) {
                $replacements[$variant] = $newUrl;
            }
        }

        return $replacements ? str_replace(array_keys($replacements), array_values($replacements), $content) : $content;
    }

    protected function wordPressContentMediaUrls(string $content): array
    {
        preg_match_all('#(?:https?:)?//[^"\'\s<>]+/wp-content/uploads/[^"\'\s<>]+|/wp-content/uploads/[^"\'\s<>]+#i', $content, $matches);
        $urls = [];
        foreach ($matches[0] ?? [] as $url) {
            $url = trim(html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $url = $this->cleanWordPressContentMediaUrl($url);
            if ($url !== '') {
                $urls[$url] = true;
            }
        }

        return array_keys($urls);
    }

    protected function cleanWordPressContentMediaUrl(string $url): string
    {
        $url = trim($url);
        $url = preg_replace('/\s+\d+[wx]\s*$/i', '', $url) ?? $url;
        $url = rtrim($url, " \t\n\r\0\x0B'\"),.;");
        return $url;
    }

    protected function absoluteWordPressMediaUrl(string $url, string $siteUrl): string
    {
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        if ($siteUrl !== '' && str_starts_with($url, '/')) {
            return rtrim($siteUrl, '/') . $url;
        }

        return $url;
    }

    protected function wordPressContentUrlVariants(string $url, string $siteUrl): array
    {
        $decodedUrl = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $variants = [$url, $decodedUrl];

        $encodedUrl = htmlspecialchars($decodedUrl, ENT_QUOTES, 'UTF-8');
        $variants[] = $encodedUrl;

        $absoluteUrl = $this->absoluteWordPressMediaUrl($decodedUrl, $siteUrl);
        if ($absoluteUrl !== $decodedUrl) {
            $variants[] = $absoluteUrl;
            $variants[] = htmlspecialchars($absoluteUrl, ENT_QUOTES, 'UTF-8');
        }

        $alternateUrl = $this->toggleScheme($absoluteUrl);
        if ($alternateUrl !== '') {
            $variants[] = $alternateUrl;
            $variants[] = htmlspecialchars($alternateUrl, ENT_QUOTES, 'UTF-8');
        }

        $path = parse_url($absoluteUrl, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $variants[] = $path;
        }

        return array_values(array_unique(array_filter($variants, static fn (string $variant): bool => trim($variant) !== '')));
    }

    protected function wordPressUploadsRelativeFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $url;
        }

        $marker = '/wp-content/uploads/';
        $position = stripos($path, $marker);
        if ($position === false) {
            return '';
        }

        return Files::normalize(rawurldecode(substr($path, $position + strlen($marker))));
    }

    protected function wordPressContentMediaId(string $relative): int
    {
        return (int) (sprintf('%u', crc32($relative)) ?: 0);
    }

    protected function saveImportedMedia(string $source, string $relativeSource, string $sourceUrl, int $id, bool $download = true, ?float $deadline = null): string
    {
        $name = basename($relativeSource ?: (parse_url($sourceUrl, PHP_URL_PATH) ?: ''));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp'], true)) {
            return '';
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', pathinfo($name, PATHINFO_FILENAME)) ?: 'file';
        $targetRelative = date('Y/m/') . 'wp-' . $id . '-' . $safeName . '.' . $extension;
        $target = base_path('storage/uploads/' . $targetRelative);
        if (is_file($target)) {
            try {
                MediaMetadata::ensure($targetRelative, [
                    'original_name' => $name,
                    'uploaded_by' => (string) $this->currentUserId(),
                ]);
            } catch (Throwable) {
                return '';
            }
            return $targetRelative;
        }

        $existingPath = $this->existingImportedMediaPath($safeName, $extension);
        if ($existingPath !== '') {
            try {
                MediaMetadata::ensure($existingPath, [
                    'original_name' => $name,
                    'uploaded_by' => (string) $this->currentUserId(),
                ]);
            } catch (Throwable) {
                return '';
            }
            return $existingPath;
        }

        if (!$download) {
            return '';
        }
        if ($deadline !== null && $deadline > 0 && microtime(true) >= $deadline) {
            return '';
        }
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }

        $streamTimeout = max(1, min(4, self::IMPORT_DOWNLOAD_TIMEOUT));
        $context = stream_context_create([
            'http' => ['timeout' => $streamTimeout],
            'https' => ['timeout' => $streamTimeout],
        ]);
        $sourceStream = is_file($source) ? @fopen($source, 'rb') : @fopen($source, 'rb', false, $context);
        if (!$sourceStream) {
            return '';
        }
        @stream_set_timeout($sourceStream, $streamTimeout);

        $targetStream = @fopen($target, 'wb');
        if (!$targetStream) {
            fclose($sourceStream);
            return '';
        }

        $bytes = 0;
        while (!feof($sourceStream)) {
            if ($deadline !== null && $deadline > 0 && microtime(true) >= $deadline) {
                $bytes = false;
                break;
            }
            $chunk = fread($sourceStream, 1024 * 256);
            if ($chunk === false) {
                $bytes = false;
                break;
            }
            if ($chunk === '') {
                $meta = stream_get_meta_data($sourceStream);
                if (!empty($meta['timed_out'])) {
                    $bytes = false;
                    break;
                }
                continue;
            }
            $written = fwrite($targetStream, $chunk);
            if ($written === false || $written <= 0) {
                $bytes = false;
                break;
            }
            $bytes += $written;
        }
        fclose($sourceStream);
        fclose($targetStream);
        if ($bytes === false || $bytes <= 0) {
            @unlink($target);
            return '';
        }

        try {
            MediaMetadata::ensure($targetRelative, [
                'original_name' => $name,
                'uploaded_by' => (string) $this->currentUserId(),
            ]);
        } catch (Throwable) {
            @unlink($target);
            return '';
        }

        if ($this->importedMediaPathIndex !== null) {
            $this->addImportedMediaPathIndexEntry($this->importedMediaPathIndex, basename($targetRelative), $targetRelative);
            $this->addImportedMediaPathIndexEntry($this->importedMediaPathIndex, $safeName . '.' . $extension, $targetRelative);
        }

        return $targetRelative;
    }

    protected function existingImportedMediaPath(string $safeName, string $extension): string
    {
        $needle = strtolower($safeName . '.' . $extension);
        $index = $this->importedMediaPathIndex();
        return (string) ($index[$needle] ?? '');
    }

    protected function importedMediaPathIndex(): array
    {
        if ($this->importedMediaPathIndex !== null) {
            return $this->importedMediaPathIndex;
        }

        $root = base_path('storage/uploads');
        if (!is_dir($root)) {
            return $this->importedMediaPathIndex = [];
        }

        $index = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $extension = strtolower($file->getExtension());
            if (!in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp'], true)) {
                continue;
            }

            $relative = Files::normalize(str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1)));
            if ($relative === '') {
                continue;
            }

            $basename = $file->getBasename();
            $this->addImportedMediaPathIndexEntry($index, $basename, $relative);
            if (preg_match('/^wp-\d+-(.+)$/i', $basename, $match) === 1) {
                $this->addImportedMediaPathIndexEntry($index, (string) $match[1], $relative);
            }
        }

        return $this->importedMediaPathIndex = $index;
    }

    protected function addImportedMediaPathIndexEntry(array &$index, string $basename, string $relative): void
    {
        $key = strtolower(trim($basename));
        if ($key === '' || isset($index[$key])) {
            return;
        }

        $index[$key] = $relative;
    }

    protected function wordPressMediaAliases(string $relativeSource, string $sourceUrl, string $siteUrl, string $metadata, string $attachmentSlug, int $attachmentId): array
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

    protected function wordPressUploadsBaseUrl(string $sourceUrl): string
    {
        $marker = '/wp-content/uploads/';
        $position = strpos($sourceUrl, $marker);
        if ($position === false) {
            return '';
        }

        return substr($sourceUrl, 0, $position);
    }

    protected function wordPressMetadataFiles(string $relativeSource, string $metadata): array
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

    protected function toggleScheme(string $url): string
    {
        if (str_starts_with($url, 'http://')) {
            return 'https://' . substr($url, 7);
        }
        if (str_starts_with($url, 'https://')) {
            return 'http://' . substr($url, 8);
        }

        return '';
    }

    protected function externalImportPdo(Request $request): \PDO
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

    protected function sanitizeDbIdentifier(string $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9_]/', '', $value) ?? '';
        return $value !== '' ? $value : 'wp_';
    }

    protected function parseImportJson(string $source, string $type): array
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

    protected function parseImportCsv(string $source): array
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

    protected function detectCsvDelimiter(string $source): string
    {
        $firstLine = strtok($source, "\r\n") ?: '';
        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }

    protected function normalizeImportRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[$this->normalizeImportKey((string) $key)] = is_scalar($value) ? trim((string) $value) : $value;
        }

        return $normalized;
    }

    protected function normalizeImportKey(string $key): string
    {
        $key = trim($key);
        $key = preg_replace('/^\xEF\xBB\xBF/', '', $key) ?? $key;
        $key = function_exists('mb_strtolower') ? mb_strtolower($key) : strtolower($key);
        $key = preg_replace('/[^a-z0-9а-яіїєґ_]+/u', '_', $key) ?? $key;
        return trim($key, '_');
    }

    protected function importRows(string $type, array $rows, string $mode = 'create'): int
    {
        $mode = $this->importMode($mode);
        return match ($type) {
            'news' => $this->importNewsRows($rows, $mode),
            'pages' => $this->importPageRows($rows, $mode),
            'global_fields' => $this->importGlobalFieldRows($rows, $mode),
            'wordpress' => $this->importWordPressRows($rows, $mode),
            default => 0,
        };
    }

    protected function importMode(string $mode): string
    {
        return in_array($mode, ['create', 'update', 'upsert'], true) ? $mode : 'create';
    }

    protected function importWordPressRows(array $rows, string $mode = 'create'): int
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
            $created += $this->importNewsRows($newsRows, $mode);
        }
        if ($pageRows) {
            $created += $this->importPageRows($pageRows, $mode);
        }

        return $created;
    }

    protected function importLegacySlug(array $row): string
    {
        $legacy = trim((string) ($row['_wp_legacy_slug'] ?? ''));
        return $legacy !== '' ? $this->limitSlug($this->slug($legacy), 180) : '';
    }

    protected function importExistingBySlug(string $table, string $slug, string $legacySlug = ''): ?array
    {
        if (!in_array($table, ['news', 'pages'], true)) {
            return null;
        }

        $existing = $this->db()->fetch("select id, created_by from {$table} where slug = ? limit 1", [$slug]);
        if ($existing || $legacySlug === '' || $legacySlug === $slug) {
            return $existing ?: null;
        }

        return $this->db()->fetch("select id, created_by from {$table} where slug = ? limit 1", [$legacySlug]) ?: null;
    }

    protected function importNewsRows(array $rows, string $mode = 'create'): int
    {
        $created = 0;
        $now = date('c');
        foreach ($rows as $row) {
            $title = $this->importValue($row, ['title', 'назва', 'заголовок']);
            if ($title === '') {
                continue;
            }
            $slugSource = $this->importValue($row, ['slug', 'адреса']) ?: $title;
            $title = $this->importTitle($title);
            $slug = $this->limitSlug($this->slug($slugSource), 180) ?: $this->limitSlug($this->slug($title), 180);
            if ($slug === '') {
                continue;
            }
            $legacySlug = $this->importLegacySlug($row);
            $existing = $mode !== 'create' ? $this->importExistingBySlug('news', $slug, $legacySlug) : null;
            if ($existing && !$this->canManageAllContent() && (int) ($existing['created_by'] ?? 0) !== $this->currentUserId()) {
                if ($mode === 'update') {
                    continue;
                }
                $existing = null;
            }
            if ($mode === 'update' && !$existing) {
                continue;
            }

            $status = $this->importStatus($this->importValue($row, ['status', 'статус']), 'draft');
            if ($status === 'published' && !Container::get('auth')->can('news.publish')) {
                $status = Container::get('auth')->can('news.manage') ? 'pending_review' : 'draft';
            }
            $publishedAt = $this->importValue($row, ['published_at', 'дата_публікації', 'дата']);
            if ($status === 'published' && $publishedAt === '') {
                $publishedAt = $now;
            }
            $categoryPayloads = $this->importNewsCategoryPayloads($row);
            $category = (string) ($categoryPayloads[0]['title'] ?? 'Загальні');
            $categoryIds = $this->ensureImportedNewsCategories($categoryPayloads, $now);
            $imagePath = Files::normalize($this->importValue($row, ['image_path', 'featured_image', 'main_image', 'головне_зображення']));
            if ($imagePath !== '' && !$this->isImportImagePath($imagePath)) {
                $imagePath = '';
            }
            if ($existing && $imagePath === '') {
                $current = $this->db()->fetch('select image_path from news where id = ? limit 1', [(int) ($existing['id'] ?? 0)]);
                $imagePath = Files::normalize((string) ($current['image_path'] ?? ''));
            }

            $data = [
                $title,
                $category,
                $imagePath !== '' ? $imagePath : null,
                $this->normalizeImportedContent($this->importValue($row, ['body', 'text', 'контент', 'текст', 'опис'])),
                $status,
                $publishedAt ?: null,
                $now,
            ];

            if ($existing) {
                $newsId = (int) ($existing['id'] ?? 0);
                $this->db()->execute(
                    'update news set title=?, slug=?, category=?, image_path=?, body=?, status=?, published_at=?, updated_at=? where id=?',
                    [$title, $slug, $category, $imagePath !== '' ? $imagePath : null, $data[3], $status, $publishedAt ?: null, $now, $newsId]
                );
                $this->db()->execute('delete from news_category_links where news_id = ?', [$newsId]);
            } else {
                $this->db()->execute(
                    'insert into news (created_by, title, slug, category, image_path, body, status, published_at, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$this->currentUserId(), $title, $this->uniqueSlug('news', $slugSource), $category, $imagePath !== '' ? $imagePath : null, $data[3], $status, $publishedAt ?: null, $now, $now]
                );
                $newsId = (int) $this->db()->lastInsertId();
            }
            foreach ($categoryIds as $categoryId) {
                $this->db()->execute('insert into news_category_links (news_id, category_id) values (?, ?)', [$newsId, $categoryId]);
            }
            $created++;
        }

        return $created;
    }

    protected function isImportImagePath(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)
            && is_file(base_path('storage/uploads/' . $path));
    }

    protected function importNewsCategoryPayloads(array $row): array
    {
        if (!empty($row['_wp_categories']) && is_array($row['_wp_categories'])) {
            $payloads = [];
            foreach ($row['_wp_categories'] as $category) {
                if (!is_array($category)) {
                    continue;
                }
                $title = trim((string) ($category['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $payloads[] = [
                    'title' => $title,
                    'slug' => trim((string) ($category['slug'] ?? '')),
                    'ancestors' => is_array($category['ancestors'] ?? null) ? $category['ancestors'] : [],
                ];
            }
            if ($payloads) {
                return $payloads;
            }
        }

        $categoryValue = $this->importValue($row, ['category', 'категорія', 'rubric', 'рубрика']) ?: 'Загальні';
        $parts = preg_split('/\s*[,;|]\s*/u', $categoryValue) ?: [];
        $payloads = [];
        foreach ($parts as $part) {
            $title = trim((string) $part);
            if ($title !== '') {
                $payloads[] = ['title' => $title, 'slug' => '', 'ancestors' => []];
            }
        }

        return $payloads ?: [['title' => 'Загальні', 'slug' => '', 'ancestors' => []]];
    }

    protected function ensureImportedNewsCategories(array $categories, string $now): array
    {
        $idsByTitle = [];
        $ids = [];
        foreach ($categories as $category) {
            $title = trim((string) ($category['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $parentId = null;
            foreach (($category['ancestors'] ?? []) as $ancestor) {
                if (!is_array($ancestor)) {
                    continue;
                }
                $ancestorTitle = trim((string) ($ancestor['title'] ?? ''));
                if ($ancestorTitle === '') {
                    continue;
                }
                $parentId = $this->ensureImportedNewsCategory($ancestorTitle, $now, $parentId, (string) ($ancestor['slug'] ?? ''));
                $idsByTitle[$ancestorTitle] = $parentId;
            }

            $id = $this->ensureImportedNewsCategory($title, $now, $parentId ?: null, (string) ($category['slug'] ?? ''));
            $idsByTitle[$title] = $id;
            if ($id && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids ?: [$this->ensureImportedNewsCategory('Загальні', $now)];
    }

    protected function ensureImportedNewsCategory(string $title, string $now, ?int $parentId = null, string $slug = ''): int
    {
        $title = trim($title);
        if ($title === '') {
            return 0;
        }

        $existing = $this->db()->fetch('select id from news_categories where title = ?', [$title]);
        if ($existing) {
            if ($parentId) {
                $this->db()->execute('update news_categories set parent_id = ? where id = ? and parent_id is null', [$parentId, $existing['id']]);
            }
            return (int) $existing['id'];
        }

        $this->db()->execute(
            'insert into news_categories (parent_id, title, slug, sort_order, created_at, updated_at) values (?, ?, ?, ?, ?, ?)',
            [$parentId, $title, $this->uniqueSlug('news_categories', $slug !== '' ? $slug : $title), 100, $now, $now]
        );
        return (int) $this->db()->lastInsertId();
    }

    protected function importPageRows(array $rows, string $mode = 'create'): int
    {
        $created = 0;
        $now = date('c');
        foreach ($rows as $row) {
            $title = $this->importValue($row, ['title', 'назва', 'заголовок']);
            if ($title === '') {
                continue;
            }
            $slugSource = $this->importValue($row, ['slug', 'адреса']) ?: $title;
            $title = $this->importTitle($title);
            $slug = $this->limitSlug($this->slug($slugSource), 180) ?: $this->limitSlug($this->slug($title), 180);
            if ($slug === '') {
                continue;
            }
            $legacySlug = $this->importLegacySlug($row);
            $existing = $mode !== 'create' ? $this->importExistingBySlug('pages', $slug, $legacySlug) : null;
            if ($existing && !$this->canManageAllContent() && (int) ($existing['created_by'] ?? 0) !== $this->currentUserId()) {
                if ($mode === 'update') {
                    continue;
                }
                $existing = null;
            }
            if ($mode === 'update' && !$existing) {
                continue;
            }

            $template = $this->importValue($row, ['template', 'шаблон']) ?: 'default';
            if (!array_key_exists($template, $this->pageTemplates())) {
                $template = 'default';
            }
            $body = $this->normalizeImportedContent($this->importValue($row, ['body', 'blocks_text', 'text', 'контент', 'текст', 'опис']));
            $blocks = json_encode($this->blocksFromText($body, false), JSON_UNESCAPED_UNICODE);

            $data = [
                $title,
                $this->importValue($row, ['excerpt', 'анонс', 'короткий_опис']),
                $template,
                $blocks === false ? '[]' : $blocks,
                $this->importStatus($this->importValue($row, ['status', 'статус']), 'draft'),
                (int) ($this->importValue($row, ['sort_order', 'порядок']) ?: 0),
                $now,
            ];

            if ($existing) {
                $this->db()->execute(
                    'update pages set title=?, slug=?, excerpt=?, template=?, blocks_json=?, status=?, sort_order=?, updated_at=? where id=?',
                    [$title, $slug, $data[1], $template, $data[3], $data[4], $data[5], $now, (int) ($existing['id'] ?? 0)]
                );
            } else {
                $this->db()->execute(
                    'insert into pages (created_by, title, slug, excerpt, template, blocks_json, status, sort_order, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$this->currentUserId(), $title, $this->uniqueSlug('pages', $slugSource), $data[1], $template, $data[3], $data[4], $data[5], $now, $now]
                );
            }
            $created++;
        }

        return $created;
    }

    protected function importGlobalFieldRows(array $rows, string $mode = 'create'): int
    {
        $fields = $this->globalFields();
        $created = 0;
        foreach ($rows as $row) {
            $label = $this->importValue($row, ['label', 'name', 'title', 'назва', 'поле']);
            $value = $this->importValue($row, ['value', 'значення']);
            if ($label === '' && $value === '') {
                continue;
            }

            $label = $label !== '' ? $label : 'Поле';
            if ($mode !== 'create') {
                $index = $this->globalFieldIndex($fields, $label);
                if ($mode === 'update' && $index === null) {
                    continue;
                }
                if ($index !== null) {
                    $fields[$index]['value'] = $value;
                } else {
                    $fields[] = ['label' => $label, 'value' => $value];
                }
            } else {
                $fields[] = ['label' => $label, 'value' => $value];
            }
            $created++;
        }

        $encodedFields = json_encode($fields, JSON_UNESCAPED_UNICODE);
        $this->saveSetting('global_fields', $encodedFields === false ? '[]' : $encodedFields);
        return $created;
    }

    protected function globalFieldIndex(array $fields, string $label): ?int
    {
        $needle = function_exists('mb_strtolower') ? mb_strtolower(trim($label), 'UTF-8') : strtolower(trim($label));
        foreach ($fields as $index => $field) {
            $current = function_exists('mb_strtolower')
                ? mb_strtolower(trim((string) ($field['label'] ?? '')), 'UTF-8')
                : strtolower(trim((string) ($field['label'] ?? '')));
            if ($current === $needle) {
                return (int) $index;
            }
        }

        return null;
    }

    protected function importPreviewSummary(string $type, array $rows): array
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

    protected function importPreviewRows(string $type, array $rows): array
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

    protected function importValue(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $key = $this->normalizeImportKey($key);
            if (array_key_exists($key, $row) && is_scalar($row[$key])) {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }

    protected function importTitle(string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            return '';
        }

        $limit = self::IMPORT_TITLE_LIMIT;
        $length = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title);
        if ($length <= $limit) {
            return $title;
        }

        $suffix = '...';
        $sliceLength = $limit - strlen($suffix);
        if (function_exists('mb_substr')) {
            return rtrim(mb_substr($title, 0, $sliceLength, 'UTF-8')) . $suffix;
        }

        return rtrim(substr($title, 0, $sliceLength)) . $suffix;
    }

    protected function importStatus(string $status, string $default): string
    {
        $status = strtolower(trim($status));
        return in_array($status, ['draft', 'pending_review', 'changes_requested', 'published'], true) ? $status : $default;
    }

    protected function importBool(string $value, bool $default): bool
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return $default;
        }

        return in_array($value, ['1', 'yes', 'true', 'так', 'да'], true);
    }

    protected function uniqueSlug(string $table, string $value): string
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

    protected function limitSlug(string $value, int $limit): string
    {
        if (function_exists('mb_substr')) {
            return trim(mb_substr($value, 0, $limit), '-');
        }

        return trim(substr($value, 0, $limit), '-');
    }

    protected function textLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    protected function mediaReferences(): array
    {
        $references = [];
        $logo = Files::normalize((string) ($this->siteSettings()['site_logo'] ?? ''));
        if ($logo !== '') {
            $references[$logo] = [
                'label' => 'Логотип сайту',
                'url' => url('/admin/settings'),
            ];
        }
        try {
            $newsImages = $this->db()->fetchAll("select id, title, image_path from news where image_path is not null and image_path <> ''");
            foreach ($newsImages as $item) {
                $path = Files::normalize((string) ($item['image_path'] ?? ''));
                if ($path === '') {
                    continue;
                }
                $references[$path] = [
                    'label' => 'Головне зображення новини: ' . (string) ($item['title'] ?? ''),
                    'url' => url('/admin/news/edit?id=' . (string) ($item['id'] ?? '')),
                ];
            }
        } catch (Throwable) {
        }

        return $references;
    }

    protected function mediaListPayload(string $query, array $pagination, string $folder = ''): array
    {
        $folder = $folder === '__none' ? '__none' : MediaMetadata::normalizeFolder($folder);
        $uploadedBy = $this->canManageAllContent() ? null : $this->currentUserId();
        $references = $this->mediaReferences();
        $total = MediaMetadata::count($query, $folder, $uploadedBy);
        $items = Files::fromMetadata(MediaMetadata::search(
            $query,
            $folder,
            $pagination['limit'],
            $pagination['offset'],
            $uploadedBy
        ), $references);
        $loaded = $pagination['offset'] + count($items);
        $stats = MediaMetadata::statistics($uploadedBy);
        $used = MediaMetadata::countExistingPaths(array_keys($references), $uploadedBy);

        return [
            'ok' => true,
            'items' => $items,
            'html' => $this->view()->partial('admin/media/rows', ['items' => $items]),
            'total' => $total,
            'next_offset' => $loaded,
            'has_more' => $loaded < $total,
            'stats' => [
                'total' => $stats['total'],
                'images' => $stats['images'],
                'unused' => max(0, $stats['total'] - $used),
                'size' => $this->formatBytes($stats['size']),
            ],
            'folders' => MediaMetadata::folderNames($uploadedBy),
        ];
    }

    protected function filterOwnedMediaItems(array $items): array
    {
        if ($this->canManageAllContent()) {
            return $items;
        }

        $userId = $this->currentUserId();
        return array_values(array_filter($items, static fn (array $item): bool => (int) ($item['uploaded_by'] ?? 0) === $userId));
    }

    protected function canManageMediaItem(array $item): bool
    {
        return $this->canManageAllContent() || (int) ($item['uploaded_by'] ?? 0) === $this->currentUserId();
    }

    protected function formatBytes(int $bytes): string
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

    protected function isAjaxRequest(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    protected function isAjax(Request $request): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || (string) $request->input('_ajax', '') === '1';
    }

    protected function ajaxError(Request $request, Throwable $e): Response
    {
        if ($this->isAjax($request)) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }

        throw $e;
    }

    protected function slug(string $value): string
    {
        $value = function_exists('mb_strtolower') ? mb_strtolower(trim($value), 'UTF-8') : strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9а-яіїєґ]+/u', '-', $value);
        $value = trim($value ?: 'item', '-');
        if (function_exists('mb_substr')) {
            return trim(mb_substr($value, 0, 170), '-');
        }
        return trim(substr($value, 0, 170), '-');
    }

    protected function blocksFromText(string $text, bool $splitPlainTextTitles = true): array
    {
        $text = trim($text);
        if ($text !== strip_tags($text)) {
            $title = '';
            if (preg_match('/<h[1-4][^>]*>(.*?)<\/h[1-4]>/is', $text, $match)) {
                $title = trim(strip_tags($match[1])) ?: $title;
            }

            return [['type' => 'text', 'title' => $title, 'text' => $text]];
        }

        if (!$splitPlainTextTitles) {
            return $text !== ''
                ? [['type' => 'text', 'title' => '', 'text' => $this->plainTextToHtml($text)]]
                : [];
        }

        $blocks = [];
        foreach (preg_split('/\R{2,}/', $text) ?: [] as $part) {
            $lines = preg_split('/\R/', trim($part)) ?: [];
            $title = array_shift($lines) ?: 'Текст';
            $blocks[] = ['type' => 'text', 'title' => $title, 'text' => implode("\n", $lines)];
        }
        return $blocks ?: [['type' => 'text', 'title' => '', 'text' => '']];
    }

    protected function plainTextToHtml(string $text): string
    {
        $paragraphs = preg_split('/\R{2,}/', trim($text)) ?: [];
        $html = [];
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph !== '') {
                $html[] = '<p>' . nl2br(e($paragraph), false) . '</p>';
            }
        }

        return implode("\n", $html);
    }
}
