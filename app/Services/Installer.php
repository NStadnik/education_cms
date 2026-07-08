<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class Installer
{
    public static function installed(): bool
    {
        return is_file(base_path('storage/installed.lock'));
    }

    public static function requirements(): array
    {
        return [
            'PHP 8.2+' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'PDO' => extension_loaded('pdo'),
            'PDO MySQL' => extension_loaded('pdo_mysql'),
            'JSON' => extension_loaded('json'),
            'Fileinfo' => extension_loaded('fileinfo'),
            'storage writable' => is_writable(base_path('storage')),
            'config writable' => is_writable(base_path('config')),
        ];
    }

    public function install(array $data): void
    {
        $dbConfig = [
            'driver' => 'mysql',
            'host' => $data['db_host'] ?: '127.0.0.1',
            'port' => $data['db_port'] ?: '3306',
            'name' => $data['db_name'],
            'user' => $data['db_user'],
            'password' => $data['db_password'],
            'charset' => 'utf8mb4',
        ];

        $this->validateDriver();
        $db = new Database($dbConfig);
        $db->pdo();
        $this->migrate($db);
        $this->seed($db, $data);
        $this->writeLocalConfig($dbConfig, $data);
        file_put_contents(base_path('storage/installed.lock'), date('c'));
    }

    private function validateDriver(): void
    {
        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('На сервері немає pdo_mysql. Попросіть хостинг увімкнути PHP extension pdo_mysql.');
        }
    }

    private function writeLocalConfig(array $dbConfig, array $data): void
    {
        $name = addslashes($data['institution_name'] ?: 'Заклад освіти');
        $export = var_export($dbConfig, true);
        $content = "<?php\n\nreturn [\n    'app' => [\n        'name' => '{$name}',\n        'theme' => 'official',\n        'debug' => false,\n    ],\n    'database' => {$export},\n];\n";
        file_put_contents(base_path('config/local.php'), $content);
    }

    private function migrate(Database $db): void
    {
        $this->dropPartialInstall($db);
        $file = 'database/mysql_schema.sql';
        $sql = file_get_contents(base_path($file));
        $db->pdo()->exec($sql);
    }

    private function dropPartialInstall(Database $db): void
    {
        $pdo = $db->pdo();
        $pdo->exec('set foreign_key_checks = 0');

        foreach ([
            'audit_logs',
            'news_category_links',
            'news_categories',
            'news',
            'pages',
            'users',
            'settings',
        ] as $table) {
            $pdo->exec("drop table if exists {$table}");
        }

        $pdo->exec('set foreign_key_checks = 1');
    }

    private function seed(Database $db, array $data): void
    {
        $now = date('c');
        $settings = [
            'institution_name' => $data['institution_name'] ?: 'Заклад освіти',
            'global_fields' => '[]',
            'site_template' => 'official',
            'site_mode' => 'online',
            'site_mode_title' => '',
            'site_mode_message' => '',
        ];

        foreach ($settings as $name => $value) {
            $db->execute('insert into settings (name, value) values (?, ?)', [$name, $value]);
        }

        $db->execute(
            'insert into users (name, email, password_hash, role, is_active, created_at) values (?, ?, ?, ?, 1, ?)',
            [
                $data['admin_name'] ?? 'Адміністратор',
                $data['admin_email'],
                password_hash($data['admin_password'], PASSWORD_DEFAULT),
                'super_admin',
                $now,
            ]
        );

        $this->seedPages($db, $now);
        $this->seedNewsCategories($db, $now);
    }

    private function seedPages(Database $db, string $now): void
    {
        $blocks = json_encode([
            ['type' => 'hero', 'title' => 'Офіційний сайт закладу освіти', 'text' => 'Новини та інформація закладу освіти в одному місці.'],
            ['type' => 'news_list', 'title' => 'Останні новини', 'limit' => 3],
        ], JSON_UNESCAPED_UNICODE);

        $db->execute(
            'insert into pages (title, slug, excerpt, template, blocks_json, status, sort_order, created_at, updated_at) values (?, ?, ?, ?, ?, ?, 0, ?, ?)',
            ['Головна', 'home', 'Головна сторінка', 'default', $blocks, 'published', $now, $now]
        );

        $db->execute(
            'insert into pages (title, slug, excerpt, template, blocks_json, status, sort_order, created_at, updated_at) values (?, ?, ?, ?, ?, ?, 10, ?, ?)',
            ['Про заклад', 'about', 'Коротка інформація про заклад', 'default', json_encode([['type' => 'text', 'title' => 'Про заклад', 'text' => 'Додайте опис, історію та пріоритети закладу освіти.']], JSON_UNESCAPED_UNICODE), 'published', $now, $now]
        );
    }

    private function seedNewsCategories(Database $db, string $now): void
    {
        $db->execute(
            'insert into news_categories (title, slug, sort_order, created_at, updated_at) values (?, ?, ?, ?, ?)',
            ['Загальні', 'загальні', 100, $now, $now]
        );
    }
}
