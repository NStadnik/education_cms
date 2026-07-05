<div class="page-head">
    <div>
        <p class="eyebrow">Контент сайту</p>
        <h1>Сторінки</h1>
        <p class="page-subtitle">Керуйте структурою меню, публікацією та порядком сторінок.</p>
    </div>
    <a class="button" href="<?= url('/admin/pages/edit') ?>">Додати сторінку</a>
</div>

<div class="metrics">
    <div class="metric"><span>Усього</span><strong><?= e((string) $stats['total']) ?></strong></div>
    <div class="metric"><span>Опубліковано</span><strong><?= e((string) $stats['published']) ?></strong></div>
    <div class="metric"><span>Чернетки</span><strong><?= e((string) $stats['drafts']) ?></strong></div>
</div>

<div class="list-panel" data-infinite-list data-list-url="<?= url('/admin/pages') ?>" data-list-target="#pagesRows" data-list-offset="<?= e((string) count($items)) ?>" data-list-limit="<?= e((string) $limit) ?>" data-list-has-more="<?= count($items) < $total ? '1' : '0' ?>">
    <div class="list-tools">
        <input data-filter-input type="search" placeholder="Пошук за назвою або slug" aria-label="Пошук сторінок">
        <span class="meta"><span data-filter-count><?= e((string) $total) ?></span> записів</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>Назва</th><th>Slug</th><th>Статус</th><th>Порядок</th><th></th></tr></thead>
            <tbody id="pagesRows"><?= $this->partial('admin/pages/rows', ['items' => $items]) ?></tbody>
        </table>
    </div>
    <div class="empty-state <?= $items ? 'd-none' : '' ?>" data-list-empty>Сторінки не знайдені.</div>
    <div class="list-sentinel" data-list-sentinel></div>
    <p class="meta list-status" data-list-status><?= count($items) < $total ? 'Прокрутіть нижче, щоб завантажити ще.' : 'Усі записи завантажено.' ?></p>
</div>
