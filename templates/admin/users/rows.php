<?php foreach ($items as $item): ?>
    <tr data-list-row>
        <td><?= e($item['name']) ?></td>
        <td><?= e($item['email']) ?></td>
        <td><?= e($item['role']) ?></td>
        <td><?= $item['is_active'] ? 'так' : 'ні' ?></td>
    </tr>
<?php endforeach; ?>
