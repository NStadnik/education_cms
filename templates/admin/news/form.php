<?php
    $isEdit = !empty($item['id']);
    $body = (string) ($item['body'] ?? '');
    $currentCategory = (string) ($item['category'] ?? 'Загальні');
    $selectedCategoryIds = $selectedCategoryIds ?? [];
    $selectedCategoryTitles = [];
    foreach (($categories ?? []) as $category) {
        if (in_array((int) ($category['id'] ?? 0), $selectedCategoryIds, true)) {
            $selectedCategoryTitles[] = (string) ($category['label'] ?? $category['category']);
        }
    }
    if (!$selectedCategoryTitles) {
        $selectedCategoryTitles[] = $currentCategory;
    }
    $categorySummary = implode(', ', $selectedCategoryTitles);
    $currentStatus = (string) ($item['status'] ?? 'draft');
    $statusLabel = $currentStatus === 'published' ? 'Опубліковано' : 'Чернетка';
    preg_match_all('/[\p{L}\p{N}]+/u', strip_tags($body), $wordMatches);
    $words = count($wordMatches[0] ?? []);
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Оголошення</p>
        <h1><?= $isEdit ? 'Редагувати новину' : 'Нова новина' ?></h1>
        <p class="page-subtitle">Підготуйте заголовок, текст і параметри публікації новини.</p>
    </div>
    <div class="form-actions">
        <a class="button secondary" href="<?= url('/admin/news') ?>"><span class="mdi mdi-arrow-left" aria-hidden="true"></span><span>До списку</span></a>
        <?php if (!empty($item['slug']) && ($item['status'] ?? '') === 'published'): ?>
            <a class="button secondary" href="<?= url('/news/' . $item['slug']) ?>"><span class="mdi mdi-eye-outline" aria-hidden="true"></span><span>Переглянути</span></a>
        <?php endif; ?>
    </div>
</div>

<div class="metrics">
    <div class="metric"><div><span>Статус</span><strong><?= e($statusLabel) ?></strong></div><span class="mdi mdi-circle-edit-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Категорії</span><strong><?= e($categorySummary) ?></strong></div><span class="mdi mdi-shape-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Слів</span><strong><?= e((string) $words) ?></strong></div><span class="mdi mdi-format-text metric-icon" aria-hidden="true"></span></div>
</div>

<form method="post" action="<?= url('/admin/news/save') ?>">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= e((string) ($item['id'] ?? '')) ?>">

    <div class="editor-layout">
        <section class="card admin-form-card">
            <div class="form-section-head">
                <div>
                    <h2>Матеріал</h2>
                    <p class="meta">Основний текст новини буде показаний у списку та на окремій сторінці.</p>
                </div>
            </div>

            <div class="form-grid wide">
                <label>Назва<input name="title" value="<?= e($item['title'] ?? '') ?>" required></label>
                <label>Текст<textarea class="textarea-large" name="body" data-rich-editor required><?= e($item['body'] ?? '') ?></textarea></label>
            </div>
        </section>

        <aside class="card admin-form-card editor-sidebar">
            <div class="sidebar-section">
                <div class="form-section-head">
                    <div>
                        <h2>Публікація</h2>
                        <p class="meta">Дата заповниться автоматично для опублікованої новини.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <label>Статус
                        <select name="status">
                            <option value="draft" <?= selected($currentStatus, 'draft') ?>>Чернетка</option>
                            <option value="published" <?= selected($currentStatus, 'published') ?>>Опубліковано</option>
                        </select>
                    </label>
                    <div class="form-field">
                        <div class="field-label">Категорії</div>
                        <div class="category-picker" data-category-picker>
                            <div class="category-picker-head">
                                <div class="category-picker-summary">
                                    <strong><span data-category-count><?= e((string) count($selectedCategoryTitles)) ?></span> вибрано</strong>
                                    <small data-category-summary><?= e($categorySummary) ?></small>
                                </div>
                                <a class="button secondary icon-button" href="<?= url('/admin/news/categories') ?>" title="Редагувати категорії" aria-label="Редагувати категорії"><span class="mdi mdi-shape-outline" aria-hidden="true"></span></a>
                            </div>
                            <label class="category-picker-search">
                                <span class="mdi mdi-magnify" aria-hidden="true"></span>
                                <input type="search" data-category-filter placeholder="Шукати категорію">
                            </label>
                            <div class="category-picker-list" data-category-list>
                                <?php foreach (($categories ?? []) as $category): ?>
                                    <?php
                                        $categoryTitle = (string) ($category['label'] ?? $category['category']);
                                        $isSelectedCategory = in_array((int) ($category['id'] ?? 0), $selectedCategoryIds, true) || (!$selectedCategoryIds && ($category['category'] ?? '') === $currentCategory);
                                    ?>
                                    <label class="category-option" data-category-item data-category-title="<?= e($categoryTitle) ?>">
                                        <input type="checkbox" name="category_ids[]" value="<?= e((string) $category['id']) ?>" <?= checked($isSelectedCategory) ?>>
                                        <span class="category-option-box"><span class="mdi mdi-check" aria-hidden="true"></span></span>
                                        <span class="category-option-title"><?= e($categoryTitle) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="category-picker-empty meta" data-category-empty hidden>Категорій не знайдено.</div>
                        </div>
                    </div>
                    <label>Дата публікації<input name="published_at" value="<?= e($item['published_at'] ?? '') ?>" placeholder="2026-07-05"></label>
                </div>
            </div>

            <div class="sidebar-section">
                <div class="form-section-head">
                    <div>
                        <h2>SEO</h2>
                        <p class="meta">Адреса формується автоматично, але її можна змінити.</p>
                    </div>
                </div>
                <div class="form-grid">
                    <label>Slug<input name="slug" value="<?= e($item['slug'] ?? '') ?>" placeholder="nazva-novyny"></label>
                </div>
            </div>

            <div class="form-actions stacked">
                <button type="submit"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span>Зберегти новину</span></button>
                <?php if ($isEdit): ?>
                    <button class="button danger" type="submit" form="newsDeleteForm"><span class="mdi mdi-delete-outline" aria-hidden="true"></span><span>Видалити</span></button>
                <?php endif; ?>
                <a class="button secondary" href="<?= url('/admin/news') ?>"><span class="mdi mdi-close" aria-hidden="true"></span><span>Скасувати</span></a>
            </div>
        </aside>
    </div>
</form>
<?php if ($isEdit): ?>
    <form id="newsDeleteForm" method="post" action="<?= url('/admin/news/bulk') ?>" data-no-ajax data-delete-confirm="Видалити цю новину?" data-after-success-url="<?= url('/admin/news') ?>">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="bulk_action" value="delete">
        <input type="hidden" name="ids[]" value="<?= e((string) $item['id']) ?>">
    </form>
<?php endif; ?>
