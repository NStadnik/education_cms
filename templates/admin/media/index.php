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

<div class="metrics">
    <div class="metric"><div><span>Усього</span><strong><?= e((string) $stats['total']) ?></strong></div><span class="mdi mdi-folder-multiple-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Зображень</span><strong><?= e((string) $stats['images']) ?></strong></div><span class="mdi mdi-image-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Вільні</span><strong><?= e((string) $stats['unused']) ?></strong></div><span class="mdi mdi-trash-can-outline metric-icon" aria-hidden="true"></span></div>
</div>

<section class="card admin-form-card">
    <div class="form-section-head">
        <div>
            <h2>Завантажити файл</h2>
            <p class="meta">Підтримуються PDF, Word, Excel і зображення JPG, PNG, WEBP.</p>
        </div>
    </div>
    <form class="form-grid wide" method="post" action="<?= url('/admin/media/upload') ?>" enctype="multipart/form-data" data-no-ajax>
        <?= \App\Core\Csrf::field() ?>
        <label>Файл<input type="file" name="file" required></label>
        <div class="form-actions">
            <button type="submit"><span class="mdi mdi-upload-outline" aria-hidden="true"></span><span>Завантажити</span></button>
        </div>
    </form>
</section>

<div class="list-panel" data-infinite-list data-list-url="<?= url('/admin/media') ?>" data-list-target="#mediaRows" data-list-offset="<?= e((string) count($items)) ?>" data-list-limit="<?= e((string) $limit) ?>" data-list-has-more="<?= count($items) < $total ? '1' : '0' ?>">
    <div class="list-tools">
        <input data-filter-input type="search" value="<?= e($query ?? '') ?>" placeholder="Пошук файлів" aria-label="Пошук медіафайлів">
        <span class="meta"><span data-filter-count><?= e((string) $total) ?></span> файлів · <?= e($stats['size']) ?></span>
    </div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>Файл</th><th>Тип</th><th>Розмір</th><th>Оновлено</th><th>Використання</th><th></th></tr></thead>
            <tbody id="mediaRows"><?= $this->partial('admin/media/rows', ['items' => $items]) ?></tbody>
        </table>
    </div>
    <div class="empty-state <?= $items ? 'd-none' : '' ?>" data-list-empty>Файли не знайдені.</div>
    <div class="list-sentinel" data-list-sentinel></div>
    <p class="meta list-status" data-list-status><?= count($items) < $total ? 'Прокрутіть нижче, щоб завантажити ще.' : 'Усі файли завантажено.' ?></p>
</div>
