<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Debug;
use App\Core\Request;
use App\Core\Response;
use App\Services\Installer;

final class PublicController extends BaseController
{
    public function home(): Response
    {
        if (!Installer::installed()) {
            redirect('/install');
        }

        $page = $this->db()->fetch('select * from pages where slug = ? and status = ?', ['home', 'published']);
        return $this->renderPage($page ?: ['title' => 'Головна', 'blocks_json' => '[]']);
    }

    public function page(Request $request, array $params): Response
    {
        $page = $this->db()->fetch('select * from pages where slug = ? and status = ?', [$params['slug'], 'published']);
        if (!$page) {
            return new Response('Not found', 404);
        }
        return $this->renderPage($page);
    }

    public function news(Request $request): Response
    {
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
            'settings' => $this->siteSettings(),
            'items' => $this->db()->fetchAll(
                'select n.*, group_concat(c.title order by c.sort_order asc, c.title asc separator ", ") as category_titles
                 from news n
                 left join news_category_links l on l.news_id = n.id
                 left join news_categories c on c.id = l.category_id
                 ' . $where . '
                 group by n.id, n.title, n.slug, n.category, n.body, n.status, n.published_at, n.created_at, n.updated_at
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
        $item = $this->db()->fetch(
            'select n.*, group_concat(c.title order by c.sort_order asc, c.title asc separator ", ") as category_titles
             from news n
             left join news_category_links l on l.news_id = n.id
             left join news_categories c on c.id = l.category_id
             where n.slug = ? and n.status = ?
             group by n.id, n.title, n.slug, n.category, n.body, n.status, n.published_at, n.created_at, n.updated_at',
            [$params['slug'], 'published']
        );
        if (!$item) {
            return new Response('Not found', 404);
        }

        return $this->render('public/news-show', [
            'title' => $item['title'],
            'settings' => $this->siteSettings(),
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
            return new Response('Not found', 404);
        }

        $type = mime_content_type($file) ?: 'application/octet-stream';
        return new Response((string) file_get_contents($file), 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'inline; filename="' . basename($file) . '"',
        ]);
    }

    public function asset(Request $request, array $params): Response
    {
        $path = str_replace(['..', '\\'], '', $params['path']);
        $file = base_path('public/assets/' . $path);
        if (!is_file($file)) {
            return new Response('Not found', 404);
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
            return new Response('Not found', 404);
        }

        return $this->render('debug/show', [
            'title' => 'Debug',
            'info' => Debug::info(base_path()),
            'session' => $_SESSION,
        ], 'layouts/minimal');
    }

    private function renderPage(array $page): Response
    {
        return $this->render('public/page', [
            'title' => $page['title'],
            'settings' => $this->siteSettings(),
            'page' => $page,
            'blocks' => json_decode($page['blocks_json'] ?? '[]', true) ?: [],
            'menu' => $this->menu(),
            'latestNews' => $this->db()->fetchAll('select * from news where status = ? order by published_at desc, id desc limit 3', ['published']),
        ]);
    }

    private function menu(): array
    {
        return $this->db()->fetchAll('select title, slug from pages where status = ? order by sort_order asc, title asc', ['published']);
    }

}
