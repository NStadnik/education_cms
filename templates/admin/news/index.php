<div class="page-head">
    <div>
        <p class="eyebrow">Оголошення</p>
        <h1>Новини</h1>
        <p class="page-subtitle">Готуйте й публікуйте новини закладу з датою виходу.</p>
    </div>
    <a class="button" href="<?= url('/admin/news/edit') ?>">Додати новину</a>
</div>

<div class="metrics">
    <div class="metric"><span>Усього</span><strong><?= e((string) $stats['total']) ?></strong></div>
    <div class="metric"><span>Опубліковано</span><strong><?= e((string) $stats['published']) ?></strong></div>
    <div class="metric"><span>Чернетки</span><strong><?= e((string) $stats['drafts']) ?></strong></div>
</div>

<div class="list-panel" data-infinite-list data-list-url="<?= url('/admin/news') ?>" data-list-target="#newsRows" data-list-offset="<?= e((string) count($items)) ?>" data-list-limit="<?= e((string) $limit) ?>" data-list-has-more="<?= count($items) < $total ? '1' : '0' ?>">
    <div class="list-tools">
        <input data-filter-input type="search" placeholder="Пошук новин" aria-label="Пошук новин">
        <span class="meta"><span data-filter-count><?= e((string) $total) ?></span> записів</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>Назва</th><th>Статус</th><th>Дата публікації</th><th>Оновлено</th><th></th></tr></thead>
            <tbody id="newsRows"><?= $this->partial('admin/news/rows', ['items' => $items]) ?></tbody>
        </table>
    </div>
    <div class="empty-state <?= $items ? 'd-none' : '' ?>" data-list-empty>Новини не знайдені.</div>
    <div class="list-sentinel" data-list-sentinel></div>
    <p class="meta list-status" data-list-status><?= count($items) < $total ? 'Прокрутіть нижче, щоб завантажити ще.' : 'Усі записи завантажено.' ?></p>
</div>
