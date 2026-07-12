<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Container;
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

            $newsModerationDone = $db->fetch('select value from settings where name = ?', ['schema_news_moderation']);
            if (($newsModerationDone['value'] ?? '') !== '1') {
                self::addNewsModerationSchema($db);
                self::upgradePublisherRole($db);
                $db->execute('delete from settings where name = ?', ['schema_news_moderation']);
                $db->execute('insert into settings (name, value) values (?, ?)', ['schema_news_moderation', '1']);
            }

            self::ensureNewsViewsSchema($db);

            self::createMediaTable($db);
            self::createFormsTables($db);
            self::upgradeFormsPermissions($db);
            self::createLcloudIntegrationTables($db);
            self::upgradeLcloudPermissions($db);
            $mediaDone = $db->fetch('select value from settings where name = ?', ['schema_media_table']);
            if (($mediaDone['value'] ?? '') !== '1') {
                MediaMetadata::migrateLegacyStorage();
                $db->execute('delete from settings where name = ?', ['schema_media_table']);
                $db->execute('insert into settings (name, value) values (?, ?)', ['schema_media_table', '1']);
            }

            self::ensureSetting($db, 'site_template', 'official');
            self::ensureSetting($db, 'site_mode', 'online');
            self::ensureSetting($db, 'site_mode_title', '');
            self::ensureSetting($db, 'site_mode_message', '');
            self::migrateLcloudConfig($db);
            self::ensureMailSettings($db);
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

    private static function ensureNewsViewsSchema(Database $db): void
    {
        if (!self::hasColumn($db, 'news', 'views_count')) {
            $db->pdo()->exec('alter table news add column views_count bigint unsigned not null default 0 after body');
        }
        $db->pdo()->exec(
            'create table if not exists news_view_stats (
                news_id bigint unsigned not null,
                view_date date not null,
                views_count int unsigned not null default 0,
                primary key (news_id, view_date),
                constraint news_view_stats_news_id_foreign foreign key(news_id) references news(id) on delete cascade
            ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci'
        );
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

    private static function addNewsModerationSchema(Database $db): void
    {
        $columns = [
            'submitted_at' => 'alter table news add column submitted_at varchar(32) null after published_at',
            'submitted_by' => 'alter table news add column submitted_by bigint unsigned null after submitted_at',
            'reviewed_at' => 'alter table news add column reviewed_at varchar(32) null after submitted_by',
            'reviewed_by' => 'alter table news add column reviewed_by bigint unsigned null after reviewed_at',
            'review_comment' => 'alter table news add column review_comment text null after reviewed_by',
            'version' => 'alter table news add column version int unsigned not null default 1 after review_comment',
        ];
        foreach ($columns as $column => $sql) {
            if (!self::hasColumn($db, 'news', $column)) {
                $db->pdo()->exec($sql);
            }
        }

        $db->pdo()->exec(
            "create table if not exists news_moderation_events (
                id bigint unsigned primary key auto_increment,
                news_id bigint unsigned not null,
                user_id bigint unsigned null,
                action varchar(40) not null,
                from_status varchar(40) not null,
                to_status varchar(40) not null,
                comment text null,
                created_at varchar(32) not null,
                index news_moderation_events_news_id_index (news_id),
                constraint news_moderation_events_news_id_foreign foreign key(news_id) references news(id) on delete cascade
            ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci"
        );
    }

    private static function upgradePublisherRole(Database $db): void
    {
        $stored = $db->fetch('select value from settings where name = ?', ['user_roles']);
        $roles = json_decode((string) ($stored['value'] ?? ''), true);
        if (!is_array($roles) || !isset($roles['publisher']) || !is_array($roles['publisher'])) {
            return;
        }
        $permissions = $roles['publisher']['permissions'] ?? [];
        if (!is_array($permissions) || in_array('*', $permissions, true) || !in_array('news.manage', $permissions, true)) {
            return;
        }
        $roles['publisher']['permissions'] = array_values(array_unique([...$permissions, 'news.review', 'news.publish']));
        $encoded = json_encode($roles, JSON_UNESCAPED_UNICODE);
        if ($encoded !== false) {
            $db->execute('update settings set value = ? where name = ?', [$encoded, 'user_roles']);
        }
    }

    private static function createMediaTable(Database $db): void
    {
        $db->pdo()->exec(
            "create table if not exists media (
                id bigint unsigned primary key auto_increment,
                path varchar(255) not null unique,
                original_name varchar(255) not null default '',
                extension varchar(20) not null default '',
                mime_type varchar(120) not null default '',
                size bigint unsigned not null default 0,
                width int unsigned null,
                height int unsigned null,
                modified_at varchar(32) not null,
                folder varchar(80) not null default '',
                alt_text varchar(160) not null default '',
                title varchar(160) not null default '',
                caption varchar(160) not null default '',
                description text null,
                uploaded_by bigint unsigned null,
                created_at varchar(32) not null,
                updated_at varchar(32) not null,
                index media_uploaded_by_index (uploaded_by),
                index media_folder_index (folder)
            ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci"
        );
    }

    private static function createFormsTables(Database $db): void
    {
        $db->pdo()->exec("create table if not exists forms (
            id bigint unsigned primary key auto_increment, created_by bigint unsigned null,
            title varchar(220) not null, slug varchar(180) not null unique, description text null,
            type varchar(40) not null default 'generic', fields_json longtext not null,
            settings_json longtext not null, status varchar(40) not null default 'draft',
            version int unsigned not null default 1, starts_at varchar(32) null, ends_at varchar(32) null,
            created_at varchar(32) not null, updated_at varchar(32) not null,
            index forms_status_index (status)
        ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci");
        $db->pdo()->exec("create table if not exists form_submissions (
            id bigint unsigned primary key auto_increment, form_id bigint unsigned not null,
            form_version int unsigned not null, answers_json longtext not null,
            schema_snapshot_json longtext not null, context_json longtext not null,
            status varchar(40) not null default 'new', submitter_email varchar(180) null,
            ip_hash varchar(64) null, user_agent varchar(500) null,
            created_at varchar(32) not null, updated_at varchar(32) not null,
            index form_submissions_form_id_index (form_id), index form_submissions_status_index (status),
            constraint form_submissions_form_id_foreign foreign key(form_id) references forms(id) on delete cascade
        ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci");
    }

    private static function upgradeFormsPermissions(Database $db): void
    {
        $done = $db->fetch('select value from settings where name=?', ['schema_forms_permissions']);
        if (($done['value'] ?? '') === '1') { return; }
        $stored = $db->fetch('select value from settings where name=?', ['user_roles']);
        $roles = json_decode((string) ($stored['value'] ?? ''), true);
        if (is_array($roles)) {
            foreach (['editor', 'publisher'] as $role) {
                if (!isset($roles[$role]['permissions']) || !is_array($roles[$role]['permissions']) || in_array('*', $roles[$role]['permissions'], true)) { continue; }
                $roles[$role]['permissions'][] = 'forms.manage';
                $roles[$role]['permissions'] = array_values(array_unique($roles[$role]['permissions']));
            }
            $db->execute('update settings set value=? where name=?', [json_encode($roles, JSON_UNESCAPED_UNICODE), 'user_roles']);
        }
        $db->execute('delete from settings where name=?', ['schema_forms_permissions']);
        $db->execute('insert into settings (name,value) values (?,?)', ['schema_forms_permissions', '1']);
    }

    private static function createLcloudIntegrationTables(Database $db): void
    {
        $db->pdo()->exec("create table if not exists external_identities (
            id bigint unsigned primary key auto_increment,
            provider varchar(40) not null,
            external_user_id varchar(190) not null,
            user_id bigint unsigned not null,
            external_institution_id varchar(190) null,
            created_at varchar(32) not null,
            updated_at varchar(32) not null,
            unique key external_identities_provider_user_unique (provider, external_user_id),
            index external_identities_user_id_index (user_id),
            constraint external_identities_user_id_foreign foreign key(user_id) references users(id) on delete cascade
        ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci");
        $db->pdo()->exec("create table if not exists external_auth_nonces (
            id bigint unsigned primary key auto_increment,
            provider varchar(40) not null,
            nonce_hash varchar(64) not null,
            expires_at varchar(32) not null,
            used_at varchar(32) not null,
            unique key external_auth_nonces_provider_nonce_unique (provider, nonce_hash),
            index external_auth_nonces_expires_at_index (expires_at)
        ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci");
    }

    private static function upgradeLcloudPermissions(Database $db): void
    {
        $done = $db->fetch('select value from settings where name=?', ['schema_lcloud_permissions']);
        if (($done['value'] ?? '') === '1') { return; }
        $stored = $db->fetch('select value from settings where name=?', ['user_roles']);
        $roles = json_decode((string) ($stored['value'] ?? ''), true);
        if (!is_array($roles) || $roles === []) {
            $roles = Auth::defaultRolesConfig();
        }
        foreach (['editor', 'publisher'] as $role) {
            if (isset($roles[$role]['permissions']) && is_array($roles[$role]['permissions']) && !in_array('*', $roles[$role]['permissions'], true)) {
                $roles[$role]['permissions'][] = 'news.categories.manage';
                $roles[$role]['permissions'] = array_values(array_unique($roles[$role]['permissions']));
            }
        }
        if (!isset($roles['teacher'])) {
            $roles['teacher'] = ['label' => 'Викладач', 'permissions' => ['news.manage', 'media.manage']];
        }
        $db->execute('delete from settings where name=?', ['user_roles']);
        $db->execute('insert into settings (name,value) values (?,?)', ['user_roles', json_encode($roles, JSON_UNESCAPED_UNICODE) ?: '{}']);
        $db->execute('insert into settings (name,value) values (?,?)', ['schema_lcloud_permissions', '1']);
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

    private static function migrateLcloudConfig(Database $db): void
    {
        $legacy = (array) (Container::get('config')['app']['lcloud'] ?? []);
        foreach ([
            'lcloud_enabled' => !empty($legacy['enabled']) ? '1' : '0',
            'lcloud_issuer' => (string) ($legacy['issuer'] ?? 'lcloud'),
            'lcloud_audience' => (string) ($legacy['audience'] ?? 'education-cms'),
            'lcloud_allowed_origin' => (string) ($legacy['allowed_origin'] ?? ''),
            'lcloud_sso_secret' => (string) ($legacy['sso_secret'] ?? ''),
            'lcloud_api_key' => (string) ($legacy['api_key'] ?? ''),
        ] as $name => $value) {
            self::ensureSetting($db, $name, $value);
        }
    }

    private static function ensureMailSettings(Database $db): void
    {
        foreach ([
            'mail_enabled' => '0',
            'mail_notify_news' => '1',
            'mail_notify_forms' => '1',
            'mail_transport' => 'mail',
            'mail_from_email' => '',
            'mail_from_name' => '',
            'mail_reply_to' => '',
            'mail_smtp_host' => '',
            'mail_smtp_port' => '587',
            'mail_smtp_encryption' => 'tls',
            'mail_smtp_username' => '',
            'mail_smtp_password' => '',
        ] as $name => $value) {
            self::ensureSetting($db, $name, $value);
        }
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
