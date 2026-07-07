<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\Files;
use Throwable;

final class SettingsController extends \App\Controllers\AdminBaseController
{
    public function settings(): Response
    {
        $this->guard('settings.manage');
        return $this->admin('admin/settings', [
            'title' => 'Налаштування',
            'settings' => $this->siteSettings(),
            'globalFields' => $this->globalFields(),
            'homePages' => $this->homePageOptions(),
            'siteTemplates' => $this->siteTemplates(),
        ]);
    }

    public function settingsSave(Request $request): Response
    {
        $this->guard('settings.manage');
        Csrf::verify();
        try {
            $this->saveSetting('institution_name', (string) $request->input('institution_name'));
            $this->saveSetting('site_logo', $this->normalizeSiteLogo((string) $request->input('site_logo', '')));
            $this->saveSetting('home_page_id', $this->normalizeHomePageId((int) $request->input('home_page_id', 0)));
            $this->saveSetting('site_template', $this->normalizeSiteTemplate((string) $request->input('site_template', 'official')));
            $globalFields = json_encode($this->normalizeGlobalFields($request), JSON_UNESCAPED_UNICODE);
            $this->saveSetting('global_fields', $globalFields === false ? '[]' : $globalFields);

            if ($this->isAjax($request)) {
                return $this->json(['ok' => true, 'message' => 'Налаштування збережено.']);
            }

            redirect('/admin/settings');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
        }
    }

    public function templates(): Response
    {
        $this->guard('settings.manage');
        return $this->admin('admin/templates/index', [
            'title' => 'Шаблони сайту',
            'settings' => $this->siteSettings(),
            'siteTemplates' => $this->siteTemplates(),
            'previewMenu' => $this->templatePreviewMenu(),
            'previewHomePage' => $this->templatePreviewHomePage(),
            'previewGlobalFields' => $this->globalFields(),
            'templateLinkPicker' => $this->templateLinkPicker(),
            'templatePickerCategories' => $this->templatePickerCategories(),
        ]);
    }

    public function templatesSave(Request $request): Response
    {
        $this->guard('settings.manage');
        Csrf::verify();
        try {
            $editorTemplate = $this->normalizeSiteTemplate((string) $request->input('template_editor_key', (string) ($this->siteSettings()['site_template'] ?? 'official')));
            $this->saveSetting('site_template_layouts', $this->encodeTemplateLayout($this->normalizeTemplateLayouts((string) $request->input('site_template_layouts', ''), $editorTemplate)));

            if ($this->isAjax($request)) {
                return $this->json(['ok' => true, 'message' => 'Шаблон сайту збережено.']);
            }

            redirect('/admin/templates');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
        }
    }

    public function templatesLinkPicker(Request $request): Response
    {
        $this->guard('settings.manage');
        $type = (string) $request->input('type', 'pages');
        if (!in_array($type, ['pages', 'categories', 'news', 'media'], true)) {
            return $this->json(['ok' => false, 'message' => 'Невідомий тип посилання.'], 422);
        }

        $query = trim((string) $request->input('q', ''));
        $pagination = $this->pagination($request);
        $payload = match ($type) {
            'categories' => $this->templateCategoryPickerPayload($request, $query, $pagination),
            'media' => $this->templateMediaPickerPayload($query, $pagination),
            'news' => $this->templateNewsPickerPayload($request, $query, $pagination),
            default => $this->templatePagePickerPayload($request, $query, $pagination),
        };

        return $this->json(array_replace(['ok' => true, 'type' => $type], $payload));
    }

    private function normalizeTemplateLayouts(string $json, string $selectedTemplate): array
    {
        $data = json_decode($json, true);
        $data = is_array($data) ? $data : [];
        $themes = $this->siteTemplates();
        $layouts = [];

        foreach ($themes as $key => $theme) {
            $templateLayout = is_array($data[$key] ?? null) ? $data[$key] : [];
            if (!$templateLayout && $key !== $selectedTemplate) {
                continue;
            }

            $layouts[$key] = [
                'header' => $this->normalizeHeaderLayout($this->encodeTemplateLayout(is_array($templateLayout['header'] ?? null) ? $templateLayout['header'] : [])),
                'footer' => $this->normalizeFooterLayout($this->encodeTemplateLayout(is_array($templateLayout['footer'] ?? null) ? $templateLayout['footer'] : [])),
            ];
        }

        if (!isset($layouts[$selectedTemplate])) {
            $layouts[$selectedTemplate] = [
                'header' => $this->normalizeHeaderLayout(''),
                'footer' => $this->normalizeFooterLayout(''),
            ];
        }

        return $layouts;
    }

