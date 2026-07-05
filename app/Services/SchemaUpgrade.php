<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

final class SchemaUpgrade
{
    public static function ensure(Database $db): void
    {
        try {
            $done = $db->fetch('select value from settings where name = ?', ['schema_documents_public_info']);
            if (($done['value'] ?? '') !== '1') {
                self::addDocumentColumns($db);
                self::migratePublicInfoItems($db);
                $db->execute('delete from settings where name = ?', ['schema_documents_public_info']);
                $db->execute('insert into settings (name, value) values (?, ?)', ['schema_documents_public_info', '1']);
            }

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

            self::ensureSetting($db, 'site_template', 'official');
            self::migrateGlobalFields($db);
        } catch (Throwable) {
            // Keep the site bootable; debug.log will still capture hard failures elsewhere.
        }
    }

    private static function addDocumentColumns(Database $db): void
    {
        $columns = [
            'public_info_section_id' => 'integer null',
            'responsible' => 'varchar(160) null',
            'published_at' => 'varchar(32) null',
        ];

        foreach ($columns as $column => $definition) {
            if (self::hasColumn($db, 'documents', $column)) {
                continue;
            }
            $db->pdo()->exec("alter table documents add column {$column} {$definition}");
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

    private static function migratePublicInfoItems(Database $db): void
    {
        if (!self::hasTable($db, 'public_info_items')) {
            return;
        }

        $items = $db->fetchAll(
            'select i.*, s.title as section_title
             from public_info_items i
             left join public_info_sections s on s.id = i.section_id'
        );

        foreach ($items as $item) {
            if (($item['status'] ?? '') === 'missing' && empty($item['file_path']) && empty($item['body'])) {
                continue;
            }

            $db->execute(
                'insert into documents (public_info_section_id, title, category, file_path, description, status, responsible, approved_at, published_at, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $item['section_id'],
                    $item['title'] ?: ($item['section_title'] ?? 'Публічна інформація'),
                    'Публічна інформація',
                    $item['file_path'] ?? null,
                    $item['body'] ?? null,
                    $item['status'] === 'missing' ? 'draft' : $item['status'],
                    $item['responsible'] ?? null,
                    $item['approved_at'] ?? null,
                    $item['published_at'] ?? null,
                    $item['updated_at'] ?? date('c'),
                    $item['updated_at'] ?? date('c'),
                ]
            );
        }
    }

    private static function hasTable(Database $db, string $table): bool
    {
        try {
            $db->fetch("select 1 from {$table} limit 1");
            return true;
        } catch (Throwable) {
            return false;
        }
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
