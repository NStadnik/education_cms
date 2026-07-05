<?php foreach ($items as $item): ?>
    <tr data-list-row>
        <td><input type="checkbox" name="ids[]" value="<?= e((string) $item['id']) ?>" data-bulk-check aria-label="Вибрати"></td>
        <td><strong><?= e($item['title']) ?></strong><br><span class="meta"><?= e($item['description'] ?? '') ?></span></td>
        <td><?= e($item['category']) ?></td>
        <td><span class="status <?= $item['public_info_section_id'] ? 'ok' : '' ?>"><?= $item['public_info_section_id'] ? 'так' : 'ні' ?></span></td>
        <td><span class="status <?= ($item['status'] ?? '') === 'published' ? 'ok' : 'warn' ?>"><?= e($item['status']) ?></span></td>
        <td><?= e($item['approved_at'] ?? $item['published_at'] ?? $item['created_at'] ?? '') ?></td>
        <td><?php if ($item['file_path']): ?><a href="<?= url('/uploads/' . $item['file_path']) ?>"><span class="mdi mdi-open-in-new" aria-hidden="true"></span> відкрити</a><?php else: ?><span class="meta">немає</span><?php endif; ?></td>
        <td><a class="button secondary compact" href="<?= url('/admin/documents/edit?id=' . $item['id']) ?>"><span class="mdi mdi-pencil-outline" aria-hidden="true"></span><span>Редагувати</span></a></td>
    </tr>
<?php endforeach; ?>
