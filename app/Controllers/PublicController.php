<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Debug;
use App\Core\Request;
use App\Core\Response;
use App\Services\Installer;
use App\Services\Files;
use App\Services\MediaMetadata;
use App\Services\SiteThemes;
use App\Services\SeoMetadata;
use App\Services\Thumbnails;

final class PublicController extends BaseController
{
    public function search(Request $request): Response
    {
        $settings = $this->siteSettings();
        if ($response = $this->siteModeResponse($settings)) {
            return $response;
        }

        $query = trim((string) $request->input('q', ''));
        $queryLength = function_exists('mb_strlen') ? mb_strlen($query) : strlen($query);
        $minimumQueryLength = 3;
        $limit = 8;
        $offsets = [
            'pages' => max(0, (int) $request->input('pages_offset', 0)),
            'news' => max(0, (int) $request->input('news_offset', 0)),
            'categories' => max(0, (int) $request->input('categories_offset', 0)),
            'media' => max(0, (int) $request->input('media_offset', 0)),
        ];
        $pages = [];
        $news = [];
        $categories = [];
        $media = [];
        $totals = ['pages' => 0, 'news' => 0, 'categories' => 0, 'media' => 0];
        if ($queryLength >= $minimumQueryLength) {
            $like = '%' . $query . '%';
            $pagesWhere = 'where status = ? and (title like ? or slug like ? or excerpt like ? or blocks_json like ?)';
            $pagesParams = ['published', $like, $like, $like, $like];
            $totals['pages'] = (int) ($this->cachedFetch('select count(*) as c from pages ' . $pagesWhere, $pagesParams)['c'] ?? 0);
            $pages = $this->cachedFetchAll(
                'select id, title, slug, excerpt from pages ' . $pagesWhere . ' order by sort_order asc, title asc limit ' . $limit . ' offset ' . $offsets['pages'],
                $pagesParams
            );
            $newsWhere = 'where n.status = ? and (n.title like ? or n.slug like ? or n.body like ? or n.category like ?)';
            $newsParams = ['published', $like, $like, $like, $like];
            $totals['news'] = (int) ($this->cachedFetch('select count(distinct n.id) as c from news n ' . $newsWhere, $newsParams)['c'] ?? 0);
            $news = $this->cachedFetchAll(
                'select distinct n.id, n.title, n.slug, n.body, n.image_path, n.published_at from news n ' . $newsWhere . ' order by n.published_at desc, n.id desc limit ' . $limit . ' offset ' . $offsets['news'],
                $newsParams
            );
            $categoryWhere = 'from news_categories c inner join news_category_links l on l.category_id = c.id inner join news n on n.id = l.news_id and n.status = ? where c.title like ? or c.slug like ?';
            $categoryParams = ['published', $like, $like];
            $totals['categories'] = (int) ($this->cachedFetch('select count(distinct c.id) as c ' . $categoryWhere, $categoryParams)['c'] ?? 0);
            $categories = $this->cachedFetchAll(
                'select c.id, c.title, c.slug, count(distinct n.id) as items_count ' . $categoryWhere . ' group by c.id, c.title, c.slug, c.sort_order order by c.sort_order asc, c.title asc limit ' . $limit . ' offset ' . $offsets['categories'],
                $categoryParams
            );
            $totals['media'] = MediaMetadata::count($query);
            $media = Files::fromMetadata(MediaMetadata::search($query, '', $limit, $offsets['media']));
        }

        $items = compact('pages', 'news', 'categories', 'media');
        $nextOffsets = [];
        $hasMore = [];
        foreach ($items as $type => $typeItems) {
            $nextOffsets[$type] = $offsets[$type] + count($typeItems);
            $hasMore[$type] = $nextOffsets[$type] < $totals[$type];
        }

        if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
            $html = [];
            foreach ($items as $type => $typeItems) {
                $html[$type] = $this->view()->partial('public/partials/search-items', ['type' => $type, 'items' => $typeItems]);
            }
            return $this->json([
                'ok' => $queryLength >= $minimumQueryLength,
                'minimum_length' => $minimumQueryLength,
                'html' => $html,
                'next_offsets' => $nextOffsets,
                'has_more' => $hasMore,
            ], $queryLength >= $minimumQueryLength ? 200 : 422);
        }