    private function normalizeSiteLogo(string $path): string
    {
        $path = Files::normalize($path);
        if ($path === '') {
            return '';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new \InvalidArgumentException('Логотип має бути зображенням JPG, PNG або WEBP.');
        }

        if (!is_file(base_path('storage/uploads/' . $path))) {
            throw new \InvalidArgumentException('Обраний логотип не знайдено у медіафайлах.');
        }

        return $path;
    }

    private function templatePreviewMenu(): array
    {
        $settings = $this->siteSettings();
        $homePageId = (int) ($settings['home_page_id'] ?? 0);
        if ($homePageId > 0) {
            return $this->db()->fetchAll('select title, slug from pages where status = ? and id <> ? order by sort_order asc, title asc', ['published', $homePageId]);
        }

        return $this->db()->fetchAll('select title, slug from pages where status = ? and slug <> ? order by sort_order asc, title asc', ['published', 'home']);
    }

    private function templatePreviewHomePage(): array
    {
        $settings = $this->siteSettings();
        $homePageId = (int) ($settings['home_page_id'] ?? 0);
        if ($homePageId > 0) {
            $page = $this->db()->fetch('select title, excerpt from pages where id = ? and status = ?', [$homePageId, 'published']);
            if (is_array($page)) {
                return $page;
            }
        }

        $page = $this->db()->fetch('select title, excerpt from pages where slug = ? and status = ?', ['home', 'published']);
        return is_array($page) ? $page : ['title' => 'Головна', 'excerpt' => ''];
    }

    private function homePageOptions(): array
    {
        return $this->db()->fetchAll('select id, title, slug, status from pages order by status desc, sort_order asc, title asc');
    }

    private function normalizeHomePageId(int $homePageId): string
    {
        if ($homePageId <= 0) {
            return '';
        }

        $page = $this->db()->fetch('select id from pages where id = ? and status = ?', [$homePageId, 'published']);
        return $page ? (string) $homePageId : '';
    }

    private function normalizeSiteTemplate(string $siteTemplate): string
    {
        if (!array_key_exists($siteTemplate, $this->siteTemplates())) {
            return 'official';
        }

        return $siteTemplate;
    }

    private function normalizeHeaderLayout(string $json): array
    {
        $data = json_decode($json, true);
        $data = is_array($data) ? $data : [];
        $links = $this->normalizeMenuLinks(is_array($data['links'] ?? null) ? $data['links'] : []);
        $secondaryLinks = $this->normalizeMenuLinks(is_array($data['secondary_links'] ?? null) ? $data['secondary_links'] : []);
        $ctaUrl = $this->normalizeUrl(trim((string) ($data['cta_url'] ?? '')));
        $heroButtonUrl = $this->normalizeUrl(trim((string) ($data['hero_button_url'] ?? '')));

        return [
            'variant' => $this->choice((string) ($data['variant'] ?? 'default'), ['default', 'centered', 'compact'], 'default'),
            'show_brand' => array_key_exists('show_brand', $data) ? !empty($data['show_brand']) : true,
            'show_home' => false,
            'show_news' => false,
            'links' => array_slice($links, 0, 16),
            'cta_label' => $this->limitString(trim((string) ($data['cta_label'] ?? '')), 80),
            'cta_url' => $this->limitString($ctaUrl, 240),
            'hero_enabled' => !empty($data['hero_enabled']),
            'hero_variant' => $this->choice((string) ($data['hero_variant'] ?? 'default'), ['default', 'accent', 'compact'], 'default'),
            'hero_title' => $this->limitString(trim((string) ($data['hero_title'] ?? '')), 140),
            'hero_text' => $this->limitString(trim((string) ($data['hero_text'] ?? '')), 500),
            'hero_button_label' => $this->limitString(trim((string) ($data['hero_button_label'] ?? '')), 80),
            'hero_button_url' => $this->limitString($heroButtonUrl, 240),
            'secondary_enabled' => !empty($data['secondary_enabled']),
            'secondary_variant' => $this->choice((string) ($data['secondary_variant'] ?? 'pills'), ['pills', 'tabs', 'plain'], 'pills'),
            'secondary_links' => array_slice($secondaryLinks, 0, 12),
            'mobile_variant' => $this->choice((string) ($data['mobile_variant'] ?? 'drawer'), ['drawer', 'panel', 'compact'], 'drawer'),
            'mobile_label' => $this->limitString(trim((string) ($data['mobile_label'] ?? 'Меню')), 40),
            'mobile_show_brand' => array_key_exists('mobile_show_brand', $data) ? !empty($data['mobile_show_brand']) : true,
            'mobile_show_cta' => array_key_exists('mobile_show_cta', $data) ? !empty($data['mobile_show_cta']) : true,
        ];
    }

