<?php
    $isEdit = !empty($item['id']);
    $blocks = $item ? json_decode($item['blocks_json'], true) : [];
    $layoutBlocks = array_values(array_filter($blocks ?: [], static fn ($block): bool => is_array($block) && ($block['type'] ?? '') === 'layout'));
    $hasLayoutBlocks = !empty($layoutBlocks);
    $blockCount = count($layoutBlocks ?: ($blocks ?: []));
    $statusLabel = (($item['status'] ?? 'draft') === 'published') ? 'Опубліковано' : 'Чернетка';
    $simpleTextParts = [];
    foreach (($blocks ?: []) as $block) {
        if (!is_array($block)) {
            continue;
        }
        if (($block['type'] ?? '') === 'layout') {
            foreach (($block['rows'] ?? []) as $row) {
                foreach (($row['columns'] ?? []) as $column) {
                    foreach (($column['cards'] ?? []) as $card) {
                        $cardTitle = trim((string) ($card['title'] ?? ''));
                        $cardText = trim((string) ($card['text'] ?? ''));
                        if ($cardTitle !== '' || $cardText !== '') {
                            $simpleTextParts[] = trim(($cardTitle !== '' ? $cardTitle . "\n" : '') . $cardText);
                        }
                    }
                }
            }
            continue;
        }
        $simpleTextParts[] = trim((string) ($block['text'] ?? ''));
    }
    $simpleText = trim(implode("\n\n", array_filter($simpleTextParts)));
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Контент сайту</p>
        <h1><?= $isEdit ? 'Редагувати сторінку' : 'Нова сторінка' ?></h1>
        <p class="page-subtitle">Заповніть назву, опис і текст сторінки. Розширений конструктор доступний окремо.</p>
    </div>
    <div class="form-actions">
        <a class="button secondary" href="<?= url('/admin/pages') ?>"><span class="mdi mdi-arrow-left" aria-hidden="true"></span><span>До списку</span></a>
        <?php if (!empty($item['slug']) && ($item['status'] ?? '') === 'published'): ?>
            <a class="button secondary" href="<?= url($item['slug'] === 'home' ? '/' : '/page/' . $item['slug']) ?>"><span class="mdi mdi-eye-outline" aria-hidden="true"></span><span>Переглянути</span></a>
        <?php endif; ?>
    </div>
</div>

<div class="metrics page-edit-metrics">
    <div class="metric"><div><span>Статус</span><strong><?= e($statusLabel) ?></strong></div><span class="mdi mdi-circle-edit-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Секцій</span><strong data-page-block-count><?= e((string) $blockCount) ?></strong></div><span class="mdi mdi-view-grid-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Порядок</span><strong><?= e((string) ($item['sort_order'] ?? 0)) ?></strong></div><span class="mdi mdi-sort-numeric-ascending metric-icon" aria-hidden="true"></span></div>
</div>

