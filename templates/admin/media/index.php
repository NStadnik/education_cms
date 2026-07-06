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
        <div class="form-actions">
            <button type="submit"><span class="mdi mdi-upload-outline" aria-hidden="true"></span><span>Завантажити</span></button>
        </div>
    </form>
</section>

<form id="mediaBulkForm" method="post" action="<?= url('/admin/media/bulk') ?>" data-no-ajax data-list-panel="#mediaListPanel">
    <?= \App\Core\Csrf::field() ?>
</form>
<div id="mediaListPanel" class="list-panel" data-infinite-list data-list-url="<?= url('/admin/media') ?>" data-list-target="#mediaRows" data-list-offset="<?= e((string) count($items)) ?>" data-list-limit="<?= e((string) $limit) ?>" data-list-has-more="<?= count($items) < $total ? '1' : '0' ?>" data-list-empty-label="файли">
    <div class="list-tools">
        <input data-filter-input type="search" value="<?= e($query ?? '') ?>" placeholder="Пошук файлів" aria-label="Пошук медіафайлів">
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
            <thead><tr><th><input type="checkbox" data-bulk-check-all form="mediaBulkForm" aria-label="Вибрати всі"></th><th>Файл</th><th>Тип</th><th>Розмір</th><th>Оновлено</th><th>Використання</th><th></th></tr></thead>
            <tbody id="mediaRows"><?= $this->partial('admin/media/rows', ['items' => $items]) ?></tbody>
        </table>
    </div>
    <div class="empty-state <?= $items ? 'd-none' : '' ?>" data-list-empty>Файли не знайдені.</div>
    <div class="list-sentinel" data-list-sentinel></div>
    <p class="meta list-status" data-list-status><?= count($items) < $total ? 'Прокрутіть нижче, щоб завантажити ще.' : 'Усі файли завантажено.' ?></p>
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
