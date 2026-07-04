<h1>Публічна інформація</h1>
<?php foreach ($sections as $section): ?>
    <details class="card" style="margin-bottom:12px">
        <summary><strong><?= e($section['title']) ?></strong> <span class="status <?= $section['status'] === 'published' ? 'ok' : 'warn' ?>"><?= e($section['status'] ?? 'missing') ?></span></summary>
        <form class="form-grid" method="post" action="<?= url('/admin/public-info/save') ?>" enctype="multipart/form-data" style="margin-top:16px">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="item_id" value="<?= e((string) $section['item_id']) ?>">
            <label>Назва<input name="title" value="<?= e($section['item_title'] ?: $section['title']) ?>"></label>
            <label>Опис / текст<textarea name="body"><?= e($section['body'] ?? '') ?></textarea></label>
            <div class="grid grid-3">
                <label>Статус<select name="status"><option value="missing" <?= selected($section['status'], 'missing') ?>>missing</option><option value="draft" <?= selected($section['status'], 'draft') ?>>draft</option><option value="published" <?= selected($section['status'], 'published') ?>>published</option></select></label>
                <label>Відповідальний<input name="responsible" value="<?= e($section['responsible'] ?? '') ?>"></label>
                <label>Дата затвердження<input name="approved_at" value="<?= e($section['approved_at'] ?? '') ?>"></label>
            </div>
            <label>Дата публікації<input name="published_at" value="<?= e($section['published_at'] ?? '') ?>"></label>
            <label>Файл<input type="file" name="file"></label>
            <?php if ($section['file_path']): ?><p><a href="<?= url('/uploads/' . $section['file_path']) ?>">Поточний файл</a></p><?php endif; ?>
            <button type="submit">Зберегти</button>
        </form>
    </details>
<?php endforeach; ?>
