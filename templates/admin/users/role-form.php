<?php
    $isEdit = (bool) ($isEdit ?? false);
    $isUsed = (bool) ($isUsed ?? false);
    $slug = (string) ($slug ?? '');
    $item = is_array($item ?? null) ? $item : [];
    $permissionCatalog = is_array($permissionCatalog ?? null) ? $permissionCatalog : [];
    $permissions = is_array($item['permissions'] ?? null) ? $item['permissions'] : [];
    $permissionGroups = [];
    foreach ($permissionCatalog as $permission => $permissionInfo) {
        $permissionGroups[(string) ($permissionInfo['group'] ?? 'Інше')][$permission] = $permissionInfo;
    }
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Доступ</p>
        <h1 data-role-form-title><?= $isEdit ? 'Редагувати роль' : 'Нова роль' ?></h1>
        <p class="page-subtitle">Налаштуйте назву ролі та доступні розділи адмінпанелі.</p>
    </div>
    <a class="button secondary" href="<?= url('/admin/users/roles') ?>"><span class="mdi mdi-arrow-left" aria-hidden="true"></span><span>До ролей</span></a>
</div>

<form method="post" action="<?= url('/admin/users/roles/save') ?>" data-role-form>
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="old_slug" value="<?= e($slug) ?>">

    <div class="editor-layout role-form-layout">
        <section class="card admin-form-card">
            <div class="form-section-head">
                <div>
                    <h2>Дані ролі</h2>
                    <p class="meta">Код ролі використовується в облікових записах.</p>
                </div>
            </div>
            <div class="form-grid wide">
                <label>Назва
                    <input name="label" value="<?= e((string) ($item['label'] ?? '')) ?>" required data-role-label-input>
                </label>
                <label>Код
                    <input name="slug" value="<?= e($slug) ?>" pattern="[a-z0-9_]+" <?= $isUsed ? 'readonly' : '' ?> required data-role-slug-input>
                </label>
                <?php if ($isUsed): ?>
                    <div class="hint-box">Цю роль уже призначено користувачам, тому її код заблоковано.</div>
                <?php endif; ?>
            </div>

            <div class="role-permission-editor">
                <?php foreach ($permissionGroups as $group => $permissionsInGroup): ?>
                    <section class="role-permission-group">
                        <strong><?= e($group) ?></strong>
                        <?php foreach ($permissionsInGroup as $permission => $permissionInfo): ?>
                            <?php $checked = in_array('*', $permissions, true) || in_array($permission, $permissions, true); ?>
                            <label class="role-permission-option">
                                <input type="checkbox" name="permissions[]" value="<?= e($permission) ?>" <?= checked($checked) ?>>
                                <span class="mdi mdi-check" aria-hidden="true"></span>
                                <span>
                                    <b><?= e((string) ($permissionInfo['label'] ?? $permission)) ?></b>
                                    <small><?= e((string) ($permissionInfo['description'] ?? $permission)) ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>
            </div>
        </section>

        <aside class="card admin-form-card editor-sidebar">
            <div class="form-section-head">
                <div>
                    <h2>Збереження</h2>
                    <p class="meta">Зміни повноважень застосуються одразу після збереження.</p>
                </div>
            </div>
            <div class="form-actions stacked">
                <button type="submit"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span>Зберегти роль</span></button>
                <a class="button secondary" href="<?= url('/admin/users/roles') ?>"><span class="mdi mdi-close" aria-hidden="true"></span><span>Скасувати</span></a>
            </div>
        </aside>
    </div>
</form>
<script src="<?= url('/assets/admin-users.js') ?>"></script>
