<?php
    $roles = is_array($roles ?? null) ? $roles : [];
    $roleUsage = is_array($roleUsage ?? null) ? $roleUsage : [];
    $permissionCatalog = is_array($permissionCatalog ?? null) ? $permissionCatalog : [];
?>
<?php foreach ($roles as $slug => $role): ?>
    <?php
        $permissions = is_array($role['permissions'] ?? null) ? $role['permissions'] : [];
        $permissionCount = in_array('*', $permissions, true) ? count($permissionCatalog) : count($permissions);
        $usersCount = (int) ($roleUsage[$slug] ?? 0);
    ?>
    <tr data-filter-row data-filter-text="<?= e((string) ($role['label'] ?? $slug) . ' ' . $slug) ?>">
        <td><strong><?= e((string) ($role['label'] ?? $slug)) ?></strong></td>
        <td><code><?= e((string) $slug) ?></code></td>
        <td><?= e((string) $permissionCount) ?> / <?= e((string) count($permissionCatalog)) ?></td>
        <td><?= e((string) $usersCount) ?></td>
        <td>
            <div class="role-row-actions">
                <a class="button secondary compact" href="<?= url('/admin/users/roles/edit?role=' . rawurlencode((string) $slug)) ?>">
                    <span class="mdi mdi-pencil-outline" aria-hidden="true"></span>
                    <span>Редагувати</span>
                </a>
                <?php if ($usersCount === 0): ?>
                    <form method="post" action="<?= url('/admin/users/roles/delete') ?>" data-no-ajax data-role-delete-form data-delete-confirm="Видалити цю роль?">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="role" value="<?= e((string) $slug) ?>">
                        <button class="button danger compact" type="submit">
                            <span class="mdi mdi-delete-outline" aria-hidden="true"></span>
                            <span>Видалити</span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </td>
    </tr>
<?php endforeach; ?>
