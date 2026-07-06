<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\Files;
use Throwable;

final class UsersController extends \App\Controllers\AdminBaseController
{
    public function profile(Request $request): Response
    {
        $this->guard();
        $user = Container::get('auth')->user() ?: [];
        $message = (string) ($_SESSION['profile_message'] ?? '');
        unset($_SESSION['profile_message']);

        return $this->admin('admin/users/profile', [
            'title' => 'Профіль',
            'item' => $user,
            'message' => $message,
            'roleLabels' => Auth::ROLE_LABELS,
            'rolePermissions' => Auth::rolePermissions(),
            'permissionCatalog' => Auth::PERMISSION_CATALOG,
        ]);
    }

    public function profileSave(Request $request): Response
    {
        $this->guard();
        Csrf::verify();

        try {
            $user = Container::get('auth')->user();
            if (!$user) {
                throw new \RuntimeException('Сесію завершено. Увійдіть знову.');
            }

            $id = (int) $user['id'];
            $name = trim((string) $request->input('name', ''));
            $email = strtolower(trim((string) $request->input('email', '')));
            $currentPassword = (string) $request->input('current_password', '');
            $newPassword = (string) $request->input('new_password', '');
            $passwordConfirmation = (string) $request->input('password_confirmation', '');

            if ($name === '') {
                throw new \InvalidArgumentException('Вкажіть імʼя.');
            }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Вкажіть коректний email.');
            }

            $duplicate = $this->db()->fetch('select id from users where email = ? and id <> ? limit 1', [$email, $id]);
            if ($duplicate) {
                throw new \InvalidArgumentException('Користувач із таким email вже існує.');
            }

            $passwordTouched = $currentPassword !== '' || $newPassword !== '' || $passwordConfirmation !== '';
            $params = [$name, $email, $id];
            $sql = 'update users set name=?, email=? where id=?';

            if ($passwordTouched) {
                if ($currentPassword === '' || !password_verify($currentPassword, (string) ($user['password_hash'] ?? ''))) {
                    throw new \InvalidArgumentException('Поточний пароль введено неправильно.');
                }
                if (strlen($newPassword) < 8) {
                    throw new \InvalidArgumentException('Новий пароль має містити щонайменше 8 символів.');
                }
                if ($newPassword !== $passwordConfirmation) {
                    throw new \InvalidArgumentException('Підтвердження пароля не збігається.');
                }
                $sql = 'update users set name=?, email=?, password_hash=? where id=?';
                $params = [$name, $email, password_hash($newPassword, PASSWORD_DEFAULT), $id];
            }

            $this->db()->execute($sql, $params);

            if ($this->isAjax($request)) {
                return $this->json([
                    'ok' => true,
                    'message' => 'Профіль оновлено.',
                    'user_name' => $name,
                    'user_email' => $email,
                    'clear_password_fields' => true,
                ]);
            }

            $_SESSION['profile_message'] = 'Профіль оновлено.';
            redirect('/admin/profile');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
        }
    }

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
        return $this->admin('admin/users/form', [
            'title' => 'Користувач',
            'item' => $item,
            'roleLabels' => Auth::ROLE_LABELS,
            'rolePermissions' => Auth::rolePermissions(),
            'permissionCatalog' => Auth::PERMISSION_CATALOG,
        ]);
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
