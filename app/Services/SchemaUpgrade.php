<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use Throwable;

final class SchemaUpgrade
{
    public static function ensure(Database $db): void
    {
        try {
            $pagesTemplateDone = $db->fetch('select value from settings where name = ?', ['schema_pages_template']);
            if (($pagesTemplateDone['value'] ?? '') !== '1') {
                self::addPageTemplateColumn($db);
                $db->execute('delete from settings where name = ?', ['schema_pages_template']);
                $db->execute('insert into settings (name, value) values (?, ?)', ['schema_pages_template', '1']);
            }

            $newsCategoryDone = $db->fetch('select value from settings where name = ?', ['schema_news_category']);
            if (($newsCategoryDone['value'] ?? '') !== '1') {
                self::addNewsCategoryColumn($db);
                $db->execute('delete from settings where name = ?', ['schema_news_category']);
                $db->execute('insert into settings (name, value) values (?, ?)', ['schema_news_category', '1']);
            }

            $newsImageDone = $db->fetch('select value from settings where name = ?', ['schema_news_image']);
            if (($newsImageDone['value'] ?? '') !== '1') {
                self::addNewsImageColumn($db);
                $db->execute('delete from settings where name = ?', ['schema_news_image']);
                $db->execute('insert into settings (name, value) values (?, ?)', ['schema_news_image', '1']);
            }

            $newsCategoriesTableDone = $db->fetch('select value from settings where name = ?', ['schema_news_categories_table']);
            if (($newsCategoriesTableDone['value'] ?? '') !== '1') {
                self::createNewsCategoriesTable($db);
                self::seedNewsCategories($db);
                $db->execute('delete from settings where name = ?', ['schema_news_categories_table']);
                $db->execute('insert into settings (name, value) values (?, ?)', ['schema_news_categories_table', '1']);
            }

            $newsCategoryParentDone = $db->fetch('select value from settings where name = ?', ['schema_news_category_parent']);
            if (($newsCategoryParentDone['value'] ?? '') !== '1') {
                self::addNewsCategoryParentColumn($db);
                $db->execute('delete from settings where name = ?', ['schema_news_category_parent']);
                $db->execute('insert into settings (name, value) values (?, ?)', ['schema_news_category_parent', '1']);
            }

            $newsCategoryLinksDone = $db->fetch('select value from settings where name = ?', ['schema_news_category_links']);
            if (($newsCategoryLinksDone['value'] ?? '') !== '1') {
                self::createNewsCategoryLinksTable($db);
                self::seedNewsCategoryLinks($db);
                $db->execute('delete from settings where name = ?', ['schema_news_category_links']);
                $db->execute('insert into settings (name, value) values (?, ?)', ['schema_news_category_links', '1']);
            }

            $ownershipDone = $db->fetch('select value from settings where name = ?', ['schema_content_ownership']);
            if (($ownershipDone['value'] ?? '') !== '1') {
                self::addContentOwnershipColumns($db);
                $db->execute('delete from settings where name = ?', ['schema_content_ownership']);
                $db->execute('insert into settings (name, value) values (?, ?)', ['schema_content_ownership', '1']);
            }

            self::ensureSetting($db, 'site_template', 'official');
            self::ensureSetting($db, 'site_mode', 'online');
            self::ensureSetting($db, 'site_mode_title', '');
            self::ensureSetting($db, 'site_mode_message', '');
            self::ensureSetting($db, 'user_roles', json_encode(Auth::defaultRolesConfig(), JSON_UNESCAPED_UNICODE) ?: '{}');
            self::migrateGlobalFields($db);
        } catch (Throwable) {
            // Keep the site bootable; debug.log will still capture hard failures elsewhere.
        }
    }

    private static function addPageTemplateColumn(Database $db): void
    {
        if (self::hasColumn($db, 'pages', 'template')) {
            return;
        }

        $db->pdo()->exec("alter table pages add column template varchar(80) not null default 'default'");
    }

    private static function addNewsCategoryColumn(Database $db): void
    {
        if (self::hasColumn($db, 'news', 'category')) {
            return;
        }

        $db->pdo()->exec("alter table news add column category varchar(160) not null default 'Загальні'");
    }

    private static function addNewsImageColumn(Database $db): void
    {
        if (self::hasColumn($db, 'news', 'image_path')) {
            return;
        }

        $db->pdo()->exec('alter table news add column image_path varchar(255) null after category');
    }

    private static function createNewsCategoriesTable(Database $db): void
    {
        $db->pdo()->exec(
            "create table if not exists news_categories (
                id bigint unsigned primary key auto_increment,
                parent_id bigint unsigned null,
                title varchar(160) not null unique,
                slug varchar(180) not null unique,
                sort_order int not null default 100,
                created_at varchar(32) not null,
                updated_at varchar(32) not null,
                index news_categories_parent_id_index (parent_id),
                constraint news_categories_parent_id_foreign foreign key(parent_id) references news_categories(id) on delete set null
            ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci"
        );
    }

