<?php
    $isEdit = !empty($item['id']);
    $canDelete = $isEdit && (int) $item['id'] !== (int) ($_SESSION['user_id'] ?? 0);
    $roleLabels = is_array($roleLabels ?? null) ? $roleLabels : [];
    $rolePermissions = is_array($rolePermissions ?? null) ? $rolePermissions : [];
    $permissionCatalog = is_array($permissionCatalog ?? null) ? $permissionCatalog : [];
    $selectedRole = (string) ($item['role'] ?? 'editor');
    if (!array_key_exists($selectedRole, $roleLabels)) {
        if ($selectedRole !== '') {
            $roleLabels[$selectedRole] = $selectedRole;
            $rolePermissions[$selectedRole] = $selectedRole === 'super_admin' ? ['*'] : ($rolePermissions[$selectedRole] ?? []);
        } else {
            $selectedRole = 'editor';
        }
    }
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Доступ</p>
        <h1><?= $isEdit ? 'Редагувати користувача' : 'Новий користувач' ?></h1>
        <p class="page-subtitle">Налаштуйте обліковий запис, роль і активність користувача.</p>
    </div>
    <a class="button secondary" href="<?= url('/admin/users') ?>"><span class="mdi mdi-arrow-left" aria-hidden="true"></span><span>До списку</span></a>
</div>

<form method="post" action="<?= url('/admin/users/save') ?>">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= e((string) ($item['id'] ?? '')) ?>">

    <div class="editor-layout">
        <section class="card admin-form-card">
            <div class="form-section-head">
                <div>
                    <h2>Профіль</h2>
                    <p class="meta">Email використовується для входу в адмінпанель.</p>
                </div>
            </div>
            <div class="form-grid wide">
                <label>Ім'я<input name="name" value="<?= e($item['name'] ?? '') ?>" required></label>
                <label>Email<input type="email" name="email" value="<?= e($item['email'] ?? '') ?>" required></label>
                <label>Пароль<input type="password" name="password" <?= $isEdit ? '' : 'required' ?>></label>
                <?php if ($isEdit): ?><div class="hint-box">Залиште пароль порожнім, щоб не змінювати його.</div><?php endif; ?>
            </div>

            <section class="user-permission-card" data-permission-matrix data-selected-role="<?= e($selectedRole) ?>">
                <div class="user-permission-head">
                    <div>
                        <p class="eyebrow">Матриця</p>
                        <h2>Матриця повноважень</h2>
                        <p class="meta">Показує, які розділи адмінпанелі відкриває кожна роль.</p>
                    </div>
                    <span class="user-role-pill" data-selected-role-label><?= e($roleLabels[$selectedRole] ?? $selectedRole) ?></span>
                </div>
                <div class="user-permission-table-wrap">
                    <table class="user-permission-table">
                        <thead>
                            <tr>
                                <th scope="col">Повноваження</th>
                                <?php foreach ($roleLabels as $role => $label): ?>
                                    <th scope="col" data-role-column="<?= e($role) ?>"><?= e($label) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($permissionCatalog as $permission => $permissionInfo): ?>
                                <tr>
                                    <th scope="row">
                                        <span><?= e((string) ($permissionInfo['label'] ?? $permission)) ?></span>
                                        <small><?= e((string) ($permissionInfo['group'] ?? '')) ?> · <?= e($permission) ?></small>
                                        <em><?= e((string) ($permissionInfo['description'] ?? '')) ?></em>
                                    </th>
                                    <?php foreach ($roleLabels as $role => $label): ?>
                                        <?php
                                            $allowed = $rolePermissions[$role] ?? [];
                                            $hasPermission = in_array('*', $allowed, true) || in_array($permission, $allowed, true);
                                        ?>
                                        <td data-role-column="<?= e($role) ?>" data-granted="<?= $hasPermission ? '1' : '0' ?>">
                                            <span class="mdi <?= $hasPermission ? 'mdi-check-circle-outline' : 'mdi-minus-circle-outline' ?>" aria-hidden="true"></span>
                                            <span class="visually-hidden"><?= $hasPermission ? 'Дозволено' : 'Немає доступу' ?></span>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>

        <aside class="card admin-form-card editor-sidebar">
            <div class="form-section-head">
                <div>
                    <h2>Права</h2>
                    <p class="meta">Роль визначає доступні розділи адмінпанелі.</p>
                </div>
            </div>
            <div class="form-grid">
                <label>Роль
                    <select name="role" data-role-select>
                        <?php foreach ($roleLabels as $role => $label): ?>
                            <option value="<?= e($role) ?>" <?= selected($selectedRole, $role) ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="check-row"><input type="checkbox" name="is_active" value="1" <?= checked($item['is_active'] ?? 1) ?>> Активний</label>
            </div>
            <div class="form-actions stacked">
                <button type="submit"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span>Зберегти користувача</span></button>
                <?php if ($canDelete): ?>
                    <button class="button danger" type="submit" form="userDeleteForm"><span class="mdi mdi-delete-outline" aria-hidden="true"></span><span>Видалити</span></button>
                <?php endif; ?>
                <a class="button secondary" href="<?= url('/admin/users') ?>"><span class="mdi mdi-close" aria-hidden="true"></span><span>Скасувати</span></a>
            </div>
        </aside>
    </div>
</form>
<?php if ($canDelete): ?>
    <form id="userDeleteForm" method="post" action="<?= url('/admin/users/bulk') ?>" data-no-ajax data-delete-confirm="Видалити цього користувача?" data-after-success-url="<?= url('/admin/users') ?>">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="bulk_action" value="delete">
        <input type="hidden" name="ids[]" value="<?= e((string) $item['id']) ?>">
    </form>
<?php endif; ?>
<script src="<?= url('/assets/admin-users.js') ?>"></script>
