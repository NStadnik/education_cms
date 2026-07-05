<?php foreach ($items as $item): ?>
    <tr data-list-row>
        <td><strong><?= e($item['title']) ?></strong><br><span class="meta"><?= e($item['excerpt'] ?? '') ?></span></td>
        <td><code><?= e($item['slug']) ?></code></td>
        <td><span class="status <?= ($item['status'] ?? '') === 'published' ? 'ok' : 'warn' ?>"><?= e($item['status']) ?></span></td>
        <td><?= e((string) ($item['sort_order'] ?? 0)) ?></td>
        <td><a class="button secondary compact" href="<?= url('/admin/pages/edit?id=' . $item['id']) ?>">Редагувати</a></td>
    </tr>
<?php endforeach; ?>
