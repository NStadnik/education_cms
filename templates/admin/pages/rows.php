<?php
$statusLabels = [
    'published' => 'Опубліковано',
    'draft' => 'Чернетка',
];
?>
<?php foreach ($items as $item): ?>
    <tr data-list-row>
        <td><input type="checkbox" name="ids[]" value="<?= e((string) $item['id']) ?>" data-bulk-check aria-label="Вибрати"></td>
        <td><strong><?= e($item['title']) ?></strong><br><span class="meta"><?= e($item['excerpt'] ?? '') ?></span></td>
        <td><?= e($item['template'] ?? 'default') ?></td>
        <td><span class="status <?= ($item['status'] ?? '') === 'published' ? 'ok' : 'warn' ?>"><?= e($statusLabels[$item['status'] ?? ''] ?? ($item['status'] ?? '')) ?></span></td>
        <td><?= e((string) ($item['sort_order'] ?? 0)) ?></td>
        <td><a class="button secondary compact" href="<?= url('/admin/pages/edit?id=' . $item['id']) ?>"><span class="mdi mdi-pencil-outline" aria-hidden="true"></span><span>Редагувати</span></a></td>
    </tr>
<?php endforeach; ?>
