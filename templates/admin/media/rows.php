<?php foreach ($items as $item): ?>
    <tr data-list-row>
        <td>
            <?php if (empty($item['is_used'])): ?>
                <input type="checkbox" name="paths[]" value="<?= e($item['path']) ?>" data-bulk-check form="mediaBulkForm" aria-label="Вибрати">
            <?php endif; ?>
        </td>
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
                <button class="button secondary compact" type="button" data-media-preview data-url="<?= url('/uploads/' . $item['path']) ?>" data-name="<?= e($item['name']) ?>" data-path="<?= e($item['path']) ?>" data-type="<?= e($item['type']) ?>" data-extension="<?= e($item['extension']) ?>" data-is-image="<?= !empty($item['is_image']) ? '1' : '0' ?>">
                    <span class="mdi mdi-eye-outline" aria-hidden="true"></span><span>Переглянути</span>
                </button>
                <?php if (empty($item['is_used'])): ?>
                    <form method="post" action="<?= url('/admin/media/delete') ?>" data-media-delete>
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="path" value="<?= e($item['path']) ?>">
                        <button class="button danger compact" type="submit"><span class="mdi mdi-trash-can-outline" aria-hidden="true"></span><span>Видалити</span></button>
                    </form>
                <?php endif; ?>
            </div>
        </td>
    </tr>
<?php endforeach; ?>
