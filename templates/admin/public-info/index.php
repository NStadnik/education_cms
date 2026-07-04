<h1>Публічна інформація</h1>
<p class="meta">Цей розділ є чеклістом документів. Додавайте один або кілька документів до кожного обов'язкового розділу.</p>
<?php foreach ($sections as $section): ?>
    <details class="card" style="margin-bottom:12px">
        <summary>
            <strong><?= e($section['title']) ?></strong>
            <span class="status <?= ((int) $section['documents_count']) > 0 ? 'ok' : 'warn' ?>">
                <?= ((int) $section['documents_count']) > 0 ? 'документи є' : 'немає документів' ?>
            </span>
        </summary>
        <form class="form-grid" method="post" action="<?= url('/admin/public-info/save') ?>" enctype="multipart/form-data" style="margin-top:16px">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="public_info_section_id" value="<?= e((string) $section['id']) ?>">
            <label>Назва документа<input name="title" value="<?= e($section['title']) ?>" required></label>
            <label>Опис<textarea name="description"></textarea></label>
            <div class="grid grid-3">
                <label>Статус<select name="status"><option value="published">published</option><option value="draft">draft</option></select></label>
                <label>Відповідальний<input name="responsible"></label>
                <label>Дата затвердження<input name="approved_at" placeholder="2026-07-04"></label>
            </div>
            <label>Дата публікації<input name="published_at" placeholder="2026-07-04"></label>
            <label>Файл<input type="file" name="file"></label>
            <button type="submit">Додати документ</button>
        </form>
        <?php $sectionDocuments = array_filter($documents, fn($document) => (int) $document['public_info_section_id'] === (int) $section['id']); ?>
        <?php if ($sectionDocuments): ?>
            <table style="margin-top:16px">
                <tr><th>Документ</th><th>Статус</th><th>Оновлено</th><th>Файл</th></tr>
                <?php foreach ($sectionDocuments as $document): ?>
                    <tr>
                        <td><?= e($document['title']) ?><br><span class="meta"><?= e($document['description'] ?? '') ?></span></td>
                        <td><span class="status"><?= e($document['status']) ?></span></td>
                        <td><?= e($document['updated_at']) ?></td>
                        <td><?php if ($document['file_path']): ?><a href="<?= url('/uploads/' . $document['file_path']) ?>">відкрити</a><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </details>
<?php endforeach; ?>
