<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\Files;
use Throwable;

final class ImportController extends \App\Controllers\AdminBaseController
{
    public function import(): Response
    {
        $this->guard('settings.manage');
        return $this->admin('admin/import', [
            'title' => 'Імпорт',
            'importOptions' => $this->importOptions(),
        ]);
    }

    public function importRun(Request $request): Response
    {
        $this->guard('settings.manage');
        Csrf::verify();
        $transactionStarted = false;
        try {
            $type = (string) $request->input('type', 'news');
            if ((string) $request->input('source', 'file') === 'database') {
                $type = 'wordpress';
            }
            if (!array_key_exists($type, $this->importOptions())) {
                throw new \RuntimeException('Невідомий тип імпорту.');
            }
            $importMode = $this->requestImportMode($request);

            if ($type === 'wordpress' && ($request->input('wp_menu_only') || (string) $request->input('wp_import_scope', 'all') === 'menu')) {
                $stats = $this->importWordPressMenus($request, $importMode);
                $result = [
                    'ok' => true,
                    'message' => 'Меню WordPress імпортовано.',
                    'created' => (int) ($stats['menu_items_imported'] ?? 0),
                    'total' => (int) ($stats['menus_imported'] ?? 0),
                    'stats' => $stats,
                    'next_offset' => 0,
                    'has_more' => false,
                ];

                return $this->json($result);
            }

            if ($type === 'wordpress' && ($request->input('wp_media_only') || (string) $request->input('wp_import_scope', 'all') === 'media')) {
                $stats = $this->runWordPressMediaBatch($request);
                $result = [
                    'ok' => true,
                    'message' => 'Пакет файлів WordPress оброблено.',
                    'created' => 0,
                    'total' => (int) ($stats['media_total'] ?? 0),
                    'stats' => $stats,
                    'next_offset' => (int) ($stats['media_next_offset'] ?? 0),
                    'has_more' => (bool) ($stats['media_has_more'] ?? false),
                ];

                return $this->json($result);
            }

            $rows = $this->readImportRows($request, false);
            if (!$rows) {
                if ($type === 'wordpress') {
                    $stats = $this->lastImportStats;
                    return $this->json([
                        'ok' => true,
                        'message' => 'Немає записів WordPress для цього пакета.',
                        'created' => 0,
                        'total' => (int) ($stats['posts_total'] ?? 0),
                        'stats' => $stats,
                        'next_offset' => (int) ($stats['posts_next_offset'] ?? 0),
                        'has_more' => false,
                    ]);
                }
                throw new \RuntimeException('Файл або текст імпорту не містить записів.');
            }

            $this->db()->pdo()->beginTransaction();
            $transactionStarted = true;
            $created = $this->importRows($type, $rows, $importMode);
            $this->db()->pdo()->commit();
            $transactionStarted = false;
            $this->audit('import', $type, null, 'processed: ' . $created);

            $result = [
                'ok' => true,
                'message' => 'Імпорт завершено.',
                'created' => $created,
                'total' => count($rows),
                'stats' => $this->lastImportStats,
            ];

            if ($type === 'wordpress') {
                $result['next_offset'] = (int) ($this->lastImportStats['posts_next_offset'] ?? count($rows));
                $result['has_more'] = (bool) ($this->lastImportStats['posts_has_more'] ?? false);
                $result['total'] = (int) ($this->lastImportStats['posts_total'] ?? count($rows));
            }

            if ($this->isAjax($request)) {
                return $this->json($result);
            }

            return $this->admin('admin/import', [
                'title' => 'Імпорт',
                'importOptions' => $this->importOptions(),
                'result' => $result,
            ]);
        } catch (Throwable $e) {
            if ($transactionStarted && $this->db()->pdo()->inTransaction()) {
                $this->db()->pdo()->rollBack();
            }

            if ($this->isAjax($request)) {
                return $this->json(['ok' => false, 'message' => $e->getMessage()], 500);
            }

            return $this->admin('admin/import', [
                'title' => 'Імпорт',
                'importOptions' => $this->importOptions(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function requestImportMode(Request $request): string
    {
        if ($request->input('import_check_duplicates')) {
            return 'upsert';
        }

        $mode = (string) $request->input('import_mode', 'create');
        return in_array($mode, ['create', 'update', 'upsert'], true) ? $mode : 'create';
    }

    public function importPreview(Request $request): Response
    {
        $this->guard('settings.manage');
        Csrf::verify();
        try {
            $type = (string) $request->input('type', 'news');
            if ((string) $request->input('source', 'file') === 'database') {
                $type = 'wordpress';
            }
            if (!array_key_exists($type, $this->importOptions())) {
                throw new \RuntimeException('Невідомий тип імпорту.');
            }

            $rows = $this->readImportRows($request, true);
            if (!$rows) {
                throw new \RuntimeException('Джерело імпорту не містить записів для попереднього перегляду.');
            }

            return $this->json([
                'ok' => true,
                'message' => 'Попередній перегляд готовий.',
                'total' => count($rows),
                'summary' => $this->importPreviewSummary($type, $rows),
                'rows' => array_slice($this->importPreviewRows($type, $rows), 0, 10),
                'stats' => $this->lastImportStats,
            ]);
        } catch (Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
