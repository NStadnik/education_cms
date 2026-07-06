<?php foreach ($categories as $category): ?>
    <?php
        $categoryDepth = max(0, min(8, (int) ($category['depth'] ?? 0)));
        $newsCount = (int) ($category['news_count'] ?? 0);
        $childrenCount = (int) ($category['children_count'] ?? 0);
        $canDeleteCategory = $newsCount === 0 && $childrenCount === 0;
        $searchText = trim(implode(' ', [
            (string) ($category['id'] ?? ''),
            (string) ($category['title'] ?? ''),
            (string) ($category['slug'] ?? ''),
            (string) ($category['label'] ?? ''),
        ]));
        $searchText = function_exists('mb_strtolower') ? mb_strtolower($searchText) : strtolower($searchText);
    ?>
    <tr
        data-news-category-row
        data-category-news-count="<?= e((string) $newsCount) ?>"
        data-category-children-count="<?= e((string) $childrenCount) ?>"
        data-category-search="<?= e($searchText) ?>"
    >
        <td>
            <form id="categoryForm<?= e((string) $category['id']) ?>" method="post" action="<?= url('/admin/news/categories/save') ?>" data-replace-target="#newsCategoryRows" data-options-target="#newCategoryParent">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="id" value="<?= e((string) $category['id']) ?>">
                <div class="news-category-name-cell news-category-depth-<?= e((string) $categoryDepth) ?>">
                    <span class="news-category-tree-dot" aria-hidden="true"></span>
                    <label>
                        <span class="visually-hidden">Назва категорії</span>
                        <input class="news-category-title-input" name="title" value="<?= e($category['title']) ?>" required aria-label="Назва категорії">
                    </label>
                    <small><?= e((string) ($category['slug'] ?? '')) ?></small>
                </div>
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
        <td>
            <div class="news-category-usage">
                <span class="status"><?= e((string) $newsCount) ?> новин</span>
                <?php if ($childrenCount > 0): ?>
                    <span class="status"><?= e((string) $childrenCount) ?> підкат.</span>
                <?php endif; ?>
            </div>
        </td>
        <td>
            <div class="form-actions news-category-row-actions">
                <button class="button secondary compact" type="submit" form="categoryForm<?= e((string) $category['id']) ?>"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span>Зберегти</span></button>
                <?php if ($canDeleteCategory): ?>
                    <form method="post" action="<?= url('/admin/news/categories/delete') ?>" data-replace-target="#newsCategoryRows" data-options-target="#newCategoryParent" onsubmit="return confirm('Видалити цю категорію?')">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= e((string) $category['id']) ?>">
                        <button class="button danger compact" type="submit"><span class="mdi mdi-delete-outline" aria-hidden="true"></span><span>Видалити</span></button>
                    </form>
                <?php else: ?>
                    <span class="news-category-locked" title="Категорію не можна видалити, доки вона використовується або має підкатегорії">
                        <span class="mdi mdi-lock-outline" aria-hidden="true"></span><span>Захищено</span>
                    </span>
                <?php endif; ?>
            </div>
        </td>
    </tr>
<?php endforeach; ?>
<?php if (!$categories): ?><tr data-news-category-empty-row><td colspan="5" class="empty-state">Категорії ще не додані.</td></tr><?php endif; ?>
