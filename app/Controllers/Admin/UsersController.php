<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\Files;
use Throwable;

final class UsersController extends \App\Controllers\AdminBaseController
{
    public function users(Request $request): Response
    {
        $this->guard('users.manage');
        $query = trim((string) $request->input('q', ''));
        $pagination = $this->pagination($request);
        [$where, $params] = $this->searchWhere($query, ['name', 'email', 'role']);
        $items = $this->db()->fetchAll(
            'select * from users ' . $where . ' order by id desc limit ' . $pagination['limit'] . ' offset ' . $pagination['offset'],
            $params
        );
        $total = (int) ($this->db()->fetch('select count(*) as c from users ' . $where, $params)['c'] ?? 0);

        if ($this->isAjaxRequest()) {
            return $this->listJson('admin/users/rows', ['items' => $items], $pagination, $total);
        }

        return $this->admin('admin/users/index', [
            'title' => 'Користувачі',
            'items' => $items,
            'total' => $total,
            'limit' => $pagination['limit'],
        ]);
    }

    public function userForm(Request $request): Response
    {
        $this->guard('users.manage');
        $id = (int) $request->input('id', 0);
        $item = $id ? $this->db()->fetch('select * from users where id = ?', [$id]) : null;
        return $this->admin('admin/users/form', ['title' => 'Користувач', 'item' => $item]);
    }

    public function userSave(Request $request): Response
    {
        $this->guard('users.manage');
        Csrf::verify();
        try {
            $id = (int) $request->input('id', 0);
            $password = (string) $request->input('password', '');
            if ($id) {
                $this->db()->execute(
                    'update users set name=?, email=?, role=?, is_active=? where id=?',
                    [$request->input('name'), $request->input('email'), $request->input('role'), $request->input('is_active') ? 1 : 0, $id]
                );
                if ($password !== '') {
                    $this->db()->execute('update users set password_hash=? where id=?', [password_hash($password, PASSWORD_DEFAULT), $id]);
                }
            } else {
                $this->db()->execute(
                    'insert into users (name, email, password_hash, role, is_active, created_at) values (?, ?, ?, ?, 1, ?)',
                    [$request->input('name'), $request->input('email'), password_hash($password, PASSWORD_DEFAULT), $request->input('role'), date('c')]
                );
                $id = (int) $this->db()->lastInsertId();
            }

            if ($this->isAjax($request)) {
                return $this->json(['ok' => true, 'message' => 'Користувача збережено.', 'id' => $id, 'edit_url' => url('/admin/users/edit?id=' . $id)]);
            }

            redirect('/admin/users');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
        }
    }

    public function usersBulk(Request $request): Response
    {
        $this->guard('users.manage');
        Csrf::verify();
        $ids = array_values(array_filter($this->bulkIds($request), static fn (int $id): bool => $id !== (int) ($_SESSION['user_id'] ?? 0)));
        $action = (string) $request->input('bulk_action', '');
        if ($ids && in_array($action, ['activate', 'deactivate'], true)) {
            $this->bulkUpdateUsers($ids, $action === 'activate' ? 1 : 0);
            $this->audit('bulk_' . $action, 'users', null, 'ids: ' . implode(',', $ids));
        } elseif ($ids && $action === 'delete') {
            $this->bulkDelete('users', $ids);
            $this->audit('bulk_delete', 'users', null, 'ids: ' . implode(',', $ids));
        }

        if ($this->isAjax($request)) {
            return $this->json($this->adminListPayload('users', $request, 'Групову дію виконано.'));
        }

        redirect('/admin/users');
    }
}
