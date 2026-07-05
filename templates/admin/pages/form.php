<?php
    $isEdit = !empty($item['id']);
    $blocks = $item ? json_decode($item['blocks_json'], true) : [];
    $blockCount = count($blocks ?: []);
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Контент сайту</p>
        <h1><?= $isEdit ? 'Редагувати сторінку' : 'Нова сторінка' ?></h1>
        <p class="page-subtitle">Налаштуйте назву, адресу, короткий опис і текстові блоки сторінки.</p>
    </div>
    <div class="form-actions">
        <a class="button secondary" href="<?= url('/admin/pages') ?>"><span class="mdi mdi-arrow-left" aria-hidden="true"></span><span>До списку</span></a>
        <?php if (!empty($item['slug']) && ($item['status'] ?? '') === 'published'): ?>
            <a class="button secondary" href="<?= url($item['slug'] === 'home' ? '/' : '/page/' . $item['slug']) ?>"><span class="mdi mdi-eye-outline" aria-hidden="true"></span><span>Переглянути</span></a>
        <?php endif; ?>
    </div>
</div>

<div class="metrics">
    <div class="metric"><div><span>Статус</span><strong><?= e($item['status'] ?? 'draft') ?></strong></div><span class="mdi mdi-circle-edit-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Блоків</span><strong><?= e((string) $blockCount) ?></strong></div><span class="mdi mdi-view-grid-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Порядок</span><strong><?= e((string) ($item['sort_order'] ?? 0)) ?></strong></div><span class="mdi mdi-sort-numeric-ascending metric-icon" aria-hidden="true"></span></div>
</div>

<form method="post" action="<?= url('/admin/pages/save') ?>">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= e((string) ($item['id'] ?? '')) ?>">

    <div class="editor-layout">
        <section class="card admin-form-card">
            <div class="form-section-head">
                <div>
                    <h2>Основний вміст</h2>
                    <p class="meta">Ці дані відображаються на публічній сторінці та в меню.</p>
                </div>
            </div>

            <div class="form-grid wide">
                <label>Назва<input name="title" value="<?= e($item['title'] ?? '') ?>" required></label>
                <label>Slug<input name="slug" value="<?= e($item['slug'] ?? '') ?>" placeholder="storinka"></label>
                <label>Короткий опис<textarea class="textarea-small" name="excerpt"><?= e($item['excerpt'] ?? '') ?></textarea></label>
                <label>Блоки тексту
                    <textarea class="textarea-large" name="blocks_text" data-rich-editor placeholder="Текст сторінки"><?php foreach (($blocks ?: []) as $block): ?><?= e($block['text'] ?? '') ?><?php endforeach; ?></textarea>
                </label>
            </div>
        </section>

        <aside class="card admin-form-card editor-sidebar">
            <div class="form-section-head">
                <div>
                    <h2>Публікація</h2>
                    <p class="meta">Керує видимістю та позицією сторінки.</p>
                </div>
            </div>

            <div class="form-grid">
                <label>Статус
                    <select name="status">
                        <option value="draft" <?= selected($item['status'] ?? '', 'draft') ?>>draft</option>
                        <option value="published" <?= selected($item['status'] ?? '', 'published') ?>>published</option>
                    </select>
                </label>
                <label>Сортування<input type="number" name="sort_order" value="<?= e((string) ($item['sort_order'] ?? 0)) ?>"></label>
                <div class="hint-box">Залиште slug порожнім, щоб система сформувала його з назви.</div>
            </div>

            <div class="form-actions stacked">
                <button type="submit"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span>Зберегти сторінку</span></button>
                <a class="button secondary" href="<?= url('/admin/pages') ?>"><span class="mdi mdi-close" aria-hidden="true"></span><span>Скасувати</span></a>
            </div>
        </aside>
    </div>
</form>
