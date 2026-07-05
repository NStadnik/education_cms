<?php
    $published = 0;
    $linked = 0;
    foreach ($items as $item) {
        if (($item['status'] ?? '') === 'published') {
            $published++;
        }
        if (!empty($item['public_info_section_id'])) {
            $linked++;
        }
    }
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Файли та рішення</p>
        <h1>Документи</h1>
        <p class="page-subtitle">Завантажуйте документи, прив'язуйте їх до публічної інформації та контролюйте статус.</p>
    </div>
</div>

<div class="metrics">
    <div class="metric"><span>Усього</span><strong><?= e((string) count($items)) ?></strong></div>
    <div class="metric"><span>Опубліковано</span><strong><?= e((string) $published) ?></strong></div>
    <div class="metric"><span>У публічній інформації</span><strong><?= e((string) $linked) ?></strong></div>
</div>

<div class="card admin-form-card">
    <form class="form-grid" method="post" action="<?= url('/admin/documents/save') ?>" enctype="multipart/form-data">
        <?= \App\Core\Csrf::field() ?>
        <label>Назва<input name="title" required></label>
        <div class="grid grid-3">
            <label>Категорія<input name="category" value="Загальні документи"></label>
            <label>Статус<select name="status"><option value="published">published</option><option value="draft">draft</option></select></label>
            <label>Дата затвердження<input name="approved_at" placeholder="2026-07-04"></label>
        </div>
        <div class="grid grid-3">
            <label>Розділ публічної інформації
                <select name="public_info_section_id">
                    <option value="">Не прив'язувати</option>
                    <?php foreach ($sections as $section): ?>
                        <option value="<?= e((string) $section['id']) ?>"><?= e($section['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Відповідальний<input name="responsible"></label>
            <label>Дата публікації<input name="published_at" placeholder="2026-07-04"></label>
        </div>
        <label>Опис<textarea name="description"></textarea></label>
        <label>Файл<input type="file" name="file"></label>
        <button type="submit">Додати документ</button>
    </form>
</div>

<div class="list-panel" data-filter-list>
    <div class="list-tools">
        <input data-filter-input type="search" placeholder="Пошук документів" aria-label="Пошук документів">
        <span class="meta"><span data-filter-count><?= e((string) count($items)) ?></span> записів</span>
    </div>
    <div class="table-scroll">
        <table>
            <tr><th>Назва</th><th>Категорія</th><th>Публічна інформація</th><th>Статус</th><th>Дата</th><th>Файл</th></tr>
            <?php foreach ($items as $item): ?>
                <?php
                    $searchText = ($item['title'] ?? '') . ' ' . ($item['category'] ?? '') . ' ' . ($item['description'] ?? '') . ' ' . ($item['status'] ?? '');
                    $searchText = function_exists('mb_strtolower') ? mb_strtolower($searchText) : strtolower($searchText);
                ?>
                <tr data-filter-row data-filter-text="<?= e($searchText) ?>">
                    <td><strong><?= e($item['title']) ?></strong><br><span class="meta"><?= e($item['description'] ?? '') ?></span></td>
                    <td><?= e($item['category']) ?></td>
                    <td><span class="status <?= $item['public_info_section_id'] ? 'ok' : '' ?>"><?= $item['public_info_section_id'] ? 'так' : 'ні' ?></span></td>
                    <td><span class="status <?= ($item['status'] ?? '') === 'published' ? 'ok' : 'warn' ?>"><?= e($item['status']) ?></span></td>
                    <td><?= e($item['approved_at'] ?? $item['published_at'] ?? $item['created_at'] ?? '') ?></td>
                    <td><?php if ($item['file_path']): ?><a href="<?= url('/uploads/' . $item['file_path']) ?>">відкрити</a><?php else: ?><span class="meta">немає</span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php if (!$items): ?><div class="empty-state">Документи ще не додані.</div><?php endif; ?>
</div>