    private static function addNewsCategoryParentColumn(Database $db): void
    {
        if (self::hasColumn($db, 'news_categories', 'parent_id')) {
            return;
        }

        $db->pdo()->exec('alter table news_categories add column parent_id bigint unsigned null after id');
    }

    private static function createNewsCategoryLinksTable(Database $db): void
    {
        $db->pdo()->exec(
            "create table if not exists news_category_links (
                news_id bigint unsigned not null,
                category_id bigint unsigned not null,
                primary key (news_id, category_id),
                index news_category_links_category_id_index (category_id),
                constraint news_category_links_news_id_foreign foreign key(news_id) references news(id) on delete cascade,
                constraint news_category_links_category_id_foreign foreign key(category_id) references news_categories(id) on delete cascade
            ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci"
        );
    }

    private static function addContentOwnershipColumns(Database $db): void
    {
        if (!self::hasColumn($db, 'pages', 'created_by')) {
            $db->pdo()->exec('alter table pages add column created_by bigint unsigned null after id');
        }
        if (!self::hasColumn($db, 'news', 'created_by')) {
            $db->pdo()->exec('alter table news add column created_by bigint unsigned null after id');
        }
    }

    private static function seedNewsCategories(Database $db): void
    {
        $now = date('c');
        $categories = $db->fetchAll("select distinct category from news where category is not null and category <> '' order by category asc");
        if (!$categories) {
            $categories = [['category' => 'Загальні']];
        }

        $sortOrder = 100;
        foreach ($categories as $category) {
            $title = trim((string) ($category['category'] ?? ''));
            if ($title === '' || $db->fetch('select id from news_categories where title = ?', [$title])) {
                continue;
            }

            $db->execute(
                'insert into news_categories (title, slug, sort_order, created_at, updated_at) values (?, ?, ?, ?, ?)',
                [$title, self::uniqueCategorySlug($db, self::categorySlug($title)), $sortOrder, $now, $now]
            );
            $sortOrder += 10;
        }
    }

    private static function seedNewsCategoryLinks(Database $db): void
    {
        $rows = $db->fetchAll(
            "select n.id as news_id, c.id as category_id
             from news n
             inner join news_categories c on c.title = n.category
             left join news_category_links l on l.news_id = n.id and l.category_id = c.id
             where l.news_id is null"
        );

        foreach ($rows as $row) {
            $db->execute(
                'insert into news_category_links (news_id, category_id) values (?, ?)',
                [$row['news_id'], $row['category_id']]
            );
        }
    }

    private static function uniqueCategorySlug(Database $db, string $slug): string
    {
        $slug = $slug ?: 'category';
        $base = $slug;
        $index = 2;
        while ($db->fetch('select id from news_categories where slug = ?', [$slug])) {
            $slug = $base . '-' . $index;
            $index++;
        }

        return $slug;
    }

    private static function categorySlug(string $value): string
    {
        $value = function_exists('mb_strtolower') ? mb_strtolower(trim($value), 'UTF-8') : strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9а-яіїєґ]+/u', '-', $value) ?? '';
        return trim($value, '-') ?: 'category';
    }

    private static function ensureSetting(Database $db, string $name, string $value): void
    {
        $existing = $db->fetch('select value from settings where name = ?', [$name]);
        if ($existing) {
            return;
        }

        $db->execute('insert into settings (name, value) values (?, ?)', [$name, $value]);
    }

    private static function migrateGlobalFields(Database $db): void
    {
        $done = $db->fetch('select value from settings where name = ?', ['schema_global_fields']);
        if (($done['value'] ?? '') === '1') {
            return;
        }

        $current = $db->fetch('select value from settings where name = ?', ['global_fields']);
        $fields = json_decode((string) ($current['value'] ?? '[]'), true);
        if (!is_array($fields)) {
            $fields = [];
        }

        $legacy = [
            'institution_type' => 'Тип',
            'edrpou' => 'ЄДРПОУ',
            'address' => 'Адреса',
            'phone' => 'Телефон',
            'email' => 'Email',
        ];

        foreach ($legacy as $name => $label) {
            $row = $db->fetch('select value from settings where name = ?', [$name]);
            $value = trim((string) ($row['value'] ?? ''));
            if ($value === '') {
                continue;
            }

            $fields[] = ['label' => $label, 'value' => $value];
        }

        $encodedFields = json_encode($fields, JSON_UNESCAPED_UNICODE);
        $db->execute('delete from settings where name = ?', ['global_fields']);
        $db->execute('insert into settings (name, value) values (?, ?)', ['global_fields', $encodedFields === false ? '[]' : $encodedFields]);
        $db->execute('delete from settings where name = ?', ['schema_global_fields']);
        $db->execute('insert into settings (name, value) values (?, ?)', ['schema_global_fields', '1']);
    }

    private static function hasColumn(Database $db, string $table, string $column): bool
    {
        try {
            $db->fetch("select {$column} from {$table} limit 1");
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
