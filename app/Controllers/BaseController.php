<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;

abstract class BaseController
{
    protected function db(): Database
    {
        return Container::get('db');
    }

    protected function view(): View
    {
        return Container::get('view');
    }

    protected function render(string $template, array $data = [], ?string $layout = 'layouts/site'): Response
    {
        if ($layout === 'layouts/site' && !array_key_exists('adminToolbar', $data)) {
            $data['adminToolbar'] = $this->adminToolbar();
        }
        return new Response($this->view()->render($template, $data, $layout));
    }

    protected function adminToolbar(?string $resource = null, ?array $item = null): ?array
    {
        $auth = Container::get('auth');
        $user = $auth->user();
        if (!$user) {
            return null;
        }

        $actions = [];
        if ($resource === 'page' && !empty($item['id']) && $auth->can('pages.manage')) {
            $ownsItem = (int) ($item['created_by'] ?? 0) === (int) $user['id'];
            if ($ownsItem || $auth->can('content.manage_all')) {
                $actions[] = [
                    'label' => !empty($item['slug']) && $item['slug'] === 'home' ? 'Редагувати головну' : 'Редагувати сторінку',
                    'url' => url('/admin/pages/edit?id=' . (int) $item['id']),
                    'icon' => 'mdi-file-document-edit-outline',
                    'primary' => true,
                ];
            }
        } elseif ($resource === 'news' && !empty($item['id'])) {
            $ownsItem = (int) ($item['created_by'] ?? 0) === (int) $user['id'];
            $canAccess = $ownsItem
                || $auth->can('content.manage_all')
                || $auth->can('news.review')
                || $auth->can('news.publish');
            if ($canAccess && ($auth->can('news.manage') || $auth->can('news.review') || $auth->can('news.publish'))) {
                $canEdit = $auth->can('news.manage') && $auth->can('news.publish');
                $actions[] = [
                    'label' => $canEdit ? 'Редагувати новину' : 'Відкрити в адмінці',
                    'url' => url('/admin/news/edit?id=' . (int) $item['id']),
                    'icon' => $canEdit ? 'mdi-pencil-outline' : 'mdi-eye-outline',
                    'primary' => true,
                ];
            }
        }

        return [
            'user' => $user,
            'actions' => $actions,
            'canPages' => $auth->can('pages.manage'),
            'canNews' => $auth->can('news.manage') || $auth->can('news.review') || $auth->can('news.publish'),
            'canCreateNews' => $auth->can('news.manage'),
        ];
    }

    protected function json(array $data, int $status = 200): Response
    {
        return new Response(json_encode($data, JSON_UNESCAPED_UNICODE), $status, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    protected function siteSettings(): array
    {
        $rows = $this->db()->fetchAll('select name, value from settings');
        return array_column($rows, 'value', 'name');
    }

    protected function audit(string $action, string $entity, ?int $entityId = null, string $details = ''): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        $this->db()->execute(
            'insert into audit_logs (user_id, action, entity, entity_id, details, created_at) values (?, ?, ?, ?, ?, ?)',
            [$userId, $action, $entity, $entityId, $details, date('c')]
        );
    }
}
