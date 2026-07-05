<?php $isEdit = !empty($item['id']); ?>
<div class="page-head">
    <div>
        <p class="eyebrow">Файли та рішення</p>
        <h1><?= $isEdit ? 'Редагувати документ' : 'Новий документ' ?></h1>
        <p class="page-subtitle">Заповніть опис, статус, прив'язку до публічної інформації та файл документа.</p>
    </div>
    <div class="form-actions">
        <a class="button secondary" href="<?= url('/admin/documents') ?>"><span class="mdi mdi-arrow-left" aria-hidden="true"></span><span>До списку</span></a>
        <?php if (!empty($item['file_path'])): ?><a class="button secondary" href="<?= url('/uploads/' . $item['file_path']) ?>"><span class="mdi mdi-open-in-new" aria-hidden="true"></span><span>Відкрити файл</span></a><?php endif; ?>
    </div>
</div>

<form method="post" action="<?= url('/admin/documents/save') ?>" enctype="multipart/form-data">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= e((string) ($item['id'] ?? '')) ?>">

    <div class="editor-layout">
        <section class="card admin-form-card">
            <div class="form-section-head">
                <div>
                    <h2>Документ</h2>
                    <p class="meta">Назва, категорія та опис відображаються у публічному списку документів.</p>
                </div>
            </div>
            <div class="form-grid wide">
                <label>Назва<input name="title" value="<?= e($item['title'] ?? '') ?>" required></label>
                <label>Категорія<input name="category" value="<?= e($item['category'] ?? 'Загальні документи') ?>"></label>
                <label>Опис<textarea class="textarea-small" name="description"><?= e($item['description'] ?? '') ?></textarea></label>
                <label>Файл<input type="file" name="file"></label>
                <?php if (!empty($item['file_path'])): ?><div class="hint-box">Поточний файл збережено. Завантажте новий файл лише якщо потрібно замінити його.</div><?php endif; ?>
            </div>
        </section>

        <aside class="card admin-form-card editor-sidebar">
            <div class="form-section-head">
                <div>
                    <h2>Публікація</h2>
                    <p class="meta">Керує статусом, датами та розділом публічної інформації.</p>
                </div>
            </div>
            <div class="form-grid">
                <label>Статус
                    <select name="status">
                        <option value="published" <?= selected($item['status'] ?? 'published', 'published') ?>>published</option>
                        <option value="draft" <?= selected($item['status'] ?? '', 'draft') ?>>draft</option>
                    </select>
                </label>
                <label>Розділ публічної інформації
                    <select name="public_info_section_id">
                        <option value="">Не прив'язувати</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?= e((string) $section['id']) ?>" <?= selected($item['public_info_section_id'] ?? '', $section['id']) ?>><?= e($section['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Відповідальний<input name="responsible" value="<?= e($item['responsible'] ?? '') ?>"></label>
                <label>Дата затвердження<input name="approved_at" value="<?= e($item['approved_at'] ?? '') ?>" placeholder="2026-07-05"></label>
                <label>Дата публікації<input name="published_at" value="<?= e($item['published_at'] ?? '') ?>" placeholder="2026-07-05"></label>
            </div>
            <div class="form-actions stacked">
                <button type="submit"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span>Зберегти документ</span></button>
                <a class="button secondary" href="<?= url('/admin/documents') ?>"><span class="mdi mdi-close" aria-hidden="true"></span><span>Скасувати</span></a>
            </div>
        </aside>
    </div>
</form>
