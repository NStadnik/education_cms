<h1>Документи</h1>
<div class="card">
    <form class="form-grid" method="post" action="<?= url('/admin/documents/save') ?>" enctype="multipart/form-data">
        <?= \App\Core\Csrf::field() ?>
        <label>Назва<input name="title" required></label>
        <div class="grid grid-3">
            <label>Категорія<input name="category" value="Загальні документи"></label>
            <label>Статус<select name="status"><option value="published">published</option><option value="draft">draft</option></select></label>
            <label>Дата затвердження<input name="approved_at" placeholder="2026-07-04"></label>
        </div>
        <div class="grid grid-3">
            <label>Розділ публічної інформації
                <select name="public_info_section_id">
                    <option value="">Не прив'язувати</option>
                    <?php foreach ($sections as $section): ?>
                        <option value="<?= e((string) $section['id']) ?>"><?= e($section['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Відповідальний<input name="responsible"></label>
            <label>Дата публікації<input name="published_at" placeholder="2026-07-04"></label>
        </div>
        <label>Опис<textarea name="description"></textarea></label>
        <label>Файл<input type="file" name="file"></label>
        <button type="submit">Додати документ</button>
    </form>
</div>
<table style="margin-top:16px">
    <tr><th>Назва</th><th>Категорія</th><th>Публічна інформація</th><th>Статус</th><th>Файл</th></tr>
    <?php foreach ($items as $item): ?>
        <tr>
            <td><?= e($item['title']) ?></td>
            <td><?= e($item['category']) ?></td>
            <td><?= $item['public_info_section_id'] ? 'так' : 'ні' ?></td>
            <td><span class="status"><?= e($item['status']) ?></span></td>
            <td><?php if ($item['file_path']): ?><a href="<?= url('/uploads/' . $item['file_path']) ?>">відкрити</a><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
</table>
