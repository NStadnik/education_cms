<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\Files;
use Throwable;

abstract class AdminBaseController extends BaseController
{
    protected const LIST_LIMIT = 20;
    protected const IMPORT_TITLE_LIMIT = 220;

    protected array $lastImportStats = [];

    protected function guard(?string $permission = null): void
    {
        Container::get('auth')->require();
        if ($permission && !Container::get('auth')->can($permission)) {
            http_response_code(403);
            exit('Forbidden');
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
            'news' => ['table' => 'news', 'template' => 'admin/news/rows', 'columns' => ['title', 'category', 'body', 'status', 'published_at'], 'order' => 'id desc'],
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

    protected function adminListStats(string $resource): array
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

    protected function publicInfoSearchWhere(string $query): array
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

    protected function publicInfoDocuments(array $sections): array
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

    protected function statusStats(string $table): array
    {
        $total = $this->count($table);
        $published = (int) ($this->db()->fetch("select count(*) as c from {$table} where status = 'published'")['c'] ?? 0);
        return ['total' => $total, 'published' => $published, 'drafts' => $total - $published];
    }

    protected function pageTemplates(): array
    {
        return [
            'default' => 'Стандартний',
            'wide' => 'Широкий контент',
            'document' => 'Документ / стаття',
        ];
    }

    protected function siteTemplates(): array
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

    protected function bulkUpdatePublicInfoRequired(array $ids, int $isRequired): void
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

    protected function bulkDeletePublicInfoSections(array $ids): int
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

    protected function bulkUpdateStatus(string $table, array $ids, string $status): void
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

    protected function bulkFillPublishedAt(string $table, array $ids): void
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
        $allowedTables = ['pages', 'news', 'documents', 'users'];
        if (!in_array($table, $allowedTables, true) || !$ids) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->db()->execute("delete from {$table} where id in ({$placeholders})", $ids);
    }

    protected function importOptions(): array
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

        $countStmt = $pdo->prepare("select count(*) as c from {$table} where {$where}");
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch(\PDO::FETCH_ASSOC)['c'] ?? 0);

        $mediaMap = [];
        if (!$preview && $this->shouldImportWordPressMedia($request)) {
            $mediaMap = $this->importWordPressMedia($pdo, $prefix, $request, !$request->input('wp_media_replace_only'));
        }

