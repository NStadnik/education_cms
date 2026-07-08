<?php
    $role = (string) ($item['role'] ?? 'viewer');
    $roleLabels = is_array($roleLabels ?? null) ? $roleLabels : [];
    $rolePermissions = is_array($rolePermissions ?? null) ? $rolePermissions : [];
    $permissionCatalog = is_array($permissionCatalog ?? null) ? $permissionCatalog : [];
    $allowed = $role === 'super_admin' ? ['*'] : ($rolePermissions[$role] ?? []);
    $canEverything = $role === 'super_admin' || in_array('*', $allowed, true);
    $roleLabel = $roleLabels[$role] ?? ($role === 'super_admin' ? 'Супер адміністратор' : $role);
    $grantedPermissions = [];
    foreach ($permissionCatalog as $permission => $permissionInfo) {
        if ($canEverything || in_array($permission, $allowed, true)) {
            $grantedPermissions[$permission] = $permissionInfo;
        }
    }
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Акаунт</p>
        <h1>Профіль користувача</h1>
        <p class="page-subtitle">Оновіть особисті дані для входу та змініть пароль, коли це потрібно.</p>
    </div>
    <span class="profile-role-pill"><?= e($roleLabel) ?></span>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>

<form method="post" action="<?= url('/admin/profile/save') ?>">
    <?= \App\Core\Csrf::field() ?>

    <div class="editor-layout profile-layout">
        <section class="card admin-form-card">
            <div class="form-section-head">
                <div>
                    <h2>Дані профілю</h2>
                    <p class="meta">Ці дані показуються в адмінці та використовуються для входу.</p>
                </div>
            </div>
            <div class="form-grid wide">
                <label>Ім'я<input name="name" value="<?= e($item['name'] ?? '') ?>" required autocomplete="name"></label>
                <label>Email<input type="email" name="email" value="<?= e($item['email'] ?? '') ?>" required autocomplete="email"></label>
            </div>

            <div class="profile-security-panel">
                <div class="form-section-head">
                    <div>
                        <h2>Безпека</h2>
                        <p class="meta">Щоб змінити пароль, введіть поточний пароль і новий пароль двічі.</p>
                    </div>
                </div>
                <div class="form-grid wide">
                    <label>Поточний пароль<input type="password" name="current_password" autocomplete="current-password"></label>
                    <label>Новий пароль<input type="password" name="new_password" autocomplete="new-password"></label>
                    <label>Підтвердження нового пароля<input type="password" name="password_confirmation" autocomplete="new-password"></label>
                </div>
            </div>
        </section>

        <aside class="card admin-form-card editor-sidebar profile-sidebar">
            <div class="profile-avatar" aria-hidden="true">
                <?= e(function_exists('mb_substr') ? mb_substr((string) ($item['name'] ?? 'A'), 0, 1) : substr((string) ($item['name'] ?? 'A'), 0, 1)) ?>
            </div>
            <div class="profile-summary">
                <strong data-profile-summary-name><?= e($item['name'] ?? 'Користувач') ?></strong>
                <span data-profile-summary-email><?= e($item['email'] ?? '') ?></span>
            </div>
            <div class="profile-role-box">
                <span class="mdi mdi-shield-account-outline" aria-hidden="true"></span>
                <div>
                    <small>Роль</small>
                    <strong><?= e($roleLabel) ?></strong>
                </div>
            </div>
            <div class="profile-permissions-list">
                <strong>Доступні розділи</strong>
                <?php if ($grantedPermissions): ?>
                    <?php foreach ($grantedPermissions as $permission => $permissionInfo): ?>
                        <span><span class="mdi mdi-check-circle-outline" aria-hidden="true"></span><?= e((string) ($permissionInfo['label'] ?? $permission)) ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="meta mb-0">Для цієї ролі немає окремих прав доступу.</p>
                <?php endif; ?>
            </div>
            <div class="form-actions stacked">
                <button type="submit"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span>Зберегти профіль</span></button>
            </div>
        </aside>
    </div>
</form>
