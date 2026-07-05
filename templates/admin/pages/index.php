<?php
    $published = 0;
    foreach ($items as $item) {
        if (($item['status'] ?? '') === 'published') {
            $published++;
        }
    }
    $drafts = count($items) - $published;
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Контент сайту</p>
        <h1>Сторінки</h1>
        <p class="page-subtitle">Керуйте структурою меню, публікацією та порядком сторінок.</p>
    </div>
    <a class="button" href="<?= url('/admin/pages/edit') ?>">Додати сторінку</a>
</div>

<div class="metrics">
    <div class="metric"><span>Усього</span><strong><?= e((string) count($items)) ?></strong></div>
    <div class="metric"><span>Опубліковано</span><strong><?= e((string) $published) ?></strong></div>
    <div class="metric"><span>Чернетки</span><strong><?= e((string) $drafts) ?></strong></div>
</div>

<div class="list-panel" data-filter-list>
    <div class="list-tools">
        <input data-filter-input type="search" placeholder="Пошук за назвою або slug" aria-label="Пошук сторінок">
        <span class="meta"><span data-filter-count><?= e((string) count($items)) ?></span> записів</span>
    </div>
    <div class="table-scroll">
        <table>
            <tr><th>Назва</th><th>Slug</th><th>Статус</th><th>Порядок</th><th></th></tr>
            <?php foreach ($items as $item): ?>
                <?php
                    $searchText = ($item['title'] ?? '') . ' ' . ($item['slug'] ?? '') . ' ' . ($item['status'] ?? '');
                    $searchText = function_exists('mb_strtolower') ? mb_strtolower($searchText) : strtolower($searchText);
                ?>
                <tr data-filter-row data-filter-text="<?= e($searchText) ?>">
                    <td><strong><?= e($item['title']) ?></strong><br><span class="meta"><?= e($item['excerpt'] ?? '') ?></span></td>
                    <td><code><?= e($item['slug']) ?></code></td>
                    <td><span class="status <?= ($item['status'] ?? '') === 'published' ? 'ok' : 'warn' ?>"><?= e($item['status']) ?></span></td>
                    <td><?= e((string) ($item['sort_order'] ?? 0)) ?></td>
                    <td><a class="button secondary compact" href="<?= url('/admin/pages/edit?id=' . $item['id']) ?>">Редагувати</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php if (!$items): ?><div class="empty-state">Сторінки ще не додані.</div><?php endif; ?>
</div>
