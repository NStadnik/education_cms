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
        <a class="button secondary" href="<?= url('/admin/pages') ?>">До списку</a>
        <?php if (!empty($item['slug']) && ($item['status'] ?? '') === 'published'): ?>
            <a class="button secondary" href="<?= url($item['slug'] === 'home' ? '/' : '/page/' . $item['slug']) ?>">Переглянути</a>
        <?php endif; ?>
    </div>
</div>

<div class="metrics">
    <div class="metric"><span>Статус</span><strong><?= e($item['status'] ?? 'draft') ?></strong></div>
    <div class="metric"><span>Блоків</span><strong><?= e((string) $blockCount) ?></strong></div>
    <div class="metric"><span>Порядок</span><strong><?= e((string) ($item['sort_order'] ?? 0)) ?></strong></div>
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
                    <textarea class="textarea-large" name="blocks_text" placeholder="Заголовок блоку&#10;Текст блоку"><?php foreach (($blocks ?: []) as $block): ?><?= e($block['title'] ?? 'Текст') . "\n" . e($block['text'] ?? '') . "\n\n" ?><?php endforeach; ?></textarea>
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
                <button type="submit">Зберегти сторінку</button>
                <a class="button secondary" href="<?= url('/admin/pages') ?>">Скасувати</a>
            </div>
        </aside>
    </div>
</form>
