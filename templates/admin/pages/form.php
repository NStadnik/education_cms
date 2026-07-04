<h1>Сторінка</h1>
<?php $blocks = $item ? json_decode($item['blocks_json'], true) : []; ?>
<form class="form-grid" method="post" action="<?= url('/admin/pages/save') ?>">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= e((string) ($item['id'] ?? '')) ?>">
    <label>Назва<input name="title" value="<?= e($item['title'] ?? '') ?>" required></label>
    <label>Slug<input name="slug" value="<?= e($item['slug'] ?? '') ?>"></label>
    <label>Короткий опис<textarea name="excerpt"><?= e($item['excerpt'] ?? '') ?></textarea></label>
    <label>Блоки тексту
        <textarea name="blocks_text"><?php foreach (($blocks ?: []) as $block): ?><?= e($block['title'] ?? 'Текст') . "\n" . e($block['text'] ?? '') . "\n\n" ?><?php endforeach; ?></textarea>
    </label>
    <div class="grid grid-3">
        <label>Статус<select name="status"><option value="draft" <?= selected($item['status'] ?? '', 'draft') ?>>draft</option><option value="published" <?= selected($item['status'] ?? '', 'published') ?>>published</option></select></label>
        <label>Сортування<input type="number" name="sort_order" value="<?= e((string) ($item['sort_order'] ?? 0)) ?>"></label>
    </div>
    <button type="submit">Зберегти</button>
</form>
