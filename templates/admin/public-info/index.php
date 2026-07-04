<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Публічна інформація</h1>
        <p class="text-muted mb-0">Чекліст розділів і документи, які публікуються на сайті.</p>
    </div>
</div>

<div class="card bootstrap-card mb-4">
    <div class="card-header bg-white">
        <strong>Додати розділ до переліку</strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= url('/admin/public-info/sections/save') ?>" data-section-save data-new-section>
            <?= \App\Core\Csrf::field() ?>
            <div class="row g-3">
                <div class="col-12 col-lg-5">
                    <label class="form-label">Назва розділу</label>
                    <input class="form-control" name="title" required>
                </div>
                <div class="col-12 col-lg-3">
                    <label class="form-label">Slug</label>
                    <input class="form-control" name="slug" placeholder="napryklad-rozdil">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label">Порядок</label>
                    <input class="form-control" type="number" name="sort_order" value="100">
                </div>
                <div class="col-6 col-lg-2 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" id="new-is-required" type="checkbox" name="is_required" value="1" checked>
                        <label class="form-check-label" for="new-is-required">Обов'язковий</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Опис</label>
                    <textarea class="form-control" name="description" rows="3"></textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Додати розділ</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="accordion" id="publicInfoAccordion">
    <?php foreach ($sections as $section): ?>
        <?php $sectionId = (int) $section['id']; ?>
        <div class="accordion-item mb-2 border rounded" data-section-card="<?= e((string) $sectionId) ?>">
            <h2 class="accordion-header" id="section-heading-<?= e((string) $sectionId) ?>">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#section-body-<?= e((string) $sectionId) ?>" aria-expanded="false" aria-controls="section-body-<?= e((string) $sectionId) ?>">
                    <span class="me-2" data-section-title><?= e($section['title']) ?></span>
                    <span class="badge <?= ((int) $section['published_documents_count']) > 0 ? 'text-bg-success' : 'text-bg-warning' ?>">
                        <?= ((int) $section['published_documents_count']) > 0 ? 'документи є' : 'немає документів' ?>
                    </span>
                </button>
            </h2>
            <div id="section-body-<?= e((string) $sectionId) ?>" class="accordion-collapse collapse" aria-labelledby="section-heading-<?= e((string) $sectionId) ?>" data-bs-parent="#publicInfoAccordion">
                <div class="accordion-body">
                    <div class="row g-4">
                        <div class="col-12 col-xl-5">
                            <div class="card bootstrap-card h-100">
                                <div class="card-header bg-white">
                                    <strong>Налаштування розділу</strong>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="<?= url('/admin/public-info/sections/save') ?>" data-section-save>
                                        <?= \App\Core\Csrf::field() ?>
                                        <input type="hidden" name="id" value="<?= e((string) $sectionId) ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Назва розділу</label>
                                            <input class="form-control" name="title" value="<?= e($section['title']) ?>" required>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-12 col-md-7">
                                                <label class="form-label">Slug</label>
                                                <input class="form-control" name="slug" value="<?= e($section['slug']) ?>">
                                            </div>
                                            <div class="col-12 col-md-5">
                                                <label class="form-label">Порядок</label>
                                                <input class="form-control" type="number" name="sort_order" value="<?= e((string) $section['sort_order']) ?>">
                                            </div>
                                        </div>
                                        <div class="form-check my-3">
                                            <input class="form-check-input" id="required-<?= e((string) $sectionId) ?>" type="checkbox" name="is_required" value="1" <?= checked((int) $section['is_required']) ?>>
                                            <label class="form-check-label" for="required-<?= e((string) $sectionId) ?>">Обов'язковий розділ</label>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Опис</label>
                                            <textarea class="form-control" name="description" rows="3"><?= e($section['description'] ?? '') ?></textarea>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button class="btn btn-primary" type="submit">Зберегти</button>
                                        </div>
                                    </form>
                                    <?php if ((int) $section['documents_count'] === 0): ?>
                                        <form method="post" action="<?= url('/admin/public-info/sections/delete') ?>" data-section-delete class="mt-2">
                                            <?= \App\Core\Csrf::field() ?>
                                            <input type="hidden" name="id" value="<?= e((string) $sectionId) ?>">
                                            <button class="btn btn-outline-danger btn-sm" type="submit">Видалити порожній розділ</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-xl-7">
                            <div class="card bootstrap-card mb-3">
                                <div class="card-header bg-white">
                                    <strong>Додати документ</strong>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="<?= url('/admin/public-info/save') ?>" enctype="multipart/form-data">
                                        <?= \App\Core\Csrf::field() ?>
                                        <input type="hidden" name="public_info_section_id" value="<?= e((string) $sectionId) ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Назва документа</label>
                                            <input class="form-control" name="title" value="<?= e($section['title']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Опис</label>
                                            <textarea class="form-control" name="description" rows="3"></textarea>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-12 col-md-4">
                                                <label class="form-label">Статус</label>
                                                <select class="form-select" name="status">
                                                    <option value="published">published</option>
                                                    <option value="draft">draft</option>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label class="form-label">Відповідальний</label>
                                                <input class="form-control" name="responsible">
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label class="form-label">Дата затвердження</label>
                                                <input class="form-control" name="approved_at" placeholder="2026-07-04">
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label class="form-label">Дата публікації</label>
                                                <input class="form-control" name="published_at" placeholder="2026-07-04">
                                            </div>
                                            <div class="col-12 col-md-8">
                                                <label class="form-label">Файл</label>
                                                <input class="form-control" type="file" name="file">
                                            </div>
                                        </div>
                                        <button class="btn btn-primary mt-3" type="submit">Додати документ</button>
                                    </form>
                                </div>
                            </div>

                            <?php
                                $sectionDocuments = array_filter($documents, static function ($document) use ($section) {
                                    return (int) $document['public_info_section_id'] === (int) $section['id'];
                                });
                            ?>
                            <?php if ($sectionDocuments): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle">
                                        <thead>
                                            <tr><th>Документ</th><th>Статус</th><th>Оновлено</th><th>Файл</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sectionDocuments as $document): ?>
                                                <tr>
                                                    <td><?= e($document['title']) ?><br><span class="text-muted small"><?= e($document['description'] ?? '') ?></span></td>
                                                    <td><span class="badge text-bg-secondary"><?= e($document['status']) ?></span></td>
                                                    <td><?= e($document['updated_at']) ?></td>
                                                    <td><?php if ($document['file_path']): ?><a href="<?= url('/uploads/' . $document['file_path']) ?>">відкрити</a><?php endif; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted small mb-0">Документи ще не додані.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

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
        node = document.createElement('div');
        node.setAttribute('data-ajax-message', '');
        form.appendChild(node);
    }
    node.className = isError ? 'alert alert-warning mt-3 mb-0' : 'small text-muted mt-3';
    node.textContent = message;
}
</script>