    private function normalizeFooterLayout(string $json): array
    {
        $data = json_decode($json, true);
        $data = is_array($data) ? $data : [];
        $columns = [];
        foreach (($data['columns'] ?? []) as $column) {
            if (!is_array($column)) {
                continue;
            }
            $title = $this->limitString(trim((string) ($column['title'] ?? '')), 100);
            $items = [];
            foreach (($column['items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $label = $this->limitString(trim((string) ($item['label'] ?? '')), 100);
                $text = $this->limitString(trim((string) ($item['text'] ?? '')), 500);
                $url = $this->limitString($this->normalizeUrl(trim((string) ($item['url'] ?? ''))), 240);
                if ($label === '' && $text === '') {
                    continue;
                }
                $items[] = ['label' => $label, 'text' => $text, 'url' => $url];
            }
            if ($title !== '' || $items) {
                $columns[] = ['title' => $title, 'items' => array_slice($items, 0, 8)];
            }
        }

        return [
            'variant' => $this->choice((string) ($data['variant'] ?? 'default'), ['default', 'dark', 'light'], 'default'),
            'columns' => array_slice($columns, 0, 4),
            'bottom_text' => $this->limitString(trim((string) ($data['bottom_text'] ?? '')), 240),
        ];
    }

    private function normalizeMenuLinks(array $items, int $depth = 0): array
    {
        if ($depth > 2) {
            return [];
        }

        $links = [];
        foreach ($items as $link) {
            if (!is_array($link)) {
                continue;
            }
            $label = trim((string) ($link['label'] ?? ''));
            $type = (string) ($link['type'] ?? 'link') === 'section' ? 'section' : 'link';
            $url = $this->normalizeUrl(trim((string) ($link['url'] ?? '')));
            $icon = $this->normalizeMdiIcon((string) ($link['icon'] ?? ''));
            $children = $this->normalizeMenuLinks(is_array($link['children'] ?? null) ? $link['children'] : [], $depth + 1);
            $columns = $type === 'section' ? $this->normalizeMenuColumns(is_array($link['columns'] ?? null) ? $link['columns'] : [], $depth + 1) : [];
            if ($label === '' && !$children && !$columns) {
                continue;
            }
            if ($type === 'link' && $url === '' && !$children && !$columns) {
                continue;
            }
            $links[] = [
                'type' => $type,
                'label' => $this->limitString($label, 80),
                'url' => $type === 'section' ? '#' : $this->limitString($url ?: '#', 240),
                'icon' => $icon,
                'children' => array_slice($children, 0, 12),
                'columns' => array_slice($columns, 0, 4),
            ];
        }

        return array_slice($links, 0, $depth === 0 ? 16 : 12);
    }

    private function normalizeMenuColumns(array $columns, int $depth): array
    {
        $normalized = [];
        foreach ($columns as $column) {
            if (!is_array($column)) {
                continue;
            }
            $title = $this->limitString(trim((string) ($column['title'] ?? '')), 80);
            $children = $this->normalizeMenuLinks(is_array($column['children'] ?? null) ? $column['children'] : [], $depth);
            if ($title === '' && !$children) {
                continue;
            }
            $normalized[] = [
                'title' => $title,
                'children' => array_slice($children, 0, 12),
            ];
        }

        return $normalized;
    }

    private function normalizeMdiIcon(string $icon): string
    {
        $icon = trim($icon);
        $icon = preg_replace('/^mdi\s+/', '', $icon) ?? '';
        $icon = preg_replace('/^mdi-/', '', $icon) ?? '';
        if ($icon === '' || !preg_match('/^[a-z0-9-]+$/i', $icon)) {
            return '';
        }

        return 'mdi-' . strtolower($icon);
    }

    private function templateLinkPicker(): array
    {
        $pages = array_map(static fn (array $page): array => [
            'label' => (string) ($page['title'] ?? ''),
            'url' => ((string) ($page['slug'] ?? '') === 'home') ? url('/') : url('/page/' . (string) ($page['slug'] ?? '')),
        ], $this->db()->fetchAll('select title, slug from pages where status = ? order by sort_order asc, title asc', ['published']));

        $categories = array_map(static fn (array $category): array => [
            'label' => (string) ($category['category'] ?? $category['title'] ?? ''),
            'display_label' => (string) ($category['label'] ?? $category['category'] ?? ''),
            'url' => url('/news?category=' . rawurlencode((string) ($category['category'] ?? ''))),
        ], $this->orderedNewsCategories($this->db()->fetchAll('select id, parent_id, title as category, title, sort_order from news_categories order by sort_order asc, title asc')));

        $news = array_map(static fn (array $item): array => [
            'label' => (string) ($item['title'] ?? ''),
            'url' => url('/news/' . (string) ($item['slug'] ?? '')),
        ], $this->db()->fetchAll('select title, slug from news where status = ? order by coalesce(published_at, created_at) desc, id desc limit 30', ['published']));

        return ['pages' => $pages, 'categories' => $categories, 'news' => $news];
    }

    private function templatePickerCategories(): array
    {
        return $this->orderedNewsCategories($this->db()->fetchAll('select id, parent_id, title as category, title, sort_order from news_categories order by sort_order asc, title asc'));
    }

    private function templatePagePickerPayload(Request $request, string $query, array $pagination): array
    {
        $clauses = [];
        $params = [];
        if ($query !== '') {
            $like = '%' . $query . '%';
            $clauses[] = '(title like ? or slug like ? or excerpt like ?)';
            array_push($params, $like, $like, $like);
        }

        $status = (string) $request->input('status', 'published');
        if (in_array($status, ['published', 'draft'], true)) {
            $clauses[] = 'status = ?';
            $params[] = $status;
        }

        $where = $clauses ? 'where ' . implode(' and ', $clauses) : '';
        $items = $this->db()->fetchAll(
            'select id, title, slug, status, sort_order from pages ' . $where . ' order by sort_order asc, title asc limit ' . $pagination['limit'] . ' offset ' . $pagination['offset'],
            $params
        );
        $total = (int) ($this->db()->fetch('select count(*) as c from pages ' . $where, $params)['c'] ?? 0);

        return $this->templatePickerResponse(array_map(static fn (array $page): array => [
            'label' => (string) ($page['title'] ?? ''),
            'url' => ((string) ($page['slug'] ?? '') === 'home') ? url('/') : url('/page/' . (string) ($page['slug'] ?? '')),
            'meta' => trim((string) ($page['status'] ?? '') . ' · /' . (string) ($page['slug'] ?? ''), ' ·'),
        ], $items), $pagination, $total);
    }

    private function templateCategoryPickerPayload(Request $request, string $query, array $pagination): array
    {
        $clauses = [];
        $params = [];
        if ($query !== '') {
            $like = '%' . $query . '%';
            $clauses[] = '(title like ? or slug like ?)';
            array_push($params, $like, $like);
        }

        $scope = (string) $request->input('scope', '');
        if ($scope === 'root') {
            $clauses[] = '(parent_id is null or parent_id = 0)';
        } elseif ($scope === 'children') {
            $clauses[] = 'parent_id is not null and parent_id <> 0';
        }

        $where = $clauses ? 'where ' . implode(' and ', $clauses) : '';
        $items = $this->db()->fetchAll(
            'select id, parent_id, title, slug, sort_order from news_categories ' . $where . ' order by sort_order asc, title asc limit ' . $pagination['limit'] . ' offset ' . $pagination['offset'],
            $params
        );
        $total = (int) ($this->db()->fetch('select count(*) as c from news_categories ' . $where, $params)['c'] ?? 0);

        return $this->templatePickerResponse(array_map(static fn (array $category): array => [
            'label' => (string) ($category['title'] ?? ''),
            'url' => url('/news?category=' . rawurlencode((string) ($category['title'] ?? ''))),
            'meta' => ((int) ($category['parent_id'] ?? 0) > 0 ? 'Підкатегорія' : 'Коренева категорія'),
        ], $items), $pagination, $total);
    }

    private function templateNewsPickerPayload(Request $request, string $query, array $pagination): array
    {
        $clauses = [];
        $params = [];
        if ($query !== '') {
            $like = '%' . $query . '%';
            $clauses[] = '(n.title like ? or n.slug like ? or n.category like ? or n.body like ? or exists (
                select 1
                from news_category_links sl
                join news_categories sc on sc.id = sl.category_id
                where sl.news_id = n.id and sc.title like ?
            ))';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $status = (string) $request->input('status', 'published');
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

        $where = $clauses ? 'where ' . implode(' and ', $clauses) : '';
        $items = $this->db()->fetchAll(
            'select n.id, n.title, n.slug, n.status, n.published_at, n.created_at,
                    group_concat(c.title order by c.sort_order asc, c.title asc separator ", ") as category_titles
             from news n
             left join news_category_links l on l.news_id = n.id
             left join news_categories c on c.id = l.category_id
             ' . $where . '
             group by n.id, n.title, n.slug, n.status, n.published_at, n.created_at
             order by coalesce(n.published_at, n.created_at) desc, n.id desc
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

        return $this->templatePickerResponse(array_map(static fn (array $item): array => [
            'label' => (string) ($item['title'] ?? ''),
            'url' => url('/news/' . (string) ($item['slug'] ?? '')),
            'meta' => trim((string) ($item['status'] ?? '') . (((string) ($item['category_titles'] ?? '') !== '') ? ' · ' . (string) $item['category_titles'] : ''), ' ·'),
        ], $items), $pagination, $total);
    }

    private function templateMediaPickerPayload(string $query, array $pagination): array
    {
        $items = $this->filterMedia(Files::all($this->mediaReferences()), $query);
        $total = count($items);
        $slice = array_slice($items, $pagination['offset'], $pagination['limit']);

        return $this->templatePickerResponse(array_map(static fn (array $item): array => [
            'label' => (string) ($item['name'] ?? $item['path'] ?? ''),
            'url' => url('/uploads/' . (string) ($item['path'] ?? '')),
            'meta' => trim((string) ($item['type'] ?? '') . ' · ' . (string) ($item['size_label'] ?? ''), ' ·'),
        ], $slice), $pagination, $total);
    }

    private function templatePickerResponse(array $items, array $pagination, int $total): array
    {
        $loaded = $pagination['offset'] + count($items);
        return [
            'items' => $items,
            'total' => $total,
            'next_offset' => $loaded,
            'has_more' => $loaded < $total,
        ];
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

    private function encodeTemplateLayout(array $layout): string
    {
        $encoded = json_encode($layout, JSON_UNESCAPED_UNICODE);
        return $encoded === false ? '{}' : $encoded;
    }

    private function choice(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function limitString(string $value, int $length): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $length);
        }
        return substr($value, 0, $length);
    }

    private function normalizeUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }
        if (preg_match('/^(https?:\/\/|mailto:|tel:|\/|#)/i', $url)) {
            return $url;
        }

        return '';
    }
}
