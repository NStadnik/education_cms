<?php foreach ($items as $item): ?>
    <tr data-list-row>
        <td><?= e($item['name']) ?></td>
        <td><?= e($item['email']) ?></td>
        <td><?= e($item['role']) ?></td>
        <td><?= $item['is_active'] ? 'так' : 'ні' ?></td>
        <td><a class="button secondary compact" href="<?= url('/admin/users/edit?id=' . $item['id']) ?>"><span class="mdi mdi-pencil-outline" aria-hidden="true"></span><span>Редагувати</span></a></td>
    </tr>
<?php endforeach; ?>
