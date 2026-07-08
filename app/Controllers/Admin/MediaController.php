<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\Files;
use App\Services\MediaMetadata;
use Throwable;

final class MediaController extends \App\Controllers\AdminBaseController
{
    public function media(Request $request): Response
    {
        $this->guard('media.manage');
        $query = trim((string) $request->input('q', ''));
        $folder = MediaMetadata::normalizeFolder((string) $request->input('folder', ''));
        $pagination = $this->pagination($request);
        $list = $this->mediaListPayload($query, $pagination, $folder);

        if ($this->isAjaxRequest()) {
            return $this->json($list);
        }

        return $this->admin('admin/media/index', [
            'title' => 'Медіафайли',
            'items' => $list['items'],
            'total' => $list['total'],
            'limit' => $pagination['limit'],
            'stats' => $list['stats'],
            'folders' => $list['folders'],
            'folder' => $folder,
            'query' => $query,
            'uploadLimitBytes' => Files::uploadLimitBytes(),
            'uploadLimitLabel' => Files::uploadLimitLabel(),
        ]);
    }

    public function mediaPicker(Request $request): Response
    {
        $this->guard('media.manage');

        $query = trim((string) $request->input('q', ''));
        $folder = MediaMetadata::normalizeFolder((string) $request->input('folder', ''));
        $pagination = $this->pagination($request);
        $list = $this->mediaListPayload($query, $pagination, $folder);
        $items = array_map(static function (array $item): array {
            return [
                'path' => (string) ($item['path'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'extension' => (string) ($item['extension'] ?? ''),
                'type' => (string) ($item['type'] ?? ''),
                'folder' => (string) ($item['folder'] ?? ''),
                'alt_text' => (string) ($item['alt_text'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'caption' => (string) ($item['caption'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
                'size_label' => (string) ($item['size_label'] ?? ''),
                'is_image' => !empty($item['is_image']),
                'url' => url('/uploads/' . (string) ($item['path'] ?? '')),
            ];
        }, $list['items']);

        return $this->json([
            'ok' => true,
            'items' => $items,
            'total' => $list['total'],
            'next_offset' => $list['next_offset'],
            'has_more' => $list['has_more'],
        ]);
    }

    public function mediaUpload(Request $request): Response
    {
        $this->guard('media.manage');
        Csrf::verify();

        try {
            $filePath = Files::upload($request->files['file'] ?? []);
            if (!$filePath) {
                throw new \RuntimeException('Оберіть файл для завантаження.');
            }
            $folder = MediaMetadata::normalizeFolder((string) $request->input('folder', ''));
            MediaMetadata::save($filePath, ['folder' => $folder, 'uploaded_by' => (string) $this->currentUserId()]);

            $this->audit('upload', 'media', null, $filePath);
            if ($this->isAjax($request)) {
                return $this->json(array_replace($this->mediaListPayload(trim((string) $request->input('q', '')), [
                    'limit' => self::LIST_LIMIT,
                    'offset' => 0,
                ], MediaMetadata::normalizeFolder((string) $request->input('current_folder', ''))), [
                    'message' => 'Файл завантажено.',
                    'uploaded_path' => $filePath,
                ]));
            }

            redirect('/admin/media');
        } catch (Throwable $e) {
            if ($this->isAjax($request)) {
                return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            $allItems = $this->filterOwnedMediaItems(Files::all($this->mediaReferences()));
            return $this->admin('admin/media/index', [
                'title' => 'Медіафайли',
                'items' => array_slice($allItems, 0, self::LIST_LIMIT),
                'total' => count($allItems),
                'limit' => self::LIST_LIMIT,
                'stats' => $this->mediaStats($allItems),
                'folders' => MediaMetadata::folders($allItems),
                'folder' => '',
                'query' => '',
                'uploadLimitBytes' => Files::uploadLimitBytes(),
                'uploadLimitLabel' => Files::uploadLimitLabel(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function mediaDelete(Request $request): Response
    {
        $this->guard('media.manage');
        Csrf::verify();

        $path = Files::normalize((string) $request->input('path', ''));
        $references = $this->mediaReferences();
        $item = $this->mediaItem($path);
        if ($path === '' || !$item || !$this->canManageMediaItem($item) || isset($references[$path])) {
            if ($this->isAjax($request)) {
                return $this->json(['ok' => false, 'message' => 'Файл використовується або не знайдений.'], 422);
            }

            return new Response('Cannot delete file', 422);
        }

        Files::delete($path);
        MediaMetadata::delete($path);
        $this->audit('delete', 'media', null, $path);
        if ($this->isAjax($request)) {
            return $this->json(array_replace($this->mediaListPayload(trim((string) $request->input('q', '')), [
                'limit' => self::LIST_LIMIT,
                'offset' => 0,
            ], MediaMetadata::normalizeFolder((string) $request->input('folder', ''))), [
                'message' => 'Файл видалено.',
                'deleted_path' => $path,
            ]));
        }

        redirect('/admin/media');
    }

    public function mediaBulk(Request $request): Response
    {
        $this->guard('media.manage');
        Csrf::verify();

        $paths = $request->input('paths', []);
        $paths = is_array($paths) ? $paths : [];
        $paths = array_values(array_unique(array_filter(array_map(static fn ($path): string => Files::normalize((string) $path), $paths))));
        $action = (string) $request->input('bulk_action', '');
        $deleted = 0;

        if ($paths && $action === 'delete') {
            $references = $this->mediaReferences();
            $mediaItems = $this->mediaItemsByPath();
            foreach ($paths as $path) {
                $item = $mediaItems[$path] ?? null;
                if ($path === '' || !$item || !$this->canManageMediaItem($item) || isset($references[$path])) {
                    continue;
                }
                try {
                    Files::delete($path);
                    MediaMetadata::delete($path);
                    $deleted++;
                } catch (Throwable) {
                    continue;
                }
            }
            $this->audit('bulk_delete', 'media', null, 'paths: ' . implode(',', $paths));
        }

        if ($this->isAjax($request)) {
            $message = $deleted > 0 ? 'Файли видалено: ' . $deleted . '.' : 'Немає файлів для видалення.';
            return $this->json($this->adminListPayload('media', $request, $message));
        }

        redirect('/admin/media');
    }

    public function mediaMetadataSave(Request $request): Response
    {
        $this->guard('media.manage');
        Csrf::verify();

        try {
            $path = Files::normalize((string) $request->input('path', ''));
            $item = $this->mediaItem($path);
            if ($path === '' || !is_file(base_path('storage/uploads/' . $path)) || !$item || !$this->canManageMediaItem($item)) {
                throw new \InvalidArgumentException('Файл не знайдено.');
            }

            $metadata = MediaMetadata::save($path, [
                'folder' => (string) $request->input('folder', ''),
                'alt_text' => (string) $request->input('alt_text', ''),
                'title' => (string) $request->input('title', ''),
                'caption' => (string) $request->input('caption', ''),
                'description' => (string) $request->input('description', ''),
            ]);
            $this->audit('metadata', 'media', null, $path);

            $payload = $this->mediaListPayload(trim((string) $request->input('q', '')), [
                'limit' => self::LIST_LIMIT,
                'offset' => 0,
            ], MediaMetadata::normalizeFolder((string) $request->input('current_folder', '')));

            return $this->json(array_replace($payload, [
                'message' => 'Метадані файлу збережено.',
                'path' => $path,
                'metadata' => $metadata,
            ]));
        } catch (Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function mediaItem(string $path): ?array
    {
        return $this->mediaItemsByPath()[$path] ?? null;
    }

    private function mediaItemsByPath(): array
    {
        $items = [];
        foreach (Files::all($this->mediaReferences()) as $item) {
            $items[(string) ($item['path'] ?? '')] = $item;
        }

        return $items;
    }
}
