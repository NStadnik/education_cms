<?php $isEdit = !empty($item['id']); ?>
<div class="page-head">
    <div>
        <p class="eyebrow">Відкритість</p>
        <h1><?= $isEdit ? 'Редагувати розділ' : 'Новий розділ' ?></h1>
        <p class="page-subtitle">Налаштуйте назву, адресу, порядок і обов'язковість розділу публічної інформації.</p>
    </div>
    <a class="button secondary" href="<?= url('/admin/public-info') ?>"><span class="mdi mdi-arrow-left" aria-hidden="true"></span><span>До списку</span></a>
</div>

<form method="post" action="<?= url('/admin/public-info/sections/save') ?>">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= e((string) ($item['id'] ?? '')) ?>">
    <div class="editor-layout">
        <section class="card admin-form-card">
            <div class="form-section-head">
                <div>
                    <h2>Розділ</h2>
                    <p class="meta">Опис допомагає пояснити призначення розділу в адмінпанелі.</p>
                </div>
            </div>
            <div class="form-grid wide">
                <label>Назва<input name="title" value="<?= e($item['title'] ?? '') ?>" required></label>
                <label>Slug<input name="slug" value="<?= e($item['slug'] ?? '') ?>" placeholder="napryklad-rozdil"></label>
                <label>Опис<textarea class="textarea-small" name="description"><?= e($item['description'] ?? '') ?></textarea></label>
            </div>
        </section>

        <aside class="card admin-form-card editor-sidebar">
            <div class="form-section-head">
                <div>
                    <h2>Параметри</h2>
                    <p class="meta">Порядок визначає позицію розділу у списку.</p>
                </div>
            </div>
            <div class="form-grid">
                <label>Порядок<input type="number" name="sort_order" value="<?= e((string) ($item['sort_order'] ?? 100)) ?>"></label>
                <label class="check-row"><input type="checkbox" name="is_required" value="1" <?= checked($item['is_required'] ?? 1) ?>> Обов'язковий</label>
            </div>
            <div class="form-actions stacked">
                <button type="submit"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span>Зберегти розділ</span></button>
                <a class="button secondary" href="<?= url('/admin/public-info') ?>"><span class="mdi mdi-close" aria-hidden="true"></span><span>Скасувати</span></a>
            </div>
        </aside>
    </div>
</form>
