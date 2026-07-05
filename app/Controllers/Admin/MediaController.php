<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\Files;
use Throwable;

final class MediaController extends \App\Controllers\AdminBaseController
{
    public function media(Request $request): Response
    {
        $this->guard('media.manage');
        $query = trim((string) $request->input('q', ''));
        $pagination = $this->pagination($request);
        $list = $this->mediaListPayload($query, $pagination);

        if ($this->isAjaxRequest()) {
            return $this->json($list);
        }

        return $this->admin('admin/media/index', [
            'title' => 'Медіафайли',
            'items' => $list['items'],
            'total' => $list['total'],
            'limit' => $pagination['limit'],
            'stats' => $list['stats'],
            'query' => $query,
            'uploadLimitBytes' => Files::uploadLimitBytes(),
            'uploadLimitLabel' => Files::uploadLimitLabel(),
        ]);
    }

    public function mediaPicker(Request $request): Response
    {
        $this->guard('media.manage');

        $query = trim((string) $request->input('q', ''));
        $pagination = $this->pagination($request);
        $list = $this->mediaListPayload($query, $pagination);
        $items = array_map(static function (array $item): array {
            return [
                'path' => (string) ($item['path'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'extension' => (string) ($item['extension'] ?? ''),
                'type' => (string) ($item['type'] ?? ''),
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

            $this->audit('upload', 'media', null, $filePath);
            if ($this->isAjax($request)) {
                return $this->json(array_replace($this->mediaListPayload(trim((string) $request->input('q', '')), [
                    'limit' => self::LIST_LIMIT,
                    'offset' => 0,
                ]), [
                    'message' => 'Файл завантажено.',
                    'uploaded_path' => $filePath,
                ]));
            }

            redirect('/admin/media');
        } catch (Throwable $e) {
            if ($this->isAjax($request)) {
                return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            $allItems = Files::all($this->mediaReferences());
            return $this->admin('admin/media/index', [
                'title' => 'Медіафайли',
                'items' => array_slice($allItems, 0, self::LIST_LIMIT),
                'total' => count($allItems),
                'limit' => self::LIST_LIMIT,
                'stats' => $this->mediaStats($allItems),
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
        if ($path === '' || isset($references[$path])) {
            if ($this->isAjax($request)) {
                return $this->json(['ok' => false, 'message' => 'Файл використовується або не знайдений.'], 422);
            }

            return new Response('Cannot delete file', 422);
        }

        Files::delete($path);
        $this->audit('delete', 'media', null, $path);
        if ($this->isAjax($request)) {
            return $this->json(array_replace($this->mediaListPayload(trim((string) $request->input('q', '')), [
                'limit' => self::LIST_LIMIT,
                'offset' => 0,
            ]), [
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
            foreach ($paths as $path) {
                if ($path === '' || isset($references[$path])) {
                    continue;
                }
                try {
                    Files::delete($path);
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
}
