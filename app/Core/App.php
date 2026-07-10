<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AdminController;
use App\Controllers\Admin\ImportController;
use App\Controllers\Admin\MediaController;
use App\Controllers\Admin\NewsController;
use App\Controllers\Admin\OptimizerController;
use App\Controllers\Admin\PagesController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\UpdatesController;
use App\Controllers\Admin\UsersController;
use App\Controllers\ErrorController;
use App\Controllers\InstallController;
use App\Controllers\PublicController;
use App\Controllers\LcloudApiController;
use App\Services\Installer;
use App\Services\SchemaUpgrade;
use Throwable;


final class App
{
    private Router $router;

    public function __construct(private readonly string $basePath)
    {
        Container::set('base_path', $basePath);
        $this->router = new Router();
    }

    public function boot(): void
    {
        Debug::register($this->basePath);
        date_default_timezone_set($this->config('app.timezone', 'Europe/Kyiv'));
        $this->startSession();

        Container::set('config', [
            'app' => $this->loadConfig('app'),
            'database' => $this->loadDatabaseConfig(),
        ]);

        Container::set('view', new View($this->basePath . '/templates'));
        Container::set('db', new Database(Container::get('config')['database']));
        Container::set('auth', new Auth(Container::get('db')));
        if (Installer::installed()) {
            SchemaUpgrade::ensure(Container::get('db'));
        }

        $this->routes();
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $sessionPath = $this->basePath . '/storage/sessions';
        if (!is_dir($sessionPath)) {
            mkdir($sessionPath, 0775, true);
        }
        if (is_writable($sessionPath)) {
            session_save_path($sessionPath);
        }

        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'use_strict_mode' => true,
        ]);
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (Throwable $exception) {
            if (Debug::enabled($this->basePath)) {
                throw $exception;
            }

            if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
                return new Response(json_encode(['ok' => false, 'message' => 'Помилка сервера.'], JSON_UNESCAPED_UNICODE), 500, [
                    'Content-Type' => 'application/json; charset=UTF-8',
                ]);
            }

            return ErrorController::response(500);
        }
    }

    private function routes(): void
    {
        $public = PublicController::class;
        $admin = AdminController::class;
        $adminPages = PagesController::class;
        $adminNews = NewsController::class;
        $adminMedia = MediaController::class;
        $adminOptimizer = OptimizerController::class;
        $adminUsers = UsersController::class;
        $adminSettings = SettingsController::class;
        $adminImport = ImportController::class;
        $adminUpdates = UpdatesController::class;
        $install = InstallController::class;
        $lcloudApi = LcloudApiController::class;

        $this->router->get('/', [$public, 'home']);
        $this->router->get('/page/{slug}', [$public, 'page']);
        $this->router->get('/news', [$public, 'news']);
        $this->router->get('/news/{slug}', [$public, 'newsShow']);
        $this->router->get('/thumb/{path}', [$public, 'thumb']);
        $this->router->get('/assets/{path}', [$public, 'asset']);
        $this->router->get('/uploads/{path}', [$public, 'upload']);
        $this->router->get('/debug', [$public, 'debug']);

        $this->router->get('/install', [$install, 'show']);
        $this->router->post('/install', [$install, 'store']);

        $this->router->get('/admin/login', [$admin, 'login']);
        $this->router->post('/admin/login', [$admin, 'authenticate']);
        $this->router->post('/admin/logout', [$admin, 'logout']);
        $this->router->get('/admin', [$admin, 'dashboard']);
        $this->router->get('/admin/profile', [$adminUsers, 'profile']);
        $this->router->post('/admin/profile/save', [$adminUsers, 'profileSave']);
        $this->router->get('/admin/pages', [$adminPages, 'pages']);
        $this->router->get('/admin/pages/edit', [$adminPages, 'pageForm']);
        $this->router->post('/admin/pages/save', [$adminPages, 'pageSave']);
        $this->router->post('/admin/pages/bulk', [$adminPages, 'pagesBulk']);
        $this->router->get('/admin/news', [$adminNews, 'news']);
        $this->router->get('/admin/news/edit', [$adminNews, 'newsForm']);
        $this->router->get('/admin/news/categories', [$adminNews, 'newsCategories']);
        $this->router->post('/admin/news/save', [$adminNews, 'newsSave']);
        $this->router->post('/admin/news/submit', [$adminNews, 'newsSubmit']);
        $this->router->post('/admin/news/request-changes', [$adminNews, 'newsRequestChanges']);
        $this->router->post('/admin/news/publish', [$adminNews, 'newsPublish']);
        $this->router->post('/admin/news/unpublish', [$adminNews, 'newsUnpublish']);
        $this->router->post('/admin/news/categories/save', [$adminNews, 'newsCategorySave']);
        $this->router->post('/admin/news/categories/delete', [$adminNews, 'newsCategoryDelete']);
        $this->router->post('/admin/news/bulk', [$adminNews, 'newsBulk']);
        $this->router->get('/admin/media', [$adminMedia, 'media']);
        $this->router->get('/admin/media/picker', [$adminMedia, 'mediaPicker']);
        $this->router->post('/admin/media/upload', [$adminMedia, 'mediaUpload']);
        $this->router->post('/admin/media/metadata', [$adminMedia, 'mediaMetadataSave']);
        $this->router->post('/admin/media/delete', [$adminMedia, 'mediaDelete']);
        $this->router->post('/admin/media/bulk', [$adminMedia, 'mediaBulk']);
        $this->router->get('/admin/optimizer', [$adminOptimizer, 'index']);
        $this->router->get('/admin/optimizer/media-folders', [$adminOptimizer, 'mediaFolders']);
        $this->router->post('/admin/optimizer/media-folders/apply', [$adminOptimizer, 'applyMediaFolders']);
        $this->router->post('/admin/optimizer/cache/clear', [$adminOptimizer, 'clearCache']);
        $this->router->post('/admin/optimizer/debug/toggle', [$adminOptimizer, 'toggleDebug']);
        $this->router->get('/admin/users', [$adminUsers, 'users']);
        $this->router->get('/admin/users/roles', [$adminUsers, 'roles']);
        $this->router->get('/admin/users/roles/edit', [$adminUsers, 'roleForm']);
        $this->router->get('/admin/users/edit', [$adminUsers, 'userForm']);
        $this->router->post('/admin/users/save', [$adminUsers, 'userSave']);
        $this->router->post('/admin/users/roles/save', [$adminUsers, 'rolesSave']);
        $this->router->post('/admin/users/roles/delete', [$adminUsers, 'roleDelete']);
        $this->router->post('/admin/users/bulk', [$adminUsers, 'usersBulk']);
        $this->router->get('/admin/link-picker', [$adminSettings, 'adminLinkPicker']);
        $this->router->get('/admin/templates', [$adminSettings, 'templates']);
        $this->router->get('/admin/templates/link-picker', [$adminSettings, 'templatesLinkPicker']);
        $this->router->post('/admin/templates/save', [$adminSettings, 'templatesSave']);
        $this->router->get('/admin/import', [$adminImport, 'import']);
        $this->router->post('/admin/import/preview', [$adminImport, 'importPreview']);
        $this->router->post('/admin/import/run', [$adminImport, 'importRun']);
        $this->router->get('/admin/updates', [$adminUpdates, 'index']);
        $this->router->post('/admin/updates/check', [$adminUpdates, 'check']);
        $this->router->post('/admin/updates/install', [$adminUpdates, 'install']);
        $this->router->get('/admin/settings', [$adminSettings, 'settings']);
        $this->router->post('/admin/settings/save', [$adminSettings, 'settingsSave']);
        $this->router->get('/api/lcloud/publications',[$lcloudApi, 'publications']);
    }

    private function loadConfig(string $name): array
    {
        $config = require $this->basePath . "/config/{$name}.php";
        $local = $this->basePath . '/config/local.php';

        if (is_file($local)) {
            $localConfig = require $local;
            if (isset($localConfig[$name]) && is_array($localConfig[$name])) {
                $config = array_replace_recursive($config, $localConfig[$name]);
            }
        }

        return $config;
    }

    private function loadDatabaseConfig(): array
    {
        $config = require $this->basePath . '/config/database.php';
        $local = $this->basePath . '/config/local.php';

        if (is_file($local)) {
            $localConfig = require $local;
            if (isset($localConfig['database']) && is_array($localConfig['database'])) {
                $config = array_replace_recursive($config, $localConfig['database']);
            }
        }

        return $config;
    }

    private function config(string $key, mixed $default = null): mixed
    {
        [$group, $item] = explode('.', $key, 2);
        $config = $this->loadConfig($group);
        return $config[$item] ?? $default;
    }
}
