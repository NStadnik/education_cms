<div class="page-head">
    <div>
        <p class="eyebrow">Відкритість</p>
        <h1>Публічна інформація</h1>
        <p class="page-subtitle">Чекліст розділів і документи, які публікуються на сайті.</p>
    </div>
    <a class="button" href="<?= url('/admin/public-info/sections/edit') ?>">
        <span class="mdi mdi-plus" aria-hidden="true"></span>
        <span>Додати розділ</span>
    </a>
</div>

<div class="metrics">
    <div class="metric"><div><span>Розділів</span><strong data-stat="total"><?= e((string) $stats['total']) ?></strong></div><span class="mdi mdi-folder-multiple-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Заповнено</span><strong data-stat="filled"><?= e((string) $stats['filled']) ?></strong></div><span class="mdi mdi-check-decagram-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Обов'язкові</span><strong data-stat="required"><?= e((string) $stats['required']) ?></strong></div><span class="mdi mdi-alert-circle-outline metric-icon" aria-hidden="true"></span></div>
</div>

<form method="post" action="<?= url('/admin/public-info/bulk') ?>" data-no-ajax>
<?= \App\Core\Csrf::field() ?>
<div class="list-panel" data-infinite-list data-list-url="<?= url('/admin/public-info') ?>" data-list-target="#publicInfoAccordion" data-list-offset="<?= e((string) count($sections)) ?>" data-list-limit="<?= e((string) $limit) ?>" data-list-has-more="<?= count($sections) < $total ? '1' : '0' ?>">
    <div class="list-tools">
        <input data-filter-input type="search" placeholder="Пошук розділів або документів" aria-label="Пошук публічної інформації">
        <div class="bulk-actions">
            <select name="bulk_action" aria-label="Групова дія">
                <option value="">Групова дія</option>
                <option value="publish_documents">Опублікувати документи</option>
                <option value="draft_documents">Документи у чернетку</option>
                <option value="delete_documents">Видалити документи</option>
                <option value="require_sections">Зробити розділи обов'язковими</option>
                <option value="optional_sections">Зробити розділи необов'язковими</option>
                <option value="delete_sections">Видалити порожні розділи</option>
            </select>
            <button class="button secondary compact" type="submit"><span class="mdi mdi-check" aria-hidden="true"></span><span>Застосувати</span></button>
            <span class="meta"><span data-filter-count><?= e((string) $total) ?></span> розділів</span>
        </div>
    </div>
    <div class="accordion admin-accordion" id="publicInfoAccordion">
        <?= $this->partial('admin/public-info/rows', ['sections' => $sections, 'documents' => $documents]) ?>
    </div>
    <div class="empty-state <?= $sections ? 'd-none' : '' ?>" data-list-empty>Розділи публічної інформації не знайдені.</div>
    <div class="list-sentinel" data-list-sentinel></div>
    <p class="meta list-status" data-list-status><?= count($sections) < $total ? 'Прокрутіть нижче, щоб завантажити ще.' : 'Усі записи завантажено.' ?></p>
</div>
</form>
