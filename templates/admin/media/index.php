<div class="page-head">
    <div>
        <p class="eyebrow">Файлове сховище</p>
        <h1>Медіафайли</h1>
        <p class="page-subtitle">Керуйте всіма файлами, завантаженими у сховище сайту.</p>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert"><?= e($error) ?></div>
<?php endif; ?>
<div class="alert d-none" data-media-message></div>

<div class="metrics">
    <div class="metric"><div><span>Усього</span><strong data-media-stat="total"><?= e((string) $stats['total']) ?></strong></div><span class="mdi mdi-folder-multiple-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Зображень</span><strong data-media-stat="images"><?= e((string) $stats['images']) ?></strong></div><span class="mdi mdi-image-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Вільні</span><strong data-media-stat="unused"><?= e((string) $stats['unused']) ?></strong></div><span class="mdi mdi-trash-can-outline metric-icon" aria-hidden="true"></span></div>
</div>

<section class="card admin-form-card">
    <div class="form-section-head">
        <div>
            <h2>Завантажити файл</h2>
            <p class="meta">Підтримуються PDF, Word, Excel і зображення JPG, PNG, WEBP. PHP-ліміт: <?= e($uploadLimitLabel ?? 'не визначено') ?>.</p>
        </div>
    </div>
    <form class="form-grid wide" method="post" action="<?= url('/admin/media/upload') ?>" enctype="multipart/form-data" data-media-upload data-upload-limit="<?= e((string) ($uploadLimitBytes ?? 0)) ?>" data-upload-limit-label="<?= e((string) ($uploadLimitLabel ?? '')) ?>">
        <?= \App\Core\Csrf::field() ?>
        <?php if (!empty($uploadLimitBytes)): ?><input type="hidden" name="MAX_FILE_SIZE" value="<?= e((string) $uploadLimitBytes) ?>"><?php endif; ?>
        <label>Файл<input type="file" name="file" required></label>
        <label>Віртуальна папка
            <input name="folder" list="mediaFolderOptions" placeholder="Наприклад: Герої, Документи, Новини">
        </label>
        <div class="form-actions">
            <button type="submit"><span class="mdi mdi-upload-outline" aria-hidden="true"></span><span>Завантажити</span></button>
        </div>
    </form>
</section>

<datalist id="mediaFolderOptions">
    <?php foreach (($folders ?? []) as $folderName): ?>
        <option value="<?= e((string) $folderName) ?>"></option>
    <?php endforeach; ?>
</datalist>

<form id="mediaBulkForm" method="post" action="<?= url('/admin/media/bulk') ?>" data-no-ajax data-list-panel="#mediaListPanel">
    <?= \App\Core\Csrf::field() ?>
