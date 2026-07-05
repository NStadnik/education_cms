<div class="page-head">
    <div>
        <p class="eyebrow">Відкритість</p>
        <h1>Публічна інформація</h1>
        <p class="page-subtitle">Чекліст розділів і документи, які публікуються на сайті.</p>
    </div>
    <a class="button" href="<?= url('/admin/public-info/sections/edit') ?>">Додати розділ</a>
</div>

<div class="metrics">
    <div class="metric"><span>Розділів</span><strong><?= e((string) $stats['total']) ?></strong></div>
    <div class="metric"><span>Заповнено</span><strong><?= e((string) $stats['filled']) ?></strong></div>
    <div class="metric"><span>Обов'язкові</span><strong><?= e((string) $stats['required']) ?></strong></div>
</div>

<div class="list-panel" data-infinite-list data-list-url="<?= url('/admin/public-info') ?>" data-list-target="#publicInfoAccordion" data-list-offset="<?= e((string) count($sections)) ?>" data-list-limit="<?= e((string) $limit) ?>" data-list-has-more="<?= count($sections) < $total ? '1' : '0' ?>">
    <div class="list-tools">
        <input data-filter-input type="search" placeholder="Пошук розділів або документів" aria-label="Пошук публічної інформації">
        <span class="meta"><span data-filter-count><?= e((string) $total) ?></span> розділів</span>
    </div>
    <div class="accordion admin-accordion" id="publicInfoAccordion">
        <?= $this->partial('admin/public-info/rows', ['sections' => $sections, 'documents' => $documents]) ?>
    </div>
    <div class="empty-state <?= $sections ? 'd-none' : '' ?>" data-list-empty>Розділи публічної інформації не знайдені.</div>
    <div class="list-sentinel" data-list-sentinel></div>
    <p class="meta list-status" data-list-status><?= count($sections) < $total ? 'Прокрутіть нижче, щоб завантажити ще.' : 'Усі записи завантажено.' ?></p>
</div>
