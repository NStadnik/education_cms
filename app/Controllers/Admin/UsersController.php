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
            'roleLabels' => Container::get('auth')->roleLabels(),
            'rolePermissions' => Container::get('auth')->rolePermissionsForAll(),
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
            return $this->listJson('admin/users/rows', ['items' => $items, 'roleLabels' => Container::get('auth')->roleLabels()], $pagination, $total);
        }

        return $this->admin('admin/users/index', [
            'title' => 'Користувачі',
            'items' => $items,
            'total' => $total,
            'limit' => $pagination['limit'],
            'roleLabels' => Container::get('auth')->roleLabels(),
        ]);
    }

    public function userForm(Request $request): Response
    {
        $this->guard('users.manage');
        $id = (int) $request->input('id', 0);
        $item = $id ? $this->db()->fetch("select u.*, (select ei.external_user_id from external_identities ei where ei.user_id=u.id and ei.provider='lcloud' limit 1) as lcloud_user_id from users u where u.id = ?", [$id]) : null;
        return $this->admin('admin/users/form', [
            'title' => 'Користувач',
            'item' => $item,
            'roleLabels' => Container::get('auth')->roleLabels(),
            'rolePermissions' => Container::get('auth')->rolePermissionsForAll(),
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
            $role = (string) $request->input('role', '');
            $lcloudUserId = trim((string) $request->input('lcloud_user_id', ''));
            $currentUser = Container::get('auth')->user() ?: [];
            $availableRoles = Container::get('auth')->roleLabels();
            if ($role === 'super_admin') {
                if (($currentUser['role'] ?? '') !== 'super_admin') {
                    throw new \InvalidArgumentException('Призначати системну роль може лише супер адміністратор.');
                }
            } elseif (!array_key_exists($role, $availableRoles)) {
                throw new \InvalidArgumentException('Оберіть коректну роль.');
            }
            if ($lcloudUserId !== '') {
                $duplicateIdentity = $this->db()->fetch(
                    'select user_id from external_identities where provider=? and external_user_id=?' . ($id ? ' and user_id<>?' : ''),
                    $id ? ['lcloud', $lcloudUserId, $id] : ['lcloud', $lcloudUserId]
                );
                if ($duplicateIdentity) {
                    throw new \InvalidArgumentException('Цей ID ЛКЛАУД уже прив’язаний до іншого користувача.');
                }
            }

            if ($id) {
                $this->db()->execute(
                    'update users set name=?, email=?, role=?, is_active=? where id=?',
                    [$request->input('name'), $request->input('email'), $role, $request->input('is_active') ? 1 : 0, $id]
                );
                if ($password !== '') {
                    $this->db()->execute('update users set password_hash=? where id=?', [password_hash($password, PASSWORD_DEFAULT), $id]);
                }
            } else {
                $this->db()->execute(
                    'insert into users (name, email, password_hash, role, is_active, created_at) values (?, ?, ?, ?, 1, ?)',
                    [$request->input('name'), $request->input('email'), password_hash($password, PASSWORD_DEFAULT), $role, date('c')]
                );
                $id = (int) $this->db()->lastInsertId();
            }

            $existingIdentity = $this->db()->fetch('select id from external_identities where provider=? and user_id=?', ['lcloud', $id]);
            if ($lcloudUserId === '' && $existingIdentity) {
                $this->db()->execute('delete from external_identities where id=?', [$existingIdentity['id']]);
            } elseif ($lcloudUserId !== '' && $existingIdentity) {
                $this->db()->execute('update external_identities set external_user_id=?,updated_at=? where id=?', [$lcloudUserId, date('c'), $existingIdentity['id']]);
            } elseif ($lcloudUserId !== '') {
                $now = date('c');
                $this->db()->execute('insert into external_identities (provider,external_user_id,user_id,created_at,updated_at) values (?,?,?,?,?)', ['lcloud', $lcloudUserId, $id, $now, $now]);
            }

            if ($this->isAjax($request)) {
                return $this->json(['ok' => true, 'message' => 'Користувача збережено.', 'id' => $id, 'edit_url' => url('/admin/users/edit?id=' . $id)]);
            }

            redirect('/admin/users');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
        }
    }

    public function roles(Request $request): Response
    {
        $this->guard('users.manage');
        $auth = Container::get('auth');
        $message = (string) ($_SESSION['roles_message'] ?? '');
        unset($_SESSION['roles_message']);

        return $this->admin('admin/users/roles', [
            'title' => 'Ролі користувачів',
            'roles' => $auth->roles(),
            'systemRoleLabel' => 'Супер адміністратор',
            'roleUsage' => $this->roleUsage(),
            'permissionCatalog' => Auth::PERMISSION_CATALOG,
            'message' => $message,
        ]);
    }

    public function roleForm(Request $request): Response
    {
        $this->guard('users.manage');
        $slug = $this->normalizeRoleSlug((string) $request->input('role', ''));
        $roles = Container::get('auth')->roles();
        $item = $slug !== '' ? ($roles[$slug] ?? null) : null;

        if ($slug !== '' && !$item) {
            $_SESSION['roles_message'] = 'Роль не знайдено.';
            redirect('/admin/users/roles');
        }

        return $this->admin('admin/users/role-form', [
            'title' => $slug !== '' ? 'Редагувати роль' : 'Нова роль',
            'slug' => $slug,
            'item' => $item,
            'isEdit' => $slug !== '',
            'isUsed' => $slug !== '' && (($this->roleUsage()[$slug] ?? 0) > 0),
            'permissionCatalog' => Auth::PERMISSION_CATALOG,
        ]);
    }

    public function rolesSave(Request $request): Response
    {
        $this->guard('users.manage');
        Csrf::verify();

        try {
            $oldSlug = $this->normalizeRoleSlug((string) $request->input('old_slug', ''));
            $slug = $this->normalizeRoleSlug((string) $request->input('slug', ''));
            $label = trim((string) $request->input('label', ''));
            $permissions = $request->input('permissions', []);
            if (!is_array($permissions)) {
                $permissions = [];
            }
            if ($slug === '' || $slug === 'super_admin') {
                throw new \InvalidArgumentException('Вкажіть коректний код ролі.');
            }
            if ($label === '') {
                throw new \InvalidArgumentException('Вкажіть назву ролі.');
            }

            $roles = Container::get('auth')->roles();
            $usage = $this->roleUsage();
            if ($oldSlug !== '' && !isset($roles[$oldSlug])) {
                throw new \InvalidArgumentException('Роль не знайдено.');
            }
            if ($oldSlug !== '' && $oldSlug !== $slug && (($usage[$oldSlug] ?? 0) > 0)) {
                throw new \InvalidArgumentException('Не можна змінити код ролі, яку призначено користувачам.');
            }
            if ($oldSlug !== $slug && isset($roles[$slug])) {
                throw new \InvalidArgumentException('Роль із таким кодом вже існує.');
            }

            if ($oldSlug !== '' && $oldSlug !== $slug) {
                unset($roles[$oldSlug]);
            }
            $roles[$slug] = ['label' => $label, 'permissions' => $permissions];

            $this->saveRoleMap($roles);
            $this->audit('save', 'user_roles');

            if ($this->isAjax($request)) {
                return $this->json([
                    'ok' => true,
                    'message' => 'Роль збережено.',
                    'role_slug' => $slug,
                    'role_label' => $label,
                    'edit_url' => url('/admin/users/roles/edit?role=' . rawurlencode($slug)),
                ]);
            }

            $_SESSION['roles_message'] = 'Роль збережено.';
            redirect('/admin/users/roles');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
        }
    }

    public function roleDelete(Request $request): Response
    {
        $this->guard('users.manage');
        Csrf::verify();

        try {
            $slug = $this->normalizeRoleSlug((string) $request->input('role', ''));
            $roles = Container::get('auth')->roles();
            if ($slug === '' || !isset($roles[$slug])) {
                throw new \InvalidArgumentException('Роль не знайдено.');
            }
            if (($this->roleUsage()[$slug] ?? 0) > 0) {
                throw new \InvalidArgumentException('Не можна видалити роль, яку призначено користувачам.');
            }

            unset($roles[$slug]);
            $this->saveRoleMap($roles);
            $this->audit('delete', 'user_roles', null, $slug);

            $_SESSION['roles_message'] = 'Роль видалено.';
            if ($this->isAjax($request)) {
                $roles = Container::get('auth')->roles();
                return $this->json([
                    'ok' => true,
                    'message' => 'Роль видалено.',
                    'html' => $this->roleRowsHtml($roles),
                    'total' => count($roles),
                ]);
            }

            redirect('/admin/users/roles');
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

    private function roleUsage(): array
    {
        $rows = $this->db()->fetchAll('select role, count(*) as users_count from users group by role');
        $usage = [];
        foreach ($rows as $row) {
            $usage[(string) $row['role']] = (int) $row['users_count'];
        }

        return $usage;
    }

    private function normalizeRoleSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9_]+/', '_', $slug) ?? '';
        return trim($slug, '_');
    }

    private function saveRoleMap(array $roles): void
    {
        $payload = [];
        foreach ($roles as $slug => $role) {
            if (!is_array($role)) {
                continue;
            }
            $payload[] = [
                'slug' => (string) $slug,
                'label' => (string) ($role['label'] ?? $slug),
                'permissions' => is_array($role['permissions'] ?? null) ? $role['permissions'] : [],
            ];
        }

        Container::get('auth')->saveRoles($payload);
    }

    private function roleRowsHtml(?array $roles = null): string
    {
        return $this->view()->partial('admin/users/role-rows', [
            'roles' => $roles ?? Container::get('auth')->roles(),
            'roleUsage' => $this->roleUsage(),
            'permissionCatalog' => Auth::PERMISSION_CATALOG,
        ]);
    }
}
