<?php foreach ($items as $item): ?>
    <tr data-list-row>
        <td>
            <?php if (empty($item['is_used'])): ?>
                <input type="checkbox" name="paths[]" value="<?= e($item['path']) ?>" data-bulk-check form="mediaBulkForm" aria-label="Вибрати">
            <?php endif; ?>
        </td>
        <td>
            <button class="media-file media-file-preview" type="button" data-media-preview title="Переглянути <?= e($item['name']) ?>" aria-label="Переглянути файл <?= e($item['name']) ?>" data-url="<?= url('/uploads/' . $item['path']) ?>" data-name="<?= e($item['name']) ?>" data-path="<?= e($item['path']) ?>" data-type="<?= e($item['type']) ?>" data-size="<?= e($item['size_label']) ?>" data-modified="<?= e($item['modified_at']) ?>" data-extension="<?= e($item['extension']) ?>" data-is-image="<?= !empty($item['is_image']) ? '1' : '0' ?>" data-is-used="<?= !empty($item['is_used']) ? '1' : '0' ?>" data-reference-label="<?= e((string) ($item['reference']['label'] ?? '')) ?>" data-reference-url="<?= e((string) ($item['reference']['url'] ?? '')) ?>" data-folder="<?= e((string) ($item['folder'] ?? '')) ?>" data-alt-text="<?= e((string) ($item['alt_text'] ?? '')) ?>" data-title="<?= e((string) ($item['title'] ?? '')) ?>" data-caption="<?= e((string) ($item['caption'] ?? '')) ?>" data-description="<?= e((string) ($item['description'] ?? '')) ?>">
                <?php if (!empty($item['is_image'])): ?>
                    <img class="media-thumb" src="<?= url('/thumb/' . $item['path'] . '?w=360&h=240&fit=crop') ?>" alt="" loading="lazy">
                <?php else: ?>
                    <span class="media-thumb media-thumb-icon mdi mdi-file-outline" aria-hidden="true"></span>
                <?php endif; ?>
                <span class="media-file-copy">
                    <strong><?= e($item['name']) ?></strong><br>
                    <code><?= e($item['path']) ?></code>
                    <?php if ((string) ($item['title'] ?? '') !== ''): ?>
                        <small class="media-file-title"><?= e((string) $item['title']) ?></small>
                    <?php endif; ?>
                </span>
            </button>
        </td>
        <td>
            <?php if ((string) ($item['folder'] ?? '') !== ''): ?>
                <span class="media-folder-pill"><span class="mdi mdi-folder-outline" aria-hidden="true"></span><?= e((string) $item['folder']) ?></span>
            <?php else: ?>
                <span class="meta">Без папки</span>
            <?php endif; ?>
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
                <button class="button secondary compact media-row-action" type="button" data-media-metadata title="Метадані" aria-label="Редагувати метадані файлу <?= e($item['name']) ?>" data-url="<?= url('/uploads/' . $item['path']) ?>" data-name="<?= e($item['name']) ?>" data-path="<?= e($item['path']) ?>" data-extension="<?= e($item['extension']) ?>" data-is-image="<?= !empty($item['is_image']) ? '1' : '0' ?>" data-folder="<?= e((string) ($item['folder'] ?? '')) ?>" data-alt-text="<?= e((string) ($item['alt_text'] ?? '')) ?>" data-title="<?= e((string) ($item['title'] ?? '')) ?>" data-caption="<?= e((string) ($item['caption'] ?? '')) ?>" data-description="<?= e((string) ($item['description'] ?? '')) ?>">
                    <span class="mdi mdi-pencil-outline" aria-hidden="true"></span><span class="media-action-label">Метадані</span>
                </button>
                <button class="button secondary compact media-row-action" type="button" data-media-preview title="Переглянути" aria-label="Переглянути файл <?= e($item['name']) ?>" data-url="<?= url('/uploads/' . $item['path']) ?>" data-name="<?= e($item['name']) ?>" data-path="<?= e($item['path']) ?>" data-type="<?= e($item['type']) ?>" data-size="<?= e($item['size_label']) ?>" data-modified="<?= e($item['modified_at']) ?>" data-extension="<?= e($item['extension']) ?>" data-is-image="<?= !empty($item['is_image']) ? '1' : '0' ?>" data-is-used="<?= !empty($item['is_used']) ? '1' : '0' ?>" data-reference-label="<?= e((string) ($item['reference']['label'] ?? '')) ?>" data-reference-url="<?= e((string) ($item['reference']['url'] ?? '')) ?>" data-folder="<?= e((string) ($item['folder'] ?? '')) ?>" data-alt-text="<?= e((string) ($item['alt_text'] ?? '')) ?>" data-title="<?= e((string) ($item['title'] ?? '')) ?>" data-caption="<?= e((string) ($item['caption'] ?? '')) ?>" data-description="<?= e((string) ($item['description'] ?? '')) ?>">
                    <span class="mdi mdi-eye-outline" aria-hidden="true"></span><span class="media-action-label">Переглянути</span>
                </button>
                <?php if (empty($item['is_used'])): ?>
                    <form method="post" action="<?= url('/admin/media/delete') ?>" data-media-delete>
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="path" value="<?= e($item['path']) ?>">
                        <button class="button danger compact media-row-action" type="submit" title="Видалити" aria-label="Видалити файл <?= e($item['name']) ?>"><span class="mdi mdi-trash-can-outline" aria-hidden="true"></span><span class="media-action-label">Видалити</span></button>
                    </form>
                <?php endif; ?>
            </div>
        </td>
    </tr>
<?php endforeach; ?>