<form class="page-editor-form" method="post" action="<?= url('/admin/pages/save') ?>">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= e((string) ($item['id'] ?? '')) ?>">

    <div class="editor-layout">
        <section class="card admin-form-card page-editor-main">
            <div class="form-section-head">
                <div>
                    <p class="eyebrow">Сторінка</p>
                    <h2>Основний вміст</h2>
                    <p class="meta">Ці дані відображаються на публічній сторінці та в меню.</p>
                </div>
            </div>

            <div class="form-grid wide">
                <div class="page-title-grid">
                    <label>Назва<input name="title" value="<?= e($item['title'] ?? '') ?>" required placeholder="Назва сторінки"></label>
                    <label>Короткий опис<textarea class="textarea-small" name="excerpt" placeholder="Опис для першого екрану або списків сторінок"><?= e($item['excerpt'] ?? '') ?></textarea></label>
                </div>
                <input type="hidden" name="editor_mode" data-editor-mode value="<?= $hasLayoutBlocks ? 'advanced' : 'simple' ?>">
                <div class="page-editor-mode-switch" data-editor-mode-switch>
                    <button class="button <?= $hasLayoutBlocks ? 'secondary' : '' ?> compact" type="button" data-editor-mode-button="simple">
                        <span class="mdi mdi-text-box-edit-outline" aria-hidden="true"></span><span>Простий редактор</span>
                    </button>
                    <button class="button <?= $hasLayoutBlocks ? '' : 'secondary' ?> compact" type="button" data-editor-mode-button="advanced">
                        <span class="mdi mdi-view-grid-plus-outline" aria-hidden="true"></span><span>Розширений конструктор</span>
                    </button>
                </div>
                <section class="page-simple-editor" data-simple-editor-panel <?= $hasLayoutBlocks ? 'hidden' : '' ?>>
                    <div class="form-section-head">
                        <div>
                            <h2>Текст сторінки</h2>
                            <p class="meta">Для звичайної сторінки достатньо заголовка, короткого опису і цього тексту.</p>
                        </div>
                    </div>
                    <label>Вміст
                        <textarea class="textarea-large" name="blocks_text" data-rich-editor placeholder="Введіть текст сторінки. Можна вставляти HTML з базовим форматуванням."><?= e($simpleText) ?></textarea>
                    </label>
                </section>
                <input type="hidden" name="layout_json" data-layout-json>
                <div class="layout-builder page-advanced-editor" data-advanced-editor-panel data-layout-builder data-initial="<?= e(json_encode($blocks ?: [], JSON_UNESCAPED_UNICODE) ?: '[]') ?>" <?= $hasLayoutBlocks ? '' : 'hidden' ?>>
                    <div class="layout-builder-head">
                        <div>
                            <strong>Редактор секцій</strong>
                            <p class="meta mb-0">Створюйте секції, ряди, колонки та картки. На сайті вони рендеряться через Bootstrap grid.</p>
                        </div>
                        <div class="layout-builder-actions">
                            <button class="button secondary compact" type="button" data-layout-template="hero">
                                <span class="mdi mdi-page-layout-header" aria-hidden="true"></span><span>Hero</span>
                            </button>
                            <button class="button secondary compact" type="button" data-layout-template="two-cards">
                                <span class="mdi mdi-view-column-outline" aria-hidden="true"></span><span>2 колонки</span>
                            </button>
                            <button class="button secondary compact" type="button" data-layout-template="three-cards">
                                <span class="mdi mdi-view-grid-outline" aria-hidden="true"></span><span>3 колонки</span>
                            </button>
                            <button class="button compact" type="button" data-layout-add-section>
                                <span class="mdi mdi-plus" aria-hidden="true"></span><span>Секція</span>
                            </button>
                        </div>
                    </div>
                    <div class="layout-section-list" data-layout-sections></div>
                </div>
            </div>
        </section>

        <aside class="card admin-form-card editor-sidebar page-editor-sidebar">
            <div class="sidebar-section">
                <div class="form-section-head">
                    <div>
                        <p class="eyebrow">Публікація</p>
                        <h2>Стан сторінки</h2>
                        <p class="meta">Керує видимістю та позицією сторінки.</p>
                    </div>
                </div>
                <div class="form-grid">
                    <label>Статус
                        <select name="status">
                            <option value="draft" <?= selected($item['status'] ?? '', 'draft') ?>>Чернетка</option>
                            <option value="published" <?= selected($item['status'] ?? '', 'published') ?>>Опубліковано</option>
                        </select>
                    </label>
                    <label>Шаблон
                        <select name="template">
                            <?php foreach ($templates as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= selected($item['template'] ?? 'default', $value) ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Сортування<input type="number" name="sort_order" value="<?= e((string) ($item['sort_order'] ?? 0)) ?>"></label>
                </div>
            </div>

            <div class="sidebar-section">
                <div class="form-section-head">
                    <div>
                        <p class="eyebrow">Адреса</p>
                        <h2>SEO</h2>
                        <p class="meta">Slug можна лишити порожнім, тоді він сформується з назви.</p>
                    </div>
                </div>
                <div class="form-grid">
                    <label>Slug<input name="slug" value="<?= e($item['slug'] ?? '') ?>" placeholder="storinka"></label>
                </div>
            </div>

            <div class="page-publish-actions">
                <button type="submit"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span>Зберегти сторінку</span></button>
                <a class="button secondary" href="<?= url('/admin/pages') ?>"><span class="mdi mdi-close" aria-hidden="true"></span><span>Скасувати</span></a>
                <?php if ($isEdit): ?>
                    <button class="button danger" type="submit" form="pageDeleteForm"><span class="mdi mdi-delete-outline" aria-hidden="true"></span><span>Видалити</span></button>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</form>
<?php if ($isEdit): ?>
    <form id="pageDeleteForm" method="post" action="<?= url('/admin/pages/bulk') ?>" data-no-ajax data-delete-confirm="Видалити цю сторінку?" data-after-success-url="<?= url('/admin/pages') ?>">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="bulk_action" value="delete">
        <input type="hidden" name="ids[]" value="<?= e((string) $item['id']) ?>">
    </form>
<?php endif; ?>

<div class="modal fade" id="layoutCardModal" tabindex="-1" aria-labelledby="layoutCardModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1">Картка</p>
                    <h5 class="modal-title" id="layoutCardModalTitle" data-card-modal-title>Нова картка</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger py-2" data-card-modal-error hidden></div>
                <div class="layout-card-modal-grid">
                    <label>Стиль
                        <select data-card-modal-field="style">
                            <option value="default">Картка</option>
                            <option value="accent">Акцент</option>
                            <option value="plain">Без рамки</option>
                        </select>
                    </label>
                    <label>Заголовок
                        <input data-card-modal-field="title" required placeholder="Заголовок картки">
                    </label>
                    <label class="layout-card-modal-wide">Текст
                        <textarea data-card-modal-field="text" rows="6" required placeholder="Основний текст картки"></textarea>
                    </label>
                    <label class="layout-card-modal-wide">Зображення URL
                        <input data-card-modal-field="image" placeholder="/uploads/image.jpg">
                    </label>
                    <label>Текст кнопки
                        <input data-card-modal-field="button_text" placeholder="Детальніше">
                    </label>
                    <label>URL кнопки
                        <input data-card-modal-field="button_url" placeholder="/page/about">
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button secondary" data-bs-dismiss="modal"><span class="mdi mdi-close" aria-hidden="true"></span><span>Скасувати</span></button>
                <button type="button" data-layout-card-save><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span data-layout-card-save-label>Додати картку</span></button>
            </div>
        </div>
    </div>
</div>

<script src="<?= url('/assets/admin-pages-form.js') ?>"></script>