        return $this->render('public/search', [
            'title' => $query !== '' ? 'Пошук — ' . $query : 'Пошук',
            'settings' => $settings,
            'query' => $query,
            'pages' => $pages,
            'news' => $news,
            'categories' => $categories,
            'media' => $media,
            'totals' => $totals,
            'nextOffsets' => $nextOffsets,
            'hasMore' => $hasMore,
            'minimumQueryLength' => $minimumQueryLength,
            'menu' => $this->menu(),
        ]);
    }

    public function home(): Response
    {
        if (!Installer::installed()) {
            redirect('/install');
        }

        $settings = $this->siteSettings();
        if ($response = $this->siteModeResponse($settings)) {
            return $response;
        }

        $homePageId = (int) ($settings['home_page_id'] ?? 0);
        $page = null;
        if ($homePageId > 0) {
            $page = $this->cachedFetch('select * from pages where id = ? and status = ?', [$homePageId, 'published']);
        }
        $page ??= $this->cachedFetch('select * from pages where slug = ? and status = ?', ['home', 'published']);
        return $this->renderPage($page ?: ['title' => 'Головна', 'blocks_json' => '[]'], true, $settings);
    }

    public function page(Request $request, array $params): Response
    {
        $settings = $this->siteSettings();
        if ($response = $this->siteModeResponse($settings)) {
            return $response;
        }

        $page = $this->cachedFetch('select * from pages where slug = ? and status = ?', [$params['slug'], 'published']);
        if (!$page) {
            return ErrorController::response(404);
        }
        return $this->renderPage($page, false, $settings);
    }

    public function news(Request $request): Response
    {
        $settings = $this->siteSettings();
        if ($response = $this->siteModeResponse($settings)) {
            return $response;
        }

        $category = trim((string) $request->input('category', ''));
        $query = trim((string) $request->input('q', ''));
        $limit = 9;
        $page = max(1, (int) $request->input('page', 1));
        $where = 'where n.status = ?';
        $params = ['published'];
        if ($category !== '') {
            $where .= ' and exists (
                select 1
                from news_category_links fl
                inner join news_categories fc on fc.id = fl.category_id
                where fl.news_id = n.id and fc.title = ?
            )';
            $params[] = $category;
        }
        if ($query !== '') {
            $where .= ' and (
                n.title like ? or n.body like ? or n.category like ? or exists (
                    select 1
                    from news_category_links sl
                    inner join news_categories sc on sc.id = sl.category_id
                    where sl.news_id = n.id and sc.title like ?
                )
            )';
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $total = (int) ($this->cachedFetch(
            'select count(distinct n.id) as c
             from news n
             left join news_category_links l on l.news_id = n.id
             left join news_categories c on c.id = l.category_id
             ' . $where,
            $params
        )['c'] ?? 0);
        $pages = max(1, (int) ceil($total / $limit));
        $hasOffset = array_key_exists('offset', $request->query) || array_key_exists('offset', $request->post);
        if (!$hasOffset) {
            $page = min($page, $pages);
        }
        $offset = max(0, (int) $request->input('offset', ($page - 1) * $limit));

        $items = $this->cachedFetchAll(
            'select n.*, group_concat(c.title order by c.sort_order asc, c.title asc separator ", ") as category_titles
             from news n
             left join news_category_links l on l.news_id = n.id
             left join news_categories c on c.id = l.category_id
             ' . $where . '
             group by n.id, n.created_by, n.title, n.slug, n.category, n.image_path, n.body, n.status, n.published_at, n.created_at, n.updated_at
             order by n.published_at desc, n.id desc
             limit ' . $limit . ' offset ' . $offset,
            $params
        );
        $loaded = $offset + count($items);
        $currentPage = min(max(1, (int) floor($offset / $limit) + 1), $pages);
        $newsUrl = static function (?string $urlCategory = null, string $urlQuery = '', int $targetPage = 1): string {
            $urlParams = [];
            if ($urlCategory !== null && $urlCategory !== '') {
                $urlParams['category'] = $urlCategory;
            }
            if ($urlQuery !== '') {
                $urlParams['q'] = $urlQuery;
            }
            if ($targetPage > 1) {
                $urlParams['page'] = $targetPage;
            }
            return url('/news' . ($urlParams ? '?' . http_build_query($urlParams) : ''));
        };
        $pageUrl = static fn (int $targetPage): string => $newsUrl($category, $query, $targetPage);
        $categories = $this->newsCategories();

        if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
            return $this->json([
                'ok' => true,
                'html' => $this->view()->partial('public/partials/news-cards', ['items' => $items]),
                'pager_html' => $this->view()->partial('public/partials/pager', [
                    'currentPage' => $currentPage,
                    'pages' => $pages,
                    'urlFactory' => $pageUrl,
                    'label' => 'Навігація сторінками новин',
                    'jumpLabel' => 'Сторінка',
                    'class' => 'news-pager',
                ]),
                'categories_html' => $this->view()->partial('public/partials/news-categories', [
                    'categories' => $categories,
                    'activeCategory' => $category,
                    'activeQuery' => $query,
                    'newsUrl' => $newsUrl,
                ]),
                'total' => $total,
                'category' => $category,
                'query' => $query,
                'current_page' => $currentPage,
                'page_url' => $pageUrl($currentPage),
                'next_offset' => $loaded,
                'has_more' => $loaded < $total,
            ]);
        }

        return $this->render('public/news', [
            'title' => $category !== '' ? 'Новини — ' . $category : 'Новини',
            'seo' => SeoMetadata::newsList($settings, $category, $query, $currentPage),
            'settings' => $settings,
            'items' => $items,
            'total' => $total,
            'limit' => $limit,
            'page' => $page,
            'categories' => $categories,
            'activeCategory' => $category,
            'activeQuery' => $query,
            'menu' => $this->menu(),
            'adminToolbar' => $this->adminToolbar('news-list'),
        ]);
    }

    public function newsShow(Request $request, array $params): Response
    {
        $settings = $this->siteSettings();
        if ($response = $this->siteModeResponse($settings)) {
            return $response;
        }

        $item = $this->cachedFetch(
            'select n.*, group_concat(c.title order by c.sort_order asc, c.title asc separator ", ") as category_titles
             from news n
             left join news_category_links l on l.news_id = n.id
             left join news_categories c on c.id = l.category_id
             where n.slug = ? and n.status = ?
             group by n.id, n.title, n.slug, n.category, n.image_path, n.body, n.status, n.published_at, n.created_at, n.updated_at',
            [$params['slug'], 'published']
        );
        if (!$item) {
            return ErrorController::response(404);
        }

        $this->recordNewsView((int) $item['id']);
        $item['views_count'] = (int) ($this->db()->fetch('select views_count from news where id = ?', [(int) $item['id']])['views_count'] ?? 0);

        return $this->render('public/news-show', [
            'title' => $item['title'],
            'seo' => SeoMetadata::news($item, $settings),
            'settings' => $settings,
            'item' => $item,
            'menu' => $this->menu(),
            'adminToolbar' => $this->adminToolbar('news', $item),
        ]);
    }

    private function newsCategories(): array
    {
        return $this->cachedFetchAll(
            "select c.title as category, count(n.id) as items_count
             from news_categories c
             inner join news_category_links l on l.category_id = c.id
             inner join news n on n.id = l.news_id and n.status = ?
             group by c.id, c.title, c.sort_order
             order by c.sort_order asc, c.title asc",
            ['published']
        );
    }

    private function recordNewsView(int $newsId): bool
    {
        if (Container::get('auth')->user()
            || preg_match('/bot|crawl|spider|slurp|preview/i', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
            return false;
        }
        $today = date('Y-m-d');
        $viewed = is_array($_SESSION['news_views'] ?? null) ? $_SESSION['news_views'] : [];
        if (($viewed[$newsId] ?? '') === $today) {
            return false;
        }

        $transactionStarted = false;
        try {
            $this->db()->pdo()->beginTransaction();
            $transactionStarted = true;
            $this->db()->execute('update news set views_count = views_count + 1 where id = ? and status = ?', [$newsId, 'published']);
            $this->db()->execute(
                'insert into news_view_stats (news_id, view_date, views_count) values (?, ?, 1)
                 on duplicate key update views_count = views_count + 1',
                [$newsId, $today]
            );
            $this->db()->pdo()->commit();
        } catch (\Throwable) {
            if ($transactionStarted && $this->db()->pdo()->inTransaction()) {
                $this->db()->pdo()->rollBack();
            }
            return false;
        }

        $viewed[$newsId] = $today;
        $_SESSION['news_views'] = array_slice($viewed, -200, null, true);
        return true;
    }

    public function upload(Request $request, array $params): Response
    {
        $path = str_replace(['..', '\\'], '', $params['path']);
        $file = base_path('storage/uploads/' . $path);
        if (!is_file($file)) {
            return ErrorController::response(404);
        }

        $type = mime_content_type($file) ?: 'application/octet-stream';
        return $this->fileResponse($file, $type, 'public, max-age=604800', [
            'Content-Disposition' => 'inline; filename="' . basename($file) . '"',
        ]);
    }

    public function thumb(Request $request, array $params): Response
    {
        try {
            $thumb = Thumbnails::make(
                (string) ($params['path'] ?? ''),
                (int) $request->input('w', 320),
                (int) $request->input('h', 240),
                (string) $request->input('fit', 'crop')
            );
        } catch (\Throwable $exception) {
            return new Response($exception->getMessage(), 404);
        }

        return $this->fileResponse((string) $thumb['path'], (string) $thumb['mime'], 'public, max-age=604800');
    }

    public function asset(Request $request, array $params): Response
    {
        $path = str_replace(['..', '\\'], '', $params['path']);
        $file = base_path('public/assets/' . $path);
        if (!is_file($file)) {
            return ErrorController::response(404);
        }

        $types = [
            'css' => 'text/css; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
        ];
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return $this->fileResponse($file, $types[$extension] ?? 'application/octet-stream', 'public, max-age=86400');
    }

    public function debug(): Response
    {
        if (!Debug::enabled(base_path())) {
            return ErrorController::response(404);
        }

        return $this->render('debug/show', [
            'title' => 'Debug',
            'info' => Debug::info(base_path()),
            'session' => $_SESSION,
        ], 'layouts/minimal');
    }

    private function fileResponse(string $file, string $contentType, string $cacheControl, array $headers = []): Response
    {
        $modifiedAt = (int) filemtime($file);
        $size = max(0, (int) filesize($file));
        $etag = '"' . sha1($file . '|' . $modifiedAt . '|' . $size) . '"';
        $lastModified = gmdate('D, d M Y H:i:s', $modifiedAt) . ' GMT';

        $headers = array_replace([
            'Content-Type' => $contentType,
            'Cache-Control' => $cacheControl,
            'ETag' => $etag,
            'Last-Modified' => $lastModified,
        ], $headers);

        if ($this->notModified($etag, $modifiedAt)) {
            return new Response('', 304, $headers);
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return ErrorController::response(404);
        }

        $headers['Content-Length'] = (string) strlen($content);
        return new Response($content, 200, $headers);
    }

    private function notModified(string $etag, int $modifiedAt): bool
    {
        $ifNoneMatch = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        if ($ifNoneMatch !== '') {
            $matches = array_map(
                static fn (string $value): string => preg_replace('/^W\//', '', trim($value)) ?? '',
                explode(',', $ifNoneMatch)
            );
            return in_array('*', $matches, true) || in_array($etag, $matches, true);
        }

        $ifModifiedSince = (string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '');
        if ($ifModifiedSince === '') {
            return false;
        }

        $since = strtotime($ifModifiedSince);
        return $since !== false && $modifiedAt <= $since;
    }

    private function renderPage(array $page, bool $isHomePage = false, ?array $settings = null): Response
    {
        $settings ??= $this->siteSettings();
        $blocks = json_decode($page['blocks_json'] ?? '[]', true) ?: [];
        $formIds = [];
        array_walk_recursive($blocks, static function ($value, $key) use (&$formIds): void {
            if ($key === 'form_id' && (int) $value > 0) { $formIds[] = (int) $value; }
        });
        $formsById = [];
        $formIds = array_values(array_unique($formIds));
        if ($formIds) {
            $placeholders = implode(',', array_fill(0, count($formIds), '?'));
            foreach ($this->db()->fetchAll('select * from forms where status=? and id in (' . $placeholders . ')', ['published', ...$formIds]) as $form) {
                $formsById[(int) $form['id']] = $form;
            }
        }
        return $this->render('public/page', [
            'title' => $page['title'],
            'seo' => SeoMetadata::page($page, $settings, $isHomePage ? '/' : '/page/' . (string) ($page['slug'] ?? '')),
            'settings' => $settings,
            'page' => $page,
            'isHomePage' => $isHomePage,
            'homeHeroVisible' => $isHomePage && $this->homeHeroVisible($settings),
            'blocks' => $blocks,
            'formsById' => $formsById,
            'menu' => $this->menu(),
            'latestNews' => $this->cachedFetchAll('select * from news where status = ? order by published_at desc, id desc limit 3', ['published']),
            'adminToolbar' => $this->adminToolbar('page', $page),
        ]);
    }

    private function siteModeResponse(array $settings): ?Response
    {
        if (Container::get('auth')->user()) {
            return null;
        }

        $mode = (string) ($settings['site_mode'] ?? 'online');
        if ($mode === 'online') {
            return null;
        }

        $modes = [
            'maintenance' => [
                'label' => 'Режим обслуговування',
                'title' => 'Сайт тимчасово на обслуговуванні',
                'message' => 'Ми оновлюємо сайт і скоро повернемо його до роботи. Дякуємо за розуміння.',
                'status' => 503,
                'accent' => 'blue',
            ],
            'coming_soon' => [
                'label' => 'Скоро відкриття',
                'title' => 'Сайт готується до відкриття',
                'message' => 'Ми завершуємо підготовку матеріалів. Завітайте трохи пізніше.',
                'status' => 503,
                'accent' => 'green',
            ],
            'private' => [
                'label' => 'Закритий доступ',
                'title' => 'Сайт доступний лише адміністраторам',
                'message' => 'Публічний доступ тимчасово закрито. Авторизовані користувачі можуть увійти в панель керування.',
                'status' => 403,
                'accent' => 'dark',
            ],
        ];
        $config = $modes[$mode] ?? null;
        if (!$config) {
            return null;
        }

        $title = trim((string) ($settings['site_mode_title'] ?? '')) ?: $config['title'];
        $message = trim((string) ($settings['site_mode_message'] ?? '')) ?: $config['message'];
        $content = $this->view()->render('public/site-mode', [
            'title' => $title,
            'settings' => $settings,
            'mode' => $mode,
            'modeLabel' => $config['label'],
            'modeTitle' => $title,
            'modeMessage' => $message,
            'modeAccent' => $config['accent'],
        ], 'layouts/minimal');

        $headers = [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, max-age=0',
        ];
        if ((int) $config['status'] === 503) {
            $headers['Retry-After'] = '3600';
        }

        return new Response($content, (int) $config['status'], $headers);
    }

    private function homeHeroVisible(array $settings): bool
    {
        $siteTemplateKey = preg_replace('/[^a-z0-9_-]/i', '', (string) ($settings['site_template'] ?? 'official')) ?: 'official';
        $siteTheme = SiteThemes::get($siteTemplateKey);
        $siteTemplate = (string) ($siteTheme['key'] ?? $siteTemplateKey);
        $templateLayouts = json_decode((string) ($settings['site_template_layouts'] ?? ''), true);
        $templateLayouts = is_array($templateLayouts) ? $templateLayouts : [];
        $legacyHeaderLayout = json_decode((string) ($settings['site_header_layout'] ?? ''), true);
        $headerLayout = is_array($templateLayouts[$siteTemplate]['header'] ?? null)
            ? $templateLayouts[$siteTemplate]['header']
            : (is_array($legacyHeaderLayout) ? $legacyHeaderLayout : []);

        return !empty($headerLayout['home_hero_enabled'])
            && (trim((string) ($headerLayout['home_hero_title'] ?? '')) !== '' || trim((string) ($headerLayout['home_hero_text'] ?? '')) !== '');
    }

    private function menu(): array
    {
        $settings = $this->siteSettings();
        $homePageId = (int) ($settings['home_page_id'] ?? 0);
        if ($homePageId > 0) {
            return $this->cachedFetchAll('select title, slug from pages where status = ? and id <> ? order by sort_order asc, title asc', ['published', $homePageId]);
        }

        return $this->cachedFetchAll('select title, slug from pages where status = ? and slug <> ? order by sort_order asc, title asc', ['published', 'home']);
    }

    protected function siteSettings(): array
    {
        $rows = $this->cachedFetchAll('select name, value from settings');
        return array_column($rows, 'value', 'name');
    }

    private function cachedFetch(string $sql, array $params = [], int $ttl = 600): ?array
    {
        return $this->db()->cachedFetch('public_site', $sql, $params, $ttl);
    }

    private function cachedFetchAll(string $sql, array $params = [], int $ttl = 600): array
    {
        return $this->db()->cachedFetchAll('public_site', $sql, $params, $ttl);
    }

}
