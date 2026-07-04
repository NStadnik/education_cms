<h1>Новина</h1>
<form class="form-grid" method="post" action="<?= url('/admin/news/save') ?>">
    <?= App\Core\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= e((string) ($item['id'] ?? '')) ?>">
    <label>Назва<input name="title" value="<?= e($item['title'] ?? '') ?>" required></label>
    <label>Slug<input name="slug" value="<?= e($item['slug'] ?? '') ?>"></label>
    <label>Текст<textarea name="body" required><?= e($item['body'] ?? '') ?></textarea></label>
    <div class="grid grid-3">
        <label>Статус<select name="status"><option value="draft" <?= selected($item['status'] ?? '', 'draft') ?>>draft</option><option value="published" <?= selected($item['status'] ?? '', 'published') ?>>published</option></select></label>
        <label>Дата публікації<input name="published_at" value="<?= e($item['published_at'] ?? '') ?>"></label>
    </div>
    <button type="submit">Зберегти</button>
</form>
