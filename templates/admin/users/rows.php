<?php foreach ($items as $item): ?>
    <tr data-list-row>
        <td><?= e($item['name']) ?></td>
        <td><?= e($item['email']) ?></td>
        <td><?= e($item['role']) ?></td>
        <td><?= $item['is_active'] ? 'так' : 'ні' ?></td>
        <td><a class="button secondary compact" href="<?= url('/admin/users/edit?id=' . $item['id']) ?>">Редагувати</a></td>
    </tr>
<?php endforeach; ?>
