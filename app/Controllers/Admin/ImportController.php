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

            $rows = $this->readImportRows($request, false);
            if (!$rows) {
                throw new \RuntimeException('Файл або текст імпорту не містить записів.');
            }

            $this->db()->pdo()->beginTransaction();
            $transactionStarted = true;
            $created = $this->importRows($type, $rows);
            $this->db()->pdo()->commit();
            $transactionStarted = false;
            $this->audit('import', $type, null, 'created: ' . $created);

            $result = [
                'ok' => true,
                'message' => 'Імпорт завершено.',
                'created' => $created,
                'total' => count($rows),
            ];

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
            ]);
        } catch (Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
