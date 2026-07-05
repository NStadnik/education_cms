<div class="page-head">
    <div>
        <p class="eyebrow">Файли та рішення</p>
        <h1>Документи</h1>
        <p class="page-subtitle">Завантажуйте документи, прив'язуйте їх до публічної інформації та контролюйте статус.</p>
    </div>
</div>

<div class="metrics">
    <div class="metric"><span>Усього</span><strong><?= e((string) $stats['total']) ?></strong></div>
    <div class="metric"><span>Опубліковано</span><strong><?= e((string) $stats['published']) ?></strong></div>
    <div class="metric"><span>У публічній інформації</span><strong><?= e((string) $stats['linked']) ?></strong></div>
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

<div class="list-panel" data-infinite-list data-list-url="<?= url('/admin/documents') ?>" data-list-target="#documentsRows" data-list-offset="<?= e((string) count($items)) ?>" data-list-limit="<?= e((string) $limit) ?>" data-list-has-more="<?= count($items) < $total ? '1' : '0' ?>">
    <div class="list-tools">
        <input data-filter-input type="search" placeholder="Пошук документів" aria-label="Пошук документів">
        <span class="meta"><span data-filter-count><?= e((string) $total) ?></span> записів</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>Назва</th><th>Категорія</th><th>Публічна інформація</th><th>Статус</th><th>Дата</th><th>Файл</th></tr></thead>
            <tbody id="documentsRows"><?= $this->partial('admin/documents/rows', ['items' => $items]) ?></tbody>
        </table>
    </div>
    <div class="empty-state <?= $items ? 'd-none' : '' ?>" data-list-empty>Документи не знайдені.</div>
    <div class="list-sentinel" data-list-sentinel></div>
    <p class="meta list-status" data-list-status><?= count($items) < $total ? 'Прокрутіть нижче, щоб завантажити ще.' : 'Усі записи завантажено.' ?></p>
</div>
