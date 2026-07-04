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

    public function news(): Response
    {
        return $this->render('public/news', [
            'title' => 'Новини',
            'settings' => $this->siteSettings(),
            'items' => $this->db()->fetchAll('select * from news where status = ? order by published_at desc, id desc', ['published']),
            'menu' => $this->menu(),
        ]);
    }

    public function newsShow(Request $request, array $params): Response
    {
        $item = $this->db()->fetch('select * from news where slug = ? and status = ?', [$params['slug'], 'published']);
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

    public function documents(): Response
    {
        return $this->render('public/documents', [
            'title' => 'Документи',
            'settings' => $this->siteSettings(),
            'items' => $this->db()->fetchAll('select * from documents where status = ? order by category, created_at desc', ['published']),
            'menu' => $this->menu(),
        ]);
    }

    public function publicInfo(): Response
    {
        $sections = $this->db()->fetchAll(
            'select s.*, count(d.id) as documents_count, max(d.updated_at) as last_document_at
             from public_info_sections s
             left join documents d on d.public_info_section_id = s.id and d.status = \'published\'
             group by s.id, s.title, s.slug, s.description, s.is_required, s.sort_order
             order by s.sort_order asc'
        );
        $documents = $this->db()->fetchAll(
            'select d.*, s.slug as section_slug, s.title as section_title
             from documents d
             left join public_info_sections s on s.id = d.public_info_section_id
             where d.public_info_section_id is not null and d.status = \'published\'
             order by s.sort_order asc, d.published_at desc, d.updated_at desc'
        );

        return $this->render('public/public-info', [
            'title' => 'Публічна інформація',
            'settings' => $this->siteSettings(),
            'sections' => $sections,
            'documentsBySection' => $this->groupBy($documents, 'public_info_section_id'),
            'menu' => $this->menu(),
        ]);
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
            'publicInfoStats' => $this->publicInfoStats(),
        ]);
    }

    private function menu(): array
    {
        return $this->db()->fetchAll('select title, slug from pages where status = ? order by sort_order asc, title asc', ['published']);
    }

    private function publicInfoStats(): array
    {
        $total = (int) ($this->db()->fetch('select count(*) as c from public_info_sections')['c'] ?? 0);
        $filled = (int) ($this->db()->fetch("select count(distinct public_info_section_id) as c from documents where status = 'published' and public_info_section_id is not null")['c'] ?? 0);
        return ['total' => $total, 'filled' => $filled, 'percent' => $total ? (int) round($filled / $total * 100) : 0];
    }

    private function groupBy(array $items, string $key): array
    {
        $grouped = [];
        foreach ($items as $item) {
            $grouped[$item[$key]][] = $item;
        }
        return $grouped;
    }
}
