<?php
    $roles = is_array($roles ?? null) ? $roles : [];
    $roleUsage = is_array($roleUsage ?? null) ? $roleUsage : [];
    $permissionCatalog = is_array($permissionCatalog ?? null) ? $permissionCatalog : [];
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Доступ</p>
        <h1>Ролі користувачів</h1>
        <p class="page-subtitle">Окремий перелік ролей із переходом до редагування повноважень.</p>
    </div>
    <div class="form-actions">
        <a class="button secondary" href="<?= url('/admin/users') ?>"><span class="mdi mdi-arrow-left" aria-hidden="true"></span><span>До користувачів</span></a>
        <a class="button" href="<?= url('/admin/users/roles/edit') ?>"><span class="mdi mdi-plus" aria-hidden="true"></span><span>Додати роль</span></a>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>
<div class="alert d-none" data-role-ajax-message></div>

<section class="card admin-form-card">
    <div class="form-section-head">
        <div>
            <h2>Системна роль</h2>
            <p class="meta">Ця роль завжди має повний доступ і не редагується.</p>
        </div>
    </div>
    <div class="role-system-row">
        <span class="mdi mdi-shield-crown-outline" aria-hidden="true"></span>
        <div>
            <strong><?= e($systemRoleLabel ?? 'Супер адміністратор') ?></strong>
            <small>super_admin · <?= e((string) ($roleUsage['super_admin'] ?? 0)) ?> користувачів · всі повноваження</small>
        </div>
    </div>
</section>

<section class="list-panel" data-filter-list>
    <div class="list-tools">
        <div>
            <strong>Перелік ролей</strong>
            <p class="meta mb-0"><span data-role-total><?= e((string) count($roles)) ?></span> ролей</p>
        </div>
        <input data-filter-input type="search" placeholder="Пошук ролей" aria-label="Пошук ролей">
    </div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Назва</th>
                    <th>Код</th>
                    <th>Повноваження</th>
                    <th>Користувачі</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="roleRows" data-role-rows>
                <?= $this->partial('admin/users/role-rows', ['roles' => $roles, 'roleUsage' => $roleUsage, 'permissionCatalog' => $permissionCatalog]) ?>
            </tbody>
        </table>
    </div>
    <div class="empty-state <?= $roles ? 'd-none' : '' ?>" data-role-empty>Ролі не знайдені.</div>
</section>
<script src="<?= url('/assets/admin-users.js') ?>"></script>
