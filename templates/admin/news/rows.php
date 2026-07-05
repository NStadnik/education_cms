<?php foreach ($items as $item): ?>
    <tr data-list-row>
        <td><input type="checkbox" name="ids[]" value="<?= e((string) $item['id']) ?>" data-bulk-check aria-label="Вибрати"></td>
        <td><strong><?= e($item['title']) ?></strong><br><span class="meta"><?= e(excerpt($item['body'] ?? '', 100)) ?></span></td>
        <td><span class="status <?= ($item['status'] ?? '') === 'published' ? 'ok' : 'warn' ?>"><?= e($item['status']) ?></span></td>
        <td><?= e($item['published_at'] ?? '') ?></td>
        <td><?= e($item['updated_at'] ?? '') ?></td>
        <td><a class="button secondary compact" href="<?= url('/admin/news/edit?id=' . $item['id']) ?>"><span class="mdi mdi-pencil-outline" aria-hidden="true"></span><span>Редагувати</span></a></td>
    </tr>
<?php endforeach; ?>
