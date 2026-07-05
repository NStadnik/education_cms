<?php foreach ($items as $item): ?>
    <tr data-list-row>
        <td><strong><?= e($item['title']) ?></strong><br><span class="meta"><?= e($item['description'] ?? '') ?></span></td>
        <td><?= e($item['category']) ?></td>
        <td><span class="status <?= $item['public_info_section_id'] ? 'ok' : '' ?>"><?= $item['public_info_section_id'] ? 'так' : 'ні' ?></span></td>
        <td><span class="status <?= ($item['status'] ?? '') === 'published' ? 'ok' : 'warn' ?>"><?= e($item['status']) ?></span></td>
        <td><?= e($item['approved_at'] ?? $item['published_at'] ?? $item['created_at'] ?? '') ?></td>
        <td><?php if ($item['file_path']): ?><a href="<?= url('/uploads/' . $item['file_path']) ?>">відкрити</a><?php else: ?><span class="meta">немає</span><?php endif; ?></td>
    </tr>
<?php endforeach; ?>