</form>
<div id="mediaListPanel" class="list-panel" data-infinite-list data-list-url="<?= url('/admin/media') ?>" data-list-target="#mediaRows" data-list-offset="<?= e((string) count($items)) ?>" data-list-limit="<?= e((string) $limit) ?>" data-list-has-more="<?= count($items) < $total ? '1' : '0' ?>" data-list-empty-label="файли" data-media-metadata-url="<?= url('/admin/media/metadata') ?>">
    <div class="list-tools">
        <input data-filter-input type="search" value="<?= e($query ?? '') ?>" placeholder="Пошук файлів" aria-label="Пошук медіафайлів">
        <select name="folder" data-list-filter data-media-folder-filter aria-label="Віртуальна папка">
            <option value="">Усі папки</option>
            <option value="__none" <?= ($folder ?? '') === '__none' ? 'selected' : '' ?>>Без папки</option>
            <?php foreach (($folders ?? []) as $folderName): ?>
                <option value="<?= e((string) $folderName) ?>" <?= selected($folder ?? '', (string) $folderName) ?>><?= e((string) $folderName) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="admin-view-switch" role="group" aria-label="Режим перегляду медіафайлів">
            <button class="button secondary compact" type="button" data-media-view="list" title="Список"><span class="mdi mdi-format-list-bulleted" aria-hidden="true"></span></button>
            <button class="button secondary compact" type="button" data-media-view="grid" title="Великі превʼю"><span class="mdi mdi-view-grid-outline" aria-hidden="true"></span></button>
        </div>
        <div class="bulk-actions">
            <select name="bulk_action" form="mediaBulkForm" aria-label="Групова дія">
                <option value="">Групова дія</option>
                <option value="delete">Видалити</option>
            </select>
            <button class="button secondary compact" type="submit" form="mediaBulkForm"><span class="mdi mdi-check" aria-hidden="true"></span><span>Застосувати</span></button>
            <span class="meta"><span data-filter-count><?= e((string) $total) ?></span> файлів · <span data-media-stat="size"><?= e($stats['size']) ?></span></span>
        </div>
    </div>
    <div class="table-scroll">
        <table>
            <thead><tr><th><input type="checkbox" data-bulk-check-all form="mediaBulkForm" aria-label="Вибрати всі"></th><th>Файл</th><th>Папка</th><th>Тип</th><th>Розмір</th><th>Оновлено</th><th>Використання</th><th></th></tr></thead>
            <tbody id="mediaRows"><?= $this->partial('admin/media/rows', ['items' => $items]) ?></tbody>
        </table>
    </div>
    <div class="empty-state <?= $items ? 'd-none' : '' ?>" data-list-empty>Файли не знайдені.</div>
    <div class="list-sentinel" data-list-sentinel></div>
    <p class="meta list-status" data-list-status><?= count($items) < $total ? 'Прокрутіть нижче, щоб завантажити ще.' : 'Усі файли завантажено.' ?></p>
</div>

<div class="modal fade" id="mediaMetadataModal" tabindex="-1" aria-labelledby="mediaMetadataTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" action="<?= url('/admin/media/metadata') ?>" data-media-metadata-form data-no-ajax>
                <div class="modal-header">
                    <div>
                        <p class="eyebrow mb-1">Медіафайл</p>
                        <h2 class="modal-title h5" id="mediaMetadataTitle">Метадані файлу</h2>
                        <p class="meta mb-0" data-media-metadata-path></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
                </div>
                <div class="modal-body">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="path" data-media-metadata-field="path">
                    <input type="hidden" name="current_folder" data-media-current-folder>
                    <div class="media-metadata-layout">
                        <div class="media-metadata-preview" data-media-metadata-preview></div>
                        <div class="form-grid">
                            <label>Віртуальна папка
                                <input name="folder" data-media-metadata-field="folder" list="mediaFolderOptions" placeholder="Без папки">
                            </label>
                            <label>Альтернативний текст
                                <input name="alt_text" data-media-metadata-field="alt_text" placeholder="Короткий опис зображення">
                            </label>
                            <label>Заголовок
                                <input name="title" data-media-metadata-field="title" placeholder="Назва для відображення">
                            </label>
                            <label>Підпис
                                <input name="caption" data-media-metadata-field="caption" placeholder="Підпис під файлом або зображенням">
                            </label>
                            <label>Опис
                                <textarea name="description" data-media-metadata-field="description" rows="5" placeholder="Розширений опис або службова примітка"></textarea>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="button secondary" data-bs-dismiss="modal"><span class="mdi mdi-close" aria-hidden="true"></span><span>Скасувати</span></button>
                    <button type="submit"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span>Зберегти</span></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="mediaPreviewModal" tabindex="-1" aria-labelledby="mediaPreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5" id="mediaPreviewTitle">Попередній перегляд</h2>
                    <p class="meta mb-0" data-media-preview-path></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
            </div>
            <div class="modal-body">
                <div class="media-preview-frame" data-media-preview-body></div>
            </div>
            <div class="modal-footer">
                <a class="button secondary" href="#" target="_blank" rel="noopener" data-media-preview-open>
                    <span class="mdi mdi-open-in-new" aria-hidden="true"></span><span>Відкрити файл</span>
                </a>
                <button type="button" class="button secondary" data-bs-dismiss="modal">
                    <span class="mdi mdi-close" aria-hidden="true"></span><span>Закрити</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= url('/assets/admin-media.js') ?>"></script>
