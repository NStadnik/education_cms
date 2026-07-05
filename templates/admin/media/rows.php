<?php foreach ($items as $item): ?>
    <tr data-list-row>
        <td>
            <div class="media-file">
                <?php if (!empty($item['is_image'])): ?>
                    <img class="media-thumb" src="<?= url('/uploads/' . $item['path']) ?>" alt="">
                <?php else: ?>
                    <span class="media-thumb media-thumb-icon mdi mdi-file-outline" aria-hidden="true"></span>
                <?php endif; ?>
                <div>
                    <strong><?= e($item['name']) ?></strong><br>
                    <code><?= e($item['path']) ?></code>
                </div>
            </div>
        </td>
        <td><?= e($item['type']) ?></td>
        <td><?= e($item['size_label']) ?></td>
        <td><?= e($item['modified_at']) ?></td>
        <td>
            <?php if (!empty($item['is_used'])): ?>
                <span class="status ok">використовується</span><br>
                <a class="meta" href="<?= e($item['reference']['url']) ?>"><?= e($item['reference']['label']) ?></a>
            <?php else: ?>
                <span class="status warn">вільний</span>
            <?php endif; ?>
        </td>
        <td>
            <div class="form-actions">
                <a class="button secondary compact" href="<?= url('/uploads/' . $item['path']) ?>">
                    <span class="mdi mdi-open-in-new" aria-hidden="true"></span><span>Відкрити</span>
                </a>
                <?php if (empty($item['is_used'])): ?>
                    <form method="post" action="<?= url('/admin/media/delete') ?>" data-no-ajax onsubmit="return confirm('Видалити цей файл?');">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="path" value="<?= e($item['path']) ?>">
                        <button class="button danger compact" type="submit"><span class="mdi mdi-trash-can-outline" aria-hidden="true"></span><span>Видалити</span></button>
                    </form>
                <?php endif; ?>
            </div>
        </td>
    </tr>
<?php endforeach; ?>
