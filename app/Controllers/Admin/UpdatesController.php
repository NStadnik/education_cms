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

        $latest = null;
        $error = '';
        try {
            $latest = $this->checker()->latest();
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        return $this->admin('admin/updates/index', [
            'title' => 'Оновлення',
            'currentVersion' => $this->currentVersion(),
            'latest' => $latest,
            'error' => $error,
            'message' => $_SESSION['updates_message'] ?? '',
        ]);
    }

    public function install(): Response
    {
        $this->guard('updates.manage');
        Csrf::verify();

        try {
            $latest = $this->checker()->latest();
            if (empty($latest['has_update'])) {
                $_SESSION['updates_message'] = 'Уже встановлена актуальна версія.';
                redirect('/admin/updates');
            }

            $result = (new UpdateInstaller($this->checker(), base_path()))->install($latest);
            SchemaUpgrade::ensure(Container::get('db'));
            try {
                $this->audit('update', 'system', null, 'Installed version ' . $result['version']);
            } catch (Throwable) {
                // The update already succeeded; audit logging must not turn it into a reported failure.
            }
            $_SESSION['updates_message'] = 'Оновлення до версії ' . $result['version'] . ' встановлено. Backup: ' . $result['backup_path'];
        } catch (Throwable $exception) {
            $_SESSION['updates_message'] = 'Помилка оновлення: ' . $exception->getMessage();
        }

        redirect('/admin/updates');
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
}
