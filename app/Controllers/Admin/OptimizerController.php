<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Debug;
use App\Core\Request;
use App\Core\Response;
use App\Services\Files;
use App\Services\MediaMetadata;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

final class OptimizerController extends \App\Controllers\AdminBaseController
{
    private const PREVIEW_LIMIT = 200;

    public function index(Request $request): Response
    {
        $this->guardOptimizer();

        return $this->admin('admin/optimizer/index', [
            'title' => 'Оптимізатор',
            'cacheInfo' => $this->cacheInfo(),
            'debugInfo' => $this->debugInfo(),
            'canManageMedia' => Container::get('auth')->can('media.manage'),
            'canManageSystem' => Container::get('auth')->can('settings.manage'),
            'previewLimit' => self::PREVIEW_LIMIT,
            'mediaTabActive' => (string) $request->input('tab', '') === 'media' || (int) $request->input('applied', 0) > 0,
            'applied' => max(0, (int) $request->input('applied', 0)),
            'cacheCleared' => max(0, (int) $request->input('cache_cleared', 0)),
            'cacheChecked' => (string) $request->input('cache_checked', '') === '1',
            'debugChanged' => (string) $request->input('debug', ''),
        ]);
    }

    public function mediaFolders(Request $request): Response
    {
        $this->guard('media.manage');

        try {
            $analysis = $this->mediaFolderAnalysis();
            $html = $this->view()->partial('admin/optimizer/media-folders', [
                'analysis' => $analysis,
                'previewLimit' => self::PREVIEW_LIMIT,
                'canManageMedia' => Container::get('auth')->can('media.manage'),
            ]);

            return $this->json([
                'ok' => true,
                'html' => $html,
                'stats' => $analysis['stats'] ?? [],
            ]);
        } catch (Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
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

            redirect('/admin/optimizer?tab=media&applied=' . $updated);
        } catch (Throwable $e) {
            if ($this->isAjax($request)) {
                return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return $this->admin('admin/optimizer/index', [
                'title' => 'Оптимізатор',
                'cacheInfo' => $this->cacheInfo(),
                'debugInfo' => $this->debugInfo(),
                'canManageMedia' => Container::get('auth')->can('media.manage'),
                'canManageSystem' => Container::get('auth')->can('settings.manage'),
                'previewLimit' => self::PREVIEW_LIMIT,
                'mediaTabActive' => true,
                'applied' => 0,
                'cacheCleared' => 0,
                'debugChanged' => '',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function clearCache(Request $request): Response
    {
        $this->guard('settings.manage');
        Csrf::verify();

        try {
            $deleted = $this->clearDirectoryContents(base_path('storage/cache'));

            if ($deleted > 0) {
                $this->audit('clear_cache', 'system', null, 'deleted: ' . $deleted);
            }

            if ($this->isAjax($request)) {
                return $this->json([
                    'ok' => true,
                    'message_title' => $deleted > 0 ? 'Кеш сайту очищено' : 'Кеш уже порожній',
                    'message' => $deleted > 0 ? 'Видалено файлів: ' . $deleted . '. Нові превʼю створяться автоматично.' : 'Файлів для видалення не знайдено. Додаткових дій не потрібно.',
                    'message_tone' => 'success',
                    'message_icon' => $deleted > 0 ? 'mdi-check-circle-outline' : 'mdi-information-outline',
                    'deleted' => $deleted,
                    'cacheInfo' => $this->cacheInfo(),
                ]);
            }

            redirect('/admin/optimizer?cache_checked=1&cache_cleared=' . $deleted);
        } catch (Throwable $e) {
            if ($this->isAjax($request)) {
                return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return $this->admin('admin/optimizer/index', [
                'title' => 'Оптимізатор',
                'cacheInfo' => $this->cacheInfo(),
                'debugInfo' => $this->debugInfo(),
                'canManageMedia' => Container::get('auth')->can('media.manage'),
                'canManageSystem' => Container::get('auth')->can('settings.manage'),
                'previewLimit' => self::PREVIEW_LIMIT,
                'applied' => 0,
                'cacheCleared' => 0,
                'debugChanged' => '',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function toggleDebug(Request $request): Response
    {
        $this->guard('settings.manage');
        Csrf::verify();

        try {
            $enabled = (string) $request->input('enabled', '0') === '1';
            $path = base_path('storage/debug.enabled');
            $storage = dirname($path);
            if (!is_dir($storage) && !mkdir($storage, 0775, true)) {
                throw new RuntimeException('Не вдалося створити директорію storage.');
            }

            if ($enabled) {
                if (file_put_contents($path, 'enabled ' . date('c') . PHP_EOL) === false) {
                    throw new RuntimeException('Не вдалося увімкнути debug режим.');
                }
            } elseif (is_file($path) && !unlink($path)) {
                throw new RuntimeException('Не вдалося вимкнути debug режим.');
            }

            $this->audit($enabled ? 'enable_debug' : 'disable_debug', 'system');

            if ($this->isAjax($request)) {
                return $this->json([
                    'ok' => true,
                    'message_title' => $enabled ? 'Debug режим увімкнено' : 'Debug режим вимкнено',
                    'message' => $enabled
                        ? 'Технічні помилки можуть відображатися відвідувачам. Вимкніть режим після завершення діагностики.'
                        : 'Технічні повідомлення більше не відображатимуться відвідувачам.',
                    'message_tone' => $enabled ? 'warning' : 'success',
                    'message_icon' => $enabled ? 'mdi-alert-outline' : 'mdi-shield-check-outline',
                    'debugInfo' => $this->debugInfo(),
                ]);
            }

            redirect('/admin/optimizer?debug=' . ($enabled ? 'enabled' : 'disabled'));
        } catch (Throwable $e) {
            if ($this->isAjax($request)) {
                return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return $this->admin('admin/optimizer/index', [
                'title' => 'Оптимізатор',
                'cacheInfo' => $this->cacheInfo(),
                'debugInfo' => $this->debugInfo(),
                'canManageMedia' => Container::get('auth')->can('media.manage'),
                'canManageSystem' => Container::get('auth')->can('settings.manage'),
                'previewLimit' => self::PREVIEW_LIMIT,
                'applied' => 0,
                'cacheCleared' => 0,
                'debugChanged' => '',
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

    private function guardOptimizer(): void
    {
        $this->guard();
        $auth = Container::get('auth');
        if ($auth->can('media.manage') || $auth->can('settings.manage')) {
            return;
        }

        if ($this->isAjaxRequest()) {
            (new Response(json_encode(['ok' => false, 'message' => 'Доступ заборонено.'], JSON_UNESCAPED_UNICODE), 403, [
                'Content-Type' => 'application/json; charset=UTF-8',
            ]))->send();
            exit;
        }

        \App\Controllers\ErrorController::response(403)->send();
        exit;
    }

    private function cacheInfo(): array
    {
        $path = base_path('storage/cache');
        $info = [
            'path' => $path,
            'exists' => is_dir($path),
            'writable' => is_dir($path) && is_writable($path),
            'files' => 0,
            'bytes' => 0,
            'size' => '0 Б',
        ];

        if (!is_dir($path)) {
            return $info;
        }

        foreach ($this->directoryFiles($path) as $file) {
            if ($file->getFilename() === '.gitkeep') {
                continue;
            }

            $info['files']++;
            $info['bytes'] += max(0, $file->getSize());
        }

        $info['size'] = $this->formatBytes((int) $info['bytes']);
        return $info;
    }

    private function debugInfo(): array
    {
        $path = base_path('storage/debug.enabled');

        return [
            'enabled' => Debug::enabled(base_path()),
            'marker_path' => $path,
            'marker_exists' => is_file($path),
            'storage_writable' => is_writable(base_path('storage')),
            'log_path' => base_path('storage/debug.log'),
        ];
    }

    private function clearDirectoryContents(string $path): int
    {
        if (!is_dir($path)) {
            if (!mkdir($path, 0775, true)) {
                throw new RuntimeException('Не вдалося створити директорію кешу.');
            }
            return 0;
        }

        if (!is_writable($path)) {
            throw new RuntimeException('Директорія кешу недоступна для запису.');
        }

        $deleted = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $itemPath = $item->getPathname();
            if ($item->isFile() || $item->isLink()) {
                if ($item->getFilename() === '.gitkeep') {
                    continue;
                }
                if (!unlink($itemPath)) {
                    throw new RuntimeException('Не вдалося видалити файл кешу: ' . $item->getFilename());
                }
                $deleted++;
                continue;
            }

            if ($item->isDir() && !$this->directoryHasGitkeep($itemPath) && !rmdir($itemPath)) {
                throw new RuntimeException('Не вдалося видалити директорію кешу: ' . $item->getFilename());
            }
        }

        return $deleted;
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function directoryFiles(string $path): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                yield $item;
            }
        }
    }

    private function directoryHasGitkeep(string $path): bool
    {
        return is_file(rtrim($path, '/\\') . '/.gitkeep');
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
