<?php
    $roleLabels = is_array($roleLabels ?? null) ? $roleLabels : \App\Core\Container::get('auth')->roleLabels();
?>
<?php foreach ($items as $item): ?>
    <tr data-list-row>
        <td><input type="checkbox" name="ids[]" value="<?= e((string) $item['id']) ?>" data-bulk-check aria-label="Вибрати"></td>
        <td><?= e($item['name']) ?></td>
        <td><?= e($item['email']) ?></td>
        <td><?= e($roleLabels[$item['role']] ?? ($item['role'] === 'super_admin' ? 'Супер адміністратор' : $item['role'])) ?></td>
        <td><?= $item['is_active'] ? 'так' : 'ні' ?></td>
        <td><a class="button secondary compact" href="<?= url('/admin/users/edit?id=' . $item['id']) ?>"><span class="mdi mdi-pencil-outline" aria-hidden="true"></span><span>Редагувати</span></a></td>
    </tr>
<?php endforeach; ?>
