<h1>Публічна інформація</h1>
<p class="meta">Цей розділ є чеклістом документів. Додавайте один або кілька документів до кожного обов'язкового розділу.</p>

<details class="card" style="margin-bottom:16px">
    <summary><strong>Додати розділ до переліку</strong></summary>
    <form class="form-grid" method="post" action="<?= url('/admin/public-info/sections/save') ?>" data-section-save data-new-section style="margin-top:16px">
        <?= \App\Core\Csrf::field() ?>
        <label>Назва розділу<input name="title" required></label>
        <div class="grid grid-3">
            <label>Slug<input name="slug" placeholder="napryklad-rozdil"></label>
            <label>Порядок<input type="number" name="sort_order" value="100"></label>
            <label><input type="checkbox" name="is_required" value="1" checked> Обов'язковий розділ</label>
        </div>
        <label>Опис<textarea name="description"></textarea></label>
        <button type="submit">Додати розділ</button>
    </form>
</details>

<?php foreach ($sections as $section): ?>
    <details class="card" style="margin-bottom:12px" data-section-card="<?= e((string) $section['id']) ?>">
        <summary>
            <strong data-section-title><?= e($section['title']) ?></strong>
            <span class="status <?= ((int) $section['published_documents_count']) > 0 ? 'ok' : 'warn' ?>">
                <?= ((int) $section['published_documents_count']) > 0 ? 'документи є' : 'немає документів' ?>
            </span>
        </summary>
        <h2>Налаштування розділу</h2>
        <form class="form-grid" method="post" action="<?= url('/admin/public-info/sections/save') ?>" data-section-save style="margin-top:16px">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="id" value="<?= e((string) $section['id']) ?>">
            <label>Назва розділу<input name="title" value="<?= e($section['title']) ?>" required></label>
            <div class="grid grid-3">
                <label>Slug<input name="slug" value="<?= e($section['slug']) ?>"></label>
                <label>Порядок<input type="number" name="sort_order" value="<?= e((string) $section['sort_order']) ?>"></label>
                <label><input type="checkbox" name="is_required" value="1" <?= checked((int) $section['is_required']) ?>> Обов'язковий розділ</label>
            </div>
            <label>Опис<textarea name="description"><?= e($section['description'] ?? '') ?></textarea></label>
            <button type="submit">Зберегти розділ</button>
        </form>
        <?php if ((int) $section['documents_count'] === 0): ?>
            <form method="post" action="<?= url('/admin/public-info/sections/delete') ?>" data-section-delete style="margin-top:10px">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="id" value="<?= e((string) $section['id']) ?>">
                <button class="button danger" type="submit">Видалити порожній розділ</button>
            </form>
        <?php endif; ?>
        <h2>Документи розділу</h2>
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
        <?php
            $sectionDocuments = array_filter($documents, static function ($document) use ($section) {
                return (int) $document['public_info_section_id'] === (int) $section['id'];
            });
        ?>
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

<script>
document.addEventListener('submit', async function (event) {
    const saveForm = event.target.closest('[data-section-save]');
    const deleteForm = event.target.closest('[data-section-delete]');
    if (!saveForm && !deleteForm) {
        return;
    }

    event.preventDefault();
    const form = saveForm || deleteForm;
    const button = form.querySelector('button[type="submit"]');
    const originalText = button ? button.textContent : '';
    setFormMessage(form, 'Збереження...', false);
    if (button) {
        button.disabled = true;
    }

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        });
        const data = await response.json();
        if (!data.ok) {
            throw new Error(data.message || 'Помилка запиту.');
        }

        if (saveForm) {
            if (form.hasAttribute('data-new-section')) {
                setFormMessage(form, data.message || 'Розділ додано.', false);
                window.setTimeout(function () { window.location.reload(); }, 500);
                return;
            }

            const card = form.closest('[data-section-card]');
            if (card && data.section) {
                const title = card.querySelector('[data-section-title]');
                if (title) {
                    title.textContent = data.section.title;
                }
                form.querySelector('[name="slug"]').value = data.section.slug;
                form.querySelector('[name="sort_order"]').value = data.section.sort_order;
            }
        }

        if (deleteForm) {
            const card = form.closest('[data-section-card]');
            if (card) {
                card.remove();
            }
            return;
        }

        setFormMessage(form, data.message || 'Збережено.', false);
    } catch (error) {
        setFormMessage(form, error.message, true);
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = originalText;
        }
    }
});

function setFormMessage(form, message, isError) {
    let node = form.querySelector('[data-ajax-message]');
    if (!node) {
        node = document.createElement('p');
        node.setAttribute('data-ajax-message', '');
        form.appendChild(node);
    }
    node.className = isError ? 'alert' : 'meta';
    node.textContent = message;
}
</script>
