<div class="page-head">
    <div>
        <p class="eyebrow">Контент сайту</p>
        <h1>Сторінки</h1>
        <p class="page-subtitle">Керуйте структурою меню, публікацією та порядком сторінок.</p>
    </div>
    <a class="button" href="<?= url('/admin/pages/edit') ?>">
        <span class="mdi mdi-plus" aria-hidden="true"></span>
        <span>Додати сторінку</span>
    </a>
</div>

<div class="metrics">
    <div class="metric"><div><span>Усього</span><strong data-stat="total"><?= e((string) $stats['total']) ?></strong></div><span class="mdi mdi-file-document-multiple-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Опубліковано</span><strong data-stat="published"><?= e((string) $stats['published']) ?></strong></div><span class="mdi mdi-check-circle-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Чернетки</span><strong data-stat="drafts"><?= e((string) $stats['drafts']) ?></strong></div><span class="mdi mdi-pencil-outline metric-icon" aria-hidden="true"></span></div>
</div>

<form method="post" action="<?= url('/admin/pages/bulk') ?>" data-no-ajax>
<?= \App\Core\Csrf::field() ?>
<div class="list-panel" data-infinite-list data-list-url="<?= url('/admin/pages') ?>" data-list-target="#pagesRows" data-list-offset="<?= e((string) count($items)) ?>" data-list-limit="<?= e((string) $limit) ?>" data-list-has-more="<?= count($items) < $total ? '1' : '0' ?>">
    <div class="list-tools">
        <input data-filter-input type="search" placeholder="Пошук за назвою" aria-label="Пошук сторінок">
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
            <thead><tr><th><input type="checkbox" data-bulk-check-all aria-label="Вибрати всі"></th><th>Назва</th><th>Шаблон</th><th>Статус</th><th>Порядок</th><th></th></tr></thead>
            <tbody id="pagesRows"><?= $this->partial('admin/pages/rows', ['items' => $items]) ?></tbody>
        </table>
    </div>
    <div class="empty-state <?= $items ? 'd-none' : '' ?>" data-list-empty>Сторінки не знайдені.</div>
    <div class="list-sentinel" data-list-sentinel></div>
    <p class="meta list-status" data-list-status><?= count($items) < $total ? 'Прокрутіть нижче, щоб завантажити ще.' : 'Усі записи завантажено.' ?></p>
</div>
</form>
