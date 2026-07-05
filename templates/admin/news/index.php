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
        <p class="eyebrow">Оголошення</p>
        <h1>Новини</h1>
        <p class="page-subtitle">Готуйте й публікуйте новини закладу з датою виходу.</p>
    </div>
    <a class="button" href="<?= url('/admin/news/edit') ?>">Додати новину</a>
</div>

<div class="metrics">
    <div class="metric"><span>Усього</span><strong><?= e((string) count($items)) ?></strong></div>
    <div class="metric"><span>Опубліковано</span><strong><?= e((string) $published) ?></strong></div>
    <div class="metric"><span>Чернетки</span><strong><?= e((string) $drafts) ?></strong></div>
</div>

<div class="list-panel" data-filter-list>
    <div class="list-tools">
        <input data-filter-input type="search" placeholder="Пошук новин" aria-label="Пошук новин">
        <span class="meta"><span data-filter-count><?= e((string) count($items)) ?></span> записів</span>
    </div>
    <div class="table-scroll">
        <table>
            <tr><th>Назва</th><th>Статус</th><th>Дата публікації</th><th>Оновлено</th><th></th></tr>
            <?php foreach ($items as $item): ?>
                <?php
                    $searchText = ($item['title'] ?? '') . ' ' . ($item['body'] ?? '') . ' ' . ($item['status'] ?? '');
                    $searchText = function_exists('mb_strtolower') ? mb_strtolower($searchText) : strtolower($searchText);
                ?>
                <tr data-filter-row data-filter-text="<?= e($searchText) ?>">
                    <td><strong><?= e($item['title']) ?></strong><br><span class="meta"><?= e(excerpt($item['body'] ?? '', 100)) ?></span></td>
                    <td><span class="status <?= ($item['status'] ?? '') === 'published' ? 'ok' : 'warn' ?>"><?= e($item['status']) ?></span></td>
                    <td><?= e($item['published_at'] ?? '') ?></td>
                    <td><?= e($item['updated_at'] ?? '') ?></td>
                    <td><a class="button secondary compact" href="<?= url('/admin/news/edit?id=' . $item['id']) ?>">Редагувати</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php if (!$items): ?><div class="empty-state">Новини ще не додані.</div><?php endif; ?>
</div>
