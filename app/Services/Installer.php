<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

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
            'JSON' => extension_loaded('json'),
            'Fileinfo' => extension_loaded('fileinfo'),
            'storage writable' => is_writable(base_path('storage')),
            'config writable' => is_writable(base_path('config')),
        ];
    }

    public function install(array $data): void
    {
        $driver = $data['driver'] ?? 'sqlite';
        $dbConfig = $driver === 'mysql'
            ? [
                'driver' => 'mysql',
                'host' => $data['db_host'] ?: '127.0.0.1',
                'port' => $data['db_port'] ?: '3306',
                'name' => $data['db_name'],
                'user' => $data['db_user'],
                'password' => $data['db_password'],
                'charset' => 'utf8mb4',
            ]
            : [
                'driver' => 'sqlite',
                'database' => base_path('storage/app.sqlite'),
            ];

        $this->writeLocalConfig($dbConfig, $data);
        $db = new Database($dbConfig);
        $this->migrate($db, $driver);
        $this->seed($db, $data);
        file_put_contents(base_path('storage/installed.lock'), date('c'));
    }

    private function writeLocalConfig(array $dbConfig, array $data): void
    {
        $name = addslashes($data['institution_name'] ?: 'Заклад освіти');
        $theme = addslashes($data['theme'] ?: 'official');
        $export = var_export($dbConfig, true);
        $content = "<?php\n\nreturn [\n    'app' => [\n        'name' => '{$name}',\n        'theme' => '{$theme}',\n        'debug' => false,\n    ],\n    'database' => {$export},\n];\n";
        file_put_contents(base_path('config/local.php'), $content);
    }

    private function migrate(Database $db, string $driver): void
    {
        $file = $driver === 'mysql' ? 'database/mysql_schema.sql' : 'database/schema.sql';
        $sql = file_get_contents(base_path($file));
        $db->pdo()->exec($sql);
    }

    private function seed(Database $db, array $data): void
    {
        $now = date('c');
        $settings = [
            'institution_name' => $data['institution_name'] ?: 'Заклад освіти',
            'institution_type' => $data['institution_type'] ?: 'Заклад освіти',
            'edrpou' => $data['edrpou'] ?: '',
            'address' => $data['address'] ?: '',
            'phone' => $data['phone'] ?: '',
            'email' => $data['institution_email'] ?: '',
        ];

        foreach ($settings as $name => $value) {
            $db->execute('insert into settings (name, value) values (?, ?)', [$name, $value]);
        }

        $db->execute(
            'insert into users (name, email, password_hash, role, is_active, created_at) values (?, ?, ?, ?, 1, ?)',
            [
                $data['admin_name'] ?: 'Адміністратор',
                $data['admin_email'],
                password_hash($data['admin_password'], PASSWORD_DEFAULT),
                'super_admin',
                $now,
            ]
        );

        $this->seedPages($db, $now);
        $this->seedPublicInfo($db, $now);
    }

    private function seedPages(Database $db, string $now): void
    {
        $blocks = json_encode([
            ['type' => 'hero', 'title' => 'Офіційний сайт закладу освіти', 'text' => 'Новини, документи та відкрита публічна інформація в одному місці.'],
            ['type' => 'news_list', 'title' => 'Останні новини', 'limit' => 3],
            ['type' => 'public_info', 'title' => 'Прозорість та інформаційна відкритість'],
        ], JSON_UNESCAPED_UNICODE);

        $db->execute(
            'insert into pages (title, slug, excerpt, blocks_json, status, sort_order, created_at, updated_at) values (?, ?, ?, ?, ?, 0, ?, ?)',
            ['Головна', 'home', 'Головна сторінка', $blocks, 'published', $now, $now]
        );

        $db->execute(
            'insert into pages (title, slug, excerpt, blocks_json, status, sort_order, created_at, updated_at) values (?, ?, ?, ?, ?, 10, ?, ?)',
            ['Про заклад', 'about', 'Коротка інформація про заклад', json_encode([['type' => 'text', 'title' => 'Про заклад', 'text' => 'Додайте опис, історію та пріоритети закладу освіти.']], JSON_UNESCAPED_UNICODE), 'published', $now, $now]
        );
    }

    private function seedPublicInfo(Database $db, string $now): void
    {
        $sections = [
            'statut' => 'Статут закладу освіти',
            'licenses' => 'Ліцензії на провадження освітньої діяльності',
            'management' => 'Структура та органи управління',
            'staff' => 'Кадровий склад',
            'programs' => 'Освітні програми',
            'territory' => 'Територія обслуговування',
            'students' => 'Кількість здобувачів освіти',
            'language' => 'Мова освітнього процесу',
            'vacancies' => 'Вакансії',
            'resources' => 'Матеріально-технічне забезпечення',
            'quality' => 'Результати моніторингу якості освіти',
            'annual-report' => 'Річний звіт',
            'admission' => 'Правила прийому',
            'accessibility' => 'Умови доступності для осіб з ООП',
            'paid-services' => 'Платні послуги',
            'behavior' => 'Правила поведінки здобувача освіти',
            'violence-prevention' => 'Запобігання і протидія насильству',
            'budget' => 'Кошторис та фінансові звіти',
            'charity' => 'Благодійна допомога',
        ];

        $sort = 0;
        foreach ($sections as $slug => $title) {
            $db->execute(
                'insert into public_info_sections (title, slug, description, is_required, sort_order) values (?, ?, ?, 1, ?)',
                [$title, $slug, '', $sort++]
            );
            $sectionId = (int) $db->lastInsertId();
            $db->execute(
                'insert into public_info_items (section_id, title, body, status, responsible, updated_at) values (?, ?, ?, ?, ?, ?)',
                [$sectionId, $title, '', 'missing', '', $now]
            );
        }
    }
}
