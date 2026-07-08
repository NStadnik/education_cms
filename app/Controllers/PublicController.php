<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Debug;
use App\Core\Request;
use App\Core\Response;
use App\Services\Installer;
use App\Services\SiteThemes;
use App\Services\Thumbnails;

final class PublicController extends BaseController
{
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
            $page = $this->db()->fetch('select * from pages where id = ? and status = ?', [$homePageId, 'published']);
        }
        $page ??= $this->db()->fetch('select * from pages where slug = ? and status = ?', ['home', 'published']);
        return $this->renderPage($page ?: ['title' => 'Головна', 'blocks_json' => '[]'], true, $settings);
    }

    public function page(Request $request, array $params): Response
    {
        $settings = $this->siteSettings();
        if ($response = $this->siteModeResponse($settings)) {
            return $response;
        }

        $page = $this->db()->fetch('select * from pages where slug = ? and status = ?', [$params['slug'], 'published']);
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

        return $this->render('public/news', [
            'title' => 'Новини',
            'settings' => $settings,
            'items' => $this->db()->fetchAll(
                'select n.*, group_concat(c.title order by c.sort_order asc, c.title asc separator ", ") as category_titles
                 from news n
                 left join news_category_links l on l.news_id = n.id
                 left join news_categories c on c.id = l.category_id
                 ' . $where . '
                 group by n.id, n.title, n.slug, n.category, n.image_path, n.body, n.status, n.published_at, n.created_at, n.updated_at
                 order by n.published_at desc, n.id desc',
                $params
            ),
            'categories' => $this->newsCategories(),
            'activeCategory' => $category,
            'menu' => $this->menu(),
        ]);
    }

    public function newsShow(Request $request, array $params): Response
    {
        $settings = $this->siteSettings();
        if ($response = $this->siteModeResponse($settings)) {
            return $response;
        }

        $item = $this->db()->fetch(
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

        return $this->render('public/news-show', [
            'title' => $item['title'],
            'settings' => $settings,
            'item' => $item,
            'menu' => $this->menu(),
        ]);
    }

    private function newsCategories(): array
    {
        return $this->db()->fetchAll(
            "select c.title as category, count(n.id) as items_count
             from news_categories c
             inner join news_category_links l on l.category_id = c.id
             inner join news n on n.id = l.news_id and n.status = ?
             group by c.id, c.title, c.sort_order
             order by c.sort_order asc, c.title asc",
            ['published']
        );
    }

    public function upload(Request $request, array $params): Response
    {
        $path = str_replace(['..', '\\'], '', $params['path']);
        $file = base_path('storage/uploads/' . $path);
        if (!is_file($file)) {
            return ErrorController::response(404);
        }

        $type = mime_content_type($file) ?: 'application/octet-stream';
        return new Response((string) file_get_contents($file), 200, [
            'Content-Type' => $type,
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

        return new Response((string) file_get_contents($thumb['path']), 200, [
            'Content-Type' => $thumb['mime'],
            'Cache-Control' => 'public, max-age=604800',
        ]);
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

        return new Response((string) file_get_contents($file), 200, [
            'Content-Type' => $types[$extension] ?? 'application/octet-stream',
            'Cache-Control' => 'public, max-age=86400',
        ]);
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

    private function renderPage(array $page, bool $isHomePage = false, ?array $settings = null): Response
    {
        $settings ??= $this->siteSettings();
        return $this->render('public/page', [
            'title' => $page['title'],
            'settings' => $settings,
            'page' => $page,
            'isHomePage' => $isHomePage,
            'homeHeroVisible' => $isHomePage && $this->homeHeroVisible($settings),
            'blocks' => json_decode($page['blocks_json'] ?? '[]', true) ?: [],
            'menu' => $this->menu(),
            'latestNews' => $this->db()->fetchAll('select * from news where status = ? order by published_at desc, id desc limit 3', ['published']),
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
            return $this->db()->fetchAll('select title, slug from pages where status = ? and id <> ? order by sort_order asc, title asc', ['published', $homePageId]);
        }

        return $this->db()->fetchAll('select title, slug from pages where status = ? and slug <> ? order by sort_order asc, title asc', ['published', 'home']);
    }

}
