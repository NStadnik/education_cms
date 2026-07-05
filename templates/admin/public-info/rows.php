<?php foreach ($sections as $section): ?>
    <?php $sectionId = (int) $section['id']; ?>
    <?php
        $sectionDocuments = array_filter($documents, static function ($document) use ($section) {
            return (int) $document['public_info_section_id'] === (int) $section['id'];
        });
    ?>
    <div class="accordion-item mb-2 border rounded" data-section-card="<?= e((string) $sectionId) ?>" data-list-row>
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
