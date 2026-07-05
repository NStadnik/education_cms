<?php
    $isEdit = !empty($item['id']);
    $body = (string) ($item['body'] ?? '');
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
    <div class="metric"><div><span>Статус</span><strong><?= e($item['status'] ?? 'draft') ?></strong></div><span class="mdi mdi-circle-edit-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Слів</span><strong><?= e((string) $words) ?></strong></div><span class="mdi mdi-format-text metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Дата</span><strong><?= e($item['published_at'] ?? 'не задано') ?></strong></div><span class="mdi mdi-calendar-outline metric-icon" aria-hidden="true"></span></div>
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
                <label>Slug<input name="slug" value="<?= e($item['slug'] ?? '') ?>" placeholder="nazva-novyny"></label>
                <label>Текст<textarea class="textarea-large" name="body" data-rich-editor required><?= e($item['body'] ?? '') ?></textarea></label>
            </div>
        </section>

        <aside class="card admin-form-card editor-sidebar">
            <div class="form-section-head">
                <div>
                    <h2>Публікація</h2>
                    <p class="meta">Для опублікованої новини дата заповниться автоматично, якщо її не вказати.</p>
                </div>
            </div>

            <div class="form-grid">
                <label>Статус
                    <select name="status">
                        <option value="draft" <?= selected($item['status'] ?? '', 'draft') ?>>draft</option>
                        <option value="published" <?= selected($item['status'] ?? '', 'published') ?>>published</option>
                    </select>
                </label>
                <label>Дата публікації<input name="published_at" value="<?= e($item['published_at'] ?? '') ?>" placeholder="2026-07-05"></label>
                <div class="hint-box">Залиште slug порожнім, щоб система сформувала його з назви.</div>
            </div>

            <div class="form-actions stacked">
                <button type="submit"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span>Зберегти новину</span></button>
                <a class="button secondary" href="<?= url('/admin/news') ?>"><span class="mdi mdi-close" aria-hidden="true"></span><span>Скасувати</span></a>
            </div>
        </aside>
    </div>
</form>
