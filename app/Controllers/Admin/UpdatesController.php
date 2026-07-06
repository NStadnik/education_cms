<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AdminBaseController;
use App\Core\Container;
use App\Core\Csrf;
use App\Core\Response;
use App\Services\SchemaUpgrade;
use App\Services\UpdateChecker;
use App\Services\UpdateInstaller;
use Throwable;

final class UpdatesController extends AdminBaseController
{
    public function index(): Response
    {
        $this->guard('updates.manage');

        $message = $_SESSION['updates_message'] ?? '';
        $recentUpdate = !empty($_SESSION['updates_recent_success']);
        unset($_SESSION['updates_message'], $_SESSION['updates_recent_success']);

        return $this->admin('admin/updates/index', [
            'title' => 'Оновлення',
            'currentVersion' => $this->currentVersion(),
            'message' => $message,
            'recentUpdate' => $recentUpdate,
        ]);
    }

    public function check(): Response
    {
        $this->guard('updates.manage');
        Csrf::verify();

        try {
            $latest = $this->checker()->latest();
            return $this->json([
                'ok' => true,
                'message' => empty($latest['has_update']) ? 'Установлена актуальна версія.' : 'Доступне оновлення до версії ' . $latest['version'] . '.',
                'current_version' => $this->currentVersion(),
                'release' => $latest,
            ]);
        } catch (Throwable $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
                'current_version' => $this->currentVersion(),
            ], 502);
        }
    }

    public function install(): Response
    {
        $this->guard('updates.manage');
        Csrf::verify();

        try {
            $latest = $this->checker()->latest();
            if (empty($latest['has_update'])) {
                return $this->finishInstallResponse([
                    'ok' => true,
                    'message' => 'Уже встановлена актуальна версія.',
                    'current_version' => $this->currentVersion(),
                    'release' => $latest,
                ]);
            }

            $result = (new UpdateInstaller($this->checker(), base_path()))->install($latest);
            SchemaUpgrade::ensure(Container::get('db'));
            try {
                $this->audit('update', 'system', null, 'Installed version ' . $result['version']);
            } catch (Throwable) {
                // The update already succeeded; audit logging must not turn it into a reported failure.
            }
            return $this->finishInstallResponse([
                'ok' => true,
                'message' => 'Оновлення до версії ' . $result['version'] . ' встановлено.',
                'current_version' => $this->currentVersion(),
                'installed_version' => $result['version'],
                'backup_path' => $result['backup_path'],
            ]);
        } catch (Throwable $exception) {
            return $this->finishInstallResponse([
                'ok' => false,
                'message' => 'Помилка оновлення: ' . $exception->getMessage(),
                'current_version' => $this->currentVersion(),
            ], 500);
        }
    }

    private function checker(): UpdateChecker
    {
        $config = Container::get('config')['app'] ?? [];
        $updates = is_array($config['updates'] ?? null) ? $config['updates'] : [];

        return new UpdateChecker(
            (string) ($updates['github_owner'] ?? 'NStadnik'),
            (string) ($updates['github_repo'] ?? 'education_cms'),
            $this->currentVersion()
        );
    }

    private function currentVersion(): string
    {
        $config = Container::get('config')['app'] ?? [];
        $versionFile = base_path('VERSION');
        if (is_file($versionFile)) {
            $version = trim((string) file_get_contents($versionFile));
            if ($version !== '') {
                return ltrim($version, 'vV');
            }
        }

        return ltrim((string) ($config['version'] ?? '0.0.0'), 'vV');
    }

    private function finishInstallResponse(array $data, int $status = 200): Response
    {
        if (!empty($data['ok'])) {
            $_SESSION['updates_message'] = (string) ($data['message'] ?? '');
            if (!empty($data['backup_path'])) {
                $_SESSION['updates_message'] .= ' Backup: ' . $data['backup_path'];
            }
            if (!empty($data['backup_path']) || !empty($data['installed_version'])) {
                $_SESSION['updates_recent_success'] = true;
            }
        }

        if ($this->isAjaxRequest()) {
            return $this->json($data, $status);
        }

        if (empty($data['ok'])) {
            $_SESSION['updates_message'] = (string) ($data['message'] ?? '');
        }
        redirect('/admin/updates');
    }
}