        $stmt = $pdo->prepare(
            "select ID, post_title, post_name, post_content, post_excerpt, post_type, post_status, post_date
             from {$table}
             where {$where}
             order by post_date desc, ID desc
             limit {$limit} offset {$offset}"
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

        $where = $postType === 'any' ? "post_type in ('post', 'page')" : 'post_type = ?';
        $params = $postType === 'any' ? [] : [$postType];
        if ($status !== 'any') {
            $where .= ' and post_status = ?';
            $params[] = $status;
        }

        $search = trim((string) $request->input('wp_search', ''));
        if ($search !== '') {
            $where .= ' and (post_title like ? or post_content like ? or post_name like ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }

        $dateFrom = trim((string) $request->input('wp_date_from', ''));
        if ($dateFrom !== '') {
            $where .= ' and post_date >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }

        $dateTo = trim((string) $request->input('wp_date_to', ''));
        if ($dateTo !== '') {
            $where .= ' and post_date <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        return [$where, $params];
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
        $maxLimit = $download ? 5000 : 50000;
        $limitInput = $request->input('wp_media_map_limit', $request->input('wp_media_limit', 1000));
        $limit = max(1, min($maxLimit, (int) $limitInput));

        $countStmt = $pdo->query("select count(*) as c from {$postsTable} where post_type = 'attachment'");
        $total = (int) ($countStmt->fetch(\PDO::FETCH_ASSOC)['c'] ?? 0);

        $stmt = $pdo->prepare(
            "select p.ID, p.guid, p.post_title, p.post_name, p.post_mime_type, m.meta_value as attached_file, mm.meta_value as attachment_metadata
             from {$postsTable} p
             left join {$metaTable} m on m.post_id = p.ID and m.meta_key = '_wp_attached_file'
             left join {$metaTable} mm on mm.post_id = p.ID and mm.meta_key = '_wp_attachment_metadata'
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

            $savedPath = $this->saveImportedMedia($source, $relativeSource, $sourceUrl, (int) ($attachment['ID'] ?? 0), $download);
            if ($savedPath === '') {
                continue;
            }
            $saved++;

            $newUrl = url('/uploads/' . $savedPath);
            foreach ($this->wordPressMediaAliases($relativeSource, $sourceUrl, $siteUrl, (string) ($attachment['attachment_metadata'] ?? ''), (string) ($attachment['post_name'] ?? ''), (int) ($attachment['ID'] ?? 0)) as $alias) {
                $map[$alias] = $newUrl;
            }
        }

        uksort($map, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $this->lastImportStats = array_replace($this->lastImportStats, [
            'media_total' => $total,
            'media_offset' => $offset,
            'media_limit' => $limit,
            'media_loaded' => $loaded,
            'media_imported' => $saved,
            'media_next_offset' => $offset + $loaded,
            'media_has_more' => ($offset + $loaded) < $total,
        ]);
        return $map;
    }

    protected function wordPressMediaSource(string $relativeSource, string $sourceUrl, string $siteUrl, string $uploadsPath): string
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

    protected function saveImportedMedia(string $source, string $relativeSource, string $sourceUrl, int $id, bool $download = true): string
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
        if (!$download) {
            return '';
        }

        $context = stream_context_create(['http' => ['timeout' => 15], 'https' => ['timeout' => 15]]);
        $contents = is_file($source) ? file_get_contents($source) : @file_get_contents($source, false, $context);
        if ($contents === false || $contents === '') {
            return '';
        }

        file_put_contents($target, $contents);
        return $targetRelative;
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

    protected function importRows(string $type, array $rows): int
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

    protected function importWordPressRows(array $rows): int
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

    protected function importNewsRows(array $rows): int
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

            $status = $this->importStatus($this->importValue($row, ['status', 'статус']), 'draft');
            $publishedAt = $this->importValue($row, ['published_at', 'дата_публікації', 'дата']);
            if ($status === 'published' && $publishedAt === '') {
                $publishedAt = $now;
            }
            $category = $this->importValue($row, ['category', 'категорія', 'rubric', 'рубрика']) ?: 'Загальні';
            $this->ensureImportedNewsCategory($category, $now);

            $this->db()->execute(
                'insert into news (title, slug, category, body, status, published_at, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $title,
                    $this->uniqueSlug('news', $slugSource),
                    $category,
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

    protected function ensureImportedNewsCategory(string $title, string $now): void
    {
        $title = trim($title);
        if ($title === '' || $this->db()->fetch('select id from news_categories where title = ?', [$title])) {
            return;
        }

        $this->db()->execute(
            'insert into news_categories (title, slug, sort_order, created_at, updated_at) values (?, ?, ?, ?, ?)',
            [$title, $this->uniqueSlug('news_categories', $title), 100, $now, $now]
        );
    }

    protected function importPageRows(array $rows): int
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
                    $this->uniqueSlug('pages', $slugSource),
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

    protected function importDocumentRows(array $rows): int
    {
        $created = 0;
        $now = date('c');
        foreach ($rows as $row) {
            $title = $this->importValue($row, ['title', 'назва', 'заголовок']);
            if ($title === '') {
                continue;
            }
            $title = $this->importTitle($title);

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

    protected function importPublicInfoSectionRows(array $rows): int
    {
        $created = 0;
        foreach ($rows as $row) {
            $title = $this->importValue($row, ['title', 'назва', 'заголовок']);
            if ($title === '') {
                continue;
            }
            $slugSource = $this->importValue($row, ['slug', 'адреса']) ?: $title;
            $title = $this->importTitle($title);

            $this->db()->execute(
                'insert into public_info_sections (title, slug, description, is_required, sort_order) values (?, ?, ?, ?, ?)',
                [
                    $title,
                    $this->uniqueSlug('public_info_sections', $slugSource),
                    $this->importValue($row, ['description', 'опис']),
                    $this->importBool($this->importValue($row, ['is_required', 'обовязковий', 'обов_язковий']), true) ? 1 : 0,
                    (int) ($this->importValue($row, ['sort_order', 'порядок']) ?: 0),
                ]
            );
            $created++;
        }

        return $created;
    }

    protected function importGlobalFieldRows(array $rows): int
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
        return in_array($status, ['draft', 'published'], true) ? $status : $default;
    }

    protected function importBool(string $value, bool $default): bool
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return $default;
        }

        return in_array($value, ['1', 'yes', 'true', 'так', 'да'], true);
    }

    protected function resolvePublicInfoSectionId(string $value): ?int
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

    protected function filterMedia(array $items, string $query): array
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

    protected function mediaListPayload(string $query, array $pagination): array
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

    protected function mediaStats(array $items): array
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
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9а-яіїєґ]+/u', '-', $value);
        $value = trim($value ?: 'item', '-');
        if (function_exists('mb_substr')) {
            return trim(mb_substr($value, 0, 170), '-');
        }
        return trim(substr($value, 0, 170), '-');
    }

    protected function blocksFromText(string $text): array
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
