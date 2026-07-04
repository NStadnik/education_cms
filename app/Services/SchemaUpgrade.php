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
            if (($done['value'] ?? '') === '1') {
                return;
            }

            self::addDocumentColumns($db);
            self::migratePublicInfoItems($db);
            $db->execute('delete from settings where name = ?', ['schema_documents_public_info']);
            $db->execute('insert into settings (name, value) values (?, ?)', ['schema_documents_public_info', '1']);
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
