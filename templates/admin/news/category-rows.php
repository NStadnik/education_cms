<?php foreach ($categories as $category): ?>
    <tr>
        <td>
            <form id="categoryForm<?= e((string) $category['id']) ?>" method="post" action="<?= url('/admin/news/categories/save') ?>" data-replace-target="#newsCategoryRows" data-options-target="#newCategoryParent">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="id" value="<?= e((string) $category['id']) ?>">
                <input name="title" value="<?= e($category['title']) ?>" style="padding-left: <?= e((string) (11 + ((int) ($category['depth'] ?? 0) * 18))) ?>px" required aria-label="Назва категорії">
            </form>
        </td>
        <td>
            <select form="categoryForm<?= e((string) $category['id']) ?>" name="parent_id" aria-label="Батьківська категорія">
                <option value="">Без батьківської</option>
                <?php foreach (($parentOptions ?? []) as $option): ?>
                    <?php if ((int) $option['id'] === (int) $category['id']) { continue; } ?>
                    <option value="<?= e((string) $option['id']) ?>" <?= selected($category['parent_id'] ?? '', $option['id']) ?>><?= e($option['label'] ?? $option['category']) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input form="categoryForm<?= e((string) $category['id']) ?>" type="number" name="sort_order" value="<?= e((string) $category['sort_order']) ?>" aria-label="Порядок"></td>
        <td><span class="status"><?= e((string) $category['news_count']) ?></span></td>
        <td>
            <div class="form-actions">
                <button class="button secondary compact" type="submit" form="categoryForm<?= e((string) $category['id']) ?>"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span>Зберегти</span></button>
                <?php if ((int) $category['news_count'] === 0): ?>
                    <form method="post" action="<?= url('/admin/news/categories/delete') ?>" data-replace-target="#newsCategoryRows" data-options-target="#newCategoryParent" onsubmit="return confirm('Видалити цю категорію?')">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= e((string) $category['id']) ?>">
                        <button class="button danger compact" type="submit"><span class="mdi mdi-delete-outline" aria-hidden="true"></span><span>Видалити</span></button>
                    </form>
                <?php endif; ?>
            </div>
        </td>
    </tr>
<?php endforeach; ?>
<?php if (!$categories): ?><tr><td colspan="5" class="empty-state">Категорії ще не додані.</td></tr><?php endif; ?>
