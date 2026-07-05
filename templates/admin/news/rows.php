<?php foreach ($items as $item): ?>
    <tr data-list-row>
        <td><strong><?= e($item['title']) ?></strong><br><span class="meta"><?= e(excerpt($item['body'] ?? '', 100)) ?></span></td>
        <td><span class="status <?= ($item['status'] ?? '') === 'published' ? 'ok' : 'warn' ?>"><?= e($item['status']) ?></span></td>
        <td><?= e($item['published_at'] ?? '') ?></td>
        <td><?= e($item['updated_at'] ?? '') ?></td>
        <td><a class="button secondary compact" href="<?= url('/admin/news/edit?id=' . $item['id']) ?>">Редагувати</a></td>
    </tr>
<?php endforeach; ?>
