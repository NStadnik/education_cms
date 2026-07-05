<div class="page-head">
    <div>
        <p class="eyebrow">Файли та рішення</p>
        <h1>Документи</h1>
        <p class="page-subtitle">Завантажуйте документи, прив'язуйте їх до публічної інформації та контролюйте статус.</p>
    </div>
    <a class="button" href="<?= url('/admin/documents/edit') ?>">
        <span class="mdi mdi-plus" aria-hidden="true"></span>
        <span>Додати документ</span>
    </a>
</div>

<div class="metrics">
    <div class="metric"><div><span>Усього</span><strong data-stat="total"><?= e((string) $stats['total']) ?></strong></div><span class="mdi mdi-file-cabinet metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Опубліковано</span><strong data-stat="published"><?= e((string) $stats['published']) ?></strong></div><span class="mdi mdi-check-circle-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>У публічній інформації</span><strong data-stat="linked"><?= e((string) $stats['linked']) ?></strong></div><span class="mdi mdi-link-variant metric-icon" aria-hidden="true"></span></div>
</div>

<form method="post" action="<?= url('/admin/documents/bulk') ?>" data-no-ajax>
<?= \App\Core\Csrf::field() ?>
<div class="list-panel" data-infinite-list data-list-url="<?= url('/admin/documents') ?>" data-list-target="#documentsRows" data-list-offset="<?= e((string) count($items)) ?>" data-list-limit="<?= e((string) $limit) ?>" data-list-has-more="<?= count($items) < $total ? '1' : '0' ?>">
    <div class="list-tools">
        <input data-filter-input type="search" placeholder="Пошук документів" aria-label="Пошук документів">
        <div class="bulk-actions">
            <select name="bulk_action" aria-label="Групова дія">
                <option value="">Групова дія</option>
                <option value="publish">Опублікувати</option>
                <option value="draft">У чернетку</option>
                <option value="delete">Видалити</option>
            </select>
            <button class="button secondary compact" type="submit"><span class="mdi mdi-check" aria-hidden="true"></span><span>Застосувати</span></button>
            <span class="meta"><span data-filter-count><?= e((string) $total) ?></span> записів</span>
        </div>
    </div>
    <div class="table-scroll">
        <table>
            <thead><tr><th><input type="checkbox" data-bulk-check-all aria-label="Вибрати всі"></th><th>Назва</th><th>Категорія</th><th>Публічна інформація</th><th>Статус</th><th>Дата</th><th>Файл</th><th></th></tr></thead>
            <tbody id="documentsRows"><?= $this->partial('admin/documents/rows', ['items' => $items]) ?></tbody>
        </table>
    </div>
    <div class="empty-state <?= $items ? 'd-none' : '' ?>" data-list-empty>Документи не знайдені.</div>
    <div class="list-sentinel" data-list-sentinel></div>
    <p class="meta list-status" data-list-status><?= count($items) < $total ? 'Прокрутіть нижче, щоб завантажити ще.' : 'Усі записи завантажено.' ?></p>
</div>
</form>
