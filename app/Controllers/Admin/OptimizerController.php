<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\Files;
use App\Services\MediaMetadata;
use Throwable;

final class OptimizerController extends \App\Controllers\AdminBaseController
{
    private const PREVIEW_LIMIT = 200;

    public function index(Request $request): Response
    {
        $this->guard('media.manage');

        return $this->admin('admin/optimizer/index', [
            'title' => 'Оптимізатор',
            'analysis' => $this->mediaFolderAnalysis(),
            'previewLimit' => self::PREVIEW_LIMIT,
            'applied' => max(0, (int) $request->input('applied', 0)),
        ]);
    }

    public function applyMediaFolders(Request $request): Response
    {
        $this->guard('media.manage');
        Csrf::verify();

        try {
            $analysis = $this->mediaFolderAnalysis();
            $updates = [];
            foreach ($analysis['suggestions'] as $suggestion) {
                $path = (string) ($suggestion['path'] ?? '');
                $folder = (string) ($suggestion['folder'] ?? '');
                if ($path === '' || $folder === '') {
                    continue;
                }

                $updates[$path] = ['folder' => $folder];
            }
            $updated = MediaMetadata::saveMany($updates);

            if ($updated > 0) {
                $this->audit('optimize_media_folders', 'media', null, 'updated: ' . $updated);
            }

            if ($this->isAjax($request)) {
                return $this->json([
                    'ok' => true,
                    'message' => $updated > 0 ? 'Медіафайли розсортовано: ' . $updated . '.' : 'Немає змін для застосування.',
                    'updated' => $updated,
                ]);
            }

            redirect('/admin/optimizer?applied=' . $updated);
        } catch (Throwable $e) {
            if ($this->isAjax($request)) {
                return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return $this->admin('admin/optimizer/index', [
                'title' => 'Оптимізатор',
                'analysis' => $this->mediaFolderAnalysis(),
                'previewLimit' => self::PREVIEW_LIMIT,
                'applied' => 0,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function mediaFolderAnalysis(): array
    {
        $mediaItems = $this->filterOwnedMediaItems(Files::all());
        $mediaByPath = [];
        foreach ($mediaItems as $item) {
            $path = (string) ($item['path'] ?? '');
            if ($path !== '') {
                $mediaByPath[$path] = $item;
            }
        }

        $usages = [];
        foreach ($this->newsRowsForMediaAnalysis() as $row) {
            $folder = $this->folderForNews($row);
            $category = $this->newsCategoryLabel($row);
            $paths = $this->mediaPathsForNews($row);
            foreach ($paths as $path) {
                if (!isset($mediaByPath[$path])) {
                    continue;
                }

                $usages[$path][] = [
                    'news_id' => (int) ($row['id'] ?? 0),
                    'title' => (string) ($row['title'] ?? 'Без назви'),
                    'category' => $category,
                    'folder' => $folder,
                    'url' => url('/admin/news/edit?id=' . (int) ($row['id'] ?? 0)),
                ];
            }
        }

        $suggestions = [];
        $conflicts = [];
        $unchanged = 0;
        foreach ($usages as $path => $pathUsages) {
            $folders = [];
            foreach ($pathUsages as $usage) {
                $folders[(string) $usage['folder']] = true;
            }
            $folders = array_keys($folders);
            sort($folders, SORT_NATURAL | SORT_FLAG_CASE);

            $item = $mediaByPath[$path] ?? [];
            $currentFolder = (string) ($item['folder'] ?? '');
            if (count($folders) > 1) {
                $conflicts[] = [
                    'path' => $path,
                    'name' => (string) ($item['name'] ?? basename($path)),
                    'current_folder' => $currentFolder,
                    'folders' => $folders,
                    'usages' => $this->compactUsages($pathUsages),
                ];
                continue;
            }

            $folder = (string) ($folders[0] ?? '');
            if ($folder === '' || $currentFolder === $folder) {
                $unchanged++;
                continue;
            }

            $suggestions[] = [
                'path' => $path,
                'name' => (string) ($item['name'] ?? basename($path)),
                'current_folder' => $currentFolder,
                'folder' => $folder,
                'usages' => $this->compactUsages($pathUsages),
            ];
        }

        usort($suggestions, static fn (array $a, array $b): int => strcmp((string) $a['folder'], (string) $b['folder']) ?: strcmp((string) $a['path'], (string) $b['path']));
        usort($conflicts, static fn (array $a, array $b): int => strcmp((string) $a['path'], (string) $b['path']));

        return [
            'suggestions' => $suggestions,
            'conflicts' => $conflicts,
            'stats' => [
                'media_total' => count($mediaItems),
                'news_media' => count($usages),
                'updates' => count($suggestions),
                'unchanged' => $unchanged,
                'conflicts' => count($conflicts),
            ],
        ];
    }

    private function newsRowsForMediaAnalysis(): array
    {
        [$ownerWhere, $ownerParams] = $this->ownedContentWhere('n');
        $clauses = [
            "(n.image_path is not null and n.image_path <> ''
                or n.body like '%uploads/%'
                or n.body like '%/thumb/%'
                or n.body like '%thumb/%')",
        ];
        if ($ownerWhere !== '') {
            array_unshift($clauses, $ownerWhere);
        }
        $where = 'where ' . implode(' and ', $clauses);

        return $this->db()->fetchAll(
            "select n.id, n.title, n.category, n.image_path, n.body,
                    group_concat(c.title order by c.sort_order asc, c.title asc separator '||') as category_titles
             from news n
             left join news_category_links l on l.news_id = n.id
             left join news_categories c on c.id = l.category_id
             {$where}
             group by n.id, n.title, n.category, n.image_path, n.body
             order by n.id desc",
            $ownerParams
        );
    }

    private function mediaPathsForNews(array $row): array
    {
        $paths = [];
        $imagePath = Files::normalize((string) ($row['image_path'] ?? ''));
        if ($imagePath !== '') {
            $paths[] = $imagePath;
        }

        array_push($paths, ...$this->extractMediaPaths((string) ($row['body'] ?? '')));
        return array_values(array_unique(array_filter($paths)));
    }

    private function extractMediaPaths(string $content): array
    {
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($content === '') {
            return [];
        }

        preg_match_all('~(?:^|[\s"\'(=])(?:https?:)?(?://[^/\s"\'<>]+)?/?(?:uploads|thumb)/([^"\'\s<>?#]+)~iu', $content, $matches);
        $paths = [];
        foreach (($matches[1] ?? []) as $path) {
            $path = Files::normalize(rawurldecode((string) $path));
            if ($path !== '') {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    private function folderForNews(array $row): string
    {
        $categories = $this->newsCategoryTitles($row);
        return MediaMetadata::normalizeFolder('Новини: ' . ($categories[0] ?? 'Без категорії'));
    }

    private function newsCategoryLabel(array $row): string
    {
        $categories = $this->newsCategoryTitles($row);
        return $categories ? implode(', ', $categories) : 'Без категорії';
    }

    private function newsCategoryTitles(array $row): array
    {
        $titles = array_values(array_filter(array_map('trim', explode('||', (string) ($row['category_titles'] ?? '')))));
        if (!$titles) {
            $legacy = trim((string) ($row['category'] ?? ''));
            if ($legacy !== '') {
                $titles[] = $legacy;
            }
        }

        return $titles ?: ['Без категорії'];
    }

    private function compactUsages(array $usages): array
    {
        $seen = [];
        $result = [];
        foreach ($usages as $usage) {
            $key = (string) ($usage['news_id'] ?? 0);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $usage;
            if (count($result) >= 5) {
                break;
            }
        }

        return $result;
    }
}
