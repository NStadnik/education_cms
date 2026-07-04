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
        return new Response($this->view()->render($template, $data, $layout));
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
