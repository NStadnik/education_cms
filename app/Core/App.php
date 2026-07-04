<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AdminController;
use App\Controllers\InstallController;
use App\Controllers\PublicController;

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
        date_default_timezone_set($this->config('app.timezone', 'Europe/Kyiv'));
        session_start();

        Container::set('config', [
            'app' => $this->loadConfig('app'),
            'database' => $this->loadDatabaseConfig(),
        ]);

        Container::set('view', new View($this->basePath . '/templates'));
        Container::set('db', new Database(Container::get('config')['database']));
        Container::set('auth', new Auth(Container::get('db')));

        $this->routes();
    }

    public function handle(Request $request): Response
    {
        return $this->router->dispatch($request);
    }

    private function routes(): void
    {
        $public = PublicController::class;
        $admin = AdminController::class;
        $install = InstallController::class;

        $this->router->get('/', [$public, 'home']);
        $this->router->get('/page/{slug}', [$public, 'page']);
        $this->router->get('/news', [$public, 'news']);
        $this->router->get('/news/{slug}', [$public, 'newsShow']);
        $this->router->get('/documents', [$public, 'documents']);
        $this->router->get('/public-info', [$public, 'publicInfo']);
        $this->router->get('/uploads/{path}', [$public, 'upload']);

        $this->router->get('/install', [$install, 'show']);
        $this->router->post('/install', [$install, 'store']);

        $this->router->get('/admin/login', [$admin, 'login']);
        $this->router->post('/admin/login', [$admin, 'authenticate']);
        $this->router->post('/admin/logout', [$admin, 'logout']);
        $this->router->get('/admin', [$admin, 'dashboard']);
        $this->router->get('/admin/pages', [$admin, 'pages']);
        $this->router->get('/admin/pages/edit', [$admin, 'pageForm']);
        $this->router->post('/admin/pages/save', [$admin, 'pageSave']);
        $this->router->get('/admin/news', [$admin, 'news']);
        $this->router->get('/admin/news/edit', [$admin, 'newsForm']);
        $this->router->post('/admin/news/save', [$admin, 'newsSave']);
        $this->router->get('/admin/documents', [$admin, 'documents']);
        $this->router->post('/admin/documents/save', [$admin, 'documentSave']);
        $this->router->get('/admin/public-info', [$admin, 'publicInfo']);
        $this->router->post('/admin/public-info/save', [$admin, 'publicInfoSave']);
        $this->router->get('/admin/users', [$admin, 'users']);
        $this->router->post('/admin/users/save', [$admin, 'userSave']);
        $this->router->get('/admin/settings', [$admin, 'settings']);
        $this->router->post('/admin/settings/save', [$admin, 'settingsSave']);
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
