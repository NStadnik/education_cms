<?php foreach ($sections as $section): ?>
    <?php $sectionId = (int) $section['id']; ?>
    <?php
        $sectionDocuments = array_filter($documents, static function ($document) use ($section) {
            return (int) $document['public_info_section_id'] === (int) $section['id'];
        });
    ?>
    <div class="accordion-item mb-2 border rounded" data-section-card="<?= e((string) $sectionId) ?>" data-list-row>
        <h2 class="accordion-header" id="section-heading-<?= e((string) $sectionId) ?>">
            <div class="accordion-title-row">
                <label class="accordion-check"><input type="checkbox" name="section_ids[]" value="<?= e((string) $sectionId) ?>" data-bulk-check aria-label="Вибрати розділ"></label>
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#section-body-<?= e((string) $sectionId) ?>" aria-expanded="false" aria-controls="section-body-<?= e((string) $sectionId) ?>">
                    <span class="mdi mdi-folder-outline me-2" aria-hidden="true"></span>
                    <span class="me-2" data-section-title><?= e($section['title']) ?></span>
                    <span class="badge <?= ((int) $section['published_documents_count']) > 0 ? 'text-bg-success' : 'text-bg-warning' ?>">
                        <?= ((int) $section['published_documents_count']) > 0 ? 'документи є' : 'немає документів' ?>
                    </span>
                </button>
            </div>
        </h2>
        <div id="section-body-<?= e((string) $sectionId) ?>" class="accordion-collapse collapse" aria-labelledby="section-heading-<?= e((string) $sectionId) ?>" data-bs-parent="#publicInfoAccordion">
            <div class="accordion-body">
                <div class="toolbar">
                    <div>
                        <h3><?= e($section['title']) ?></h3>
                        <p class="meta"><?= e($section['description'] ?? '') ?></p>
                    </div>
                    <div class="form-actions">
                        <a class="button secondary compact" href="<?= url('/admin/public-info/sections/edit?id=' . $sectionId) ?>"><span class="mdi mdi-pencil-outline" aria-hidden="true"></span><span>Редагувати розділ</span></a>
                        <a class="button compact" href="<?= url('/admin/public-info/documents/edit?section_id=' . $sectionId) ?>"><span class="mdi mdi-file-plus-outline" aria-hidden="true"></span><span>Додати документ</span></a>
                    </div>
                </div>

                <?php if ($sectionDocuments): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead>
                                <tr><th><input type="checkbox" data-bulk-check-all aria-label="Вибрати всі документи"></th><th>Документ</th><th>Статус</th><th>Оновлено</th><th>Файл</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sectionDocuments as $document): ?>
                                    <tr>
                                        <td><input type="checkbox" name="document_ids[]" value="<?= e((string) $document['id']) ?>" data-bulk-check aria-label="Вибрати документ"></td>
                                        <td><?= e($document['title']) ?><br><span class="text-muted small"><?= e($document['description'] ?? '') ?></span></td>
                                        <td><span class="badge text-bg-secondary"><?= e($document['status']) ?></span></td>
                                        <td><?= e($document['updated_at']) ?></td>
                                        <td><?php if ($document['file_path']): ?><a href="<?= url('/uploads/' . $document['file_path']) ?>"><span class="mdi mdi-open-in-new" aria-hidden="true"></span> відкрити</a><?php endif; ?></td>
                                        <td><a class="button secondary compact" href="<?= url('/admin/public-info/documents/edit?id=' . $document['id']) ?>"><span class="mdi mdi-pencil-outline" aria-hidden="true"></span><span>Редагувати</span></a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state small">Документи ще не додані.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>
