<div class="toolbar"><h1><?= e($title) ?></h1></div>
<div class="grid grid-3">
    <div class="card metric">
        <div><span>Сторінки</span><strong><?= e((string) $stats['pages']) ?></strong></div>
        <span class="mdi mdi-file-document-edit-outline metric-icon" aria-hidden="true"></span>
    </div>
    <div class="card metric">
        <div><span>Новини</span><strong><?= e((string) $stats['news']) ?></strong></div>
        <span class="mdi mdi-newspaper-variant-outline metric-icon" aria-hidden="true"></span>
    </div>
    <div class="card metric">
        <div><span>Документи</span><strong><?= e((string) $stats['documents']) ?></strong></div>
        <span class="mdi mdi-file-cabinet metric-icon" aria-hidden="true"></span>
    </div>
</div>
<div class="card" style="margin-top:16px">
    <h2>Публічна інформація</h2>
    <p>Заповнено <?= e((string) $stats['publicFilled']) ?> з <?= e((string) $stats['publicTotal']) ?> обов'язкових позицій.</p>
    <a class="button" href="<?= url('/admin/public-info') ?>">
        <span class="mdi mdi-folder-sync-outline" aria-hidden="true"></span>
        <span>Оновити розділи</span>
    </a>
</div>
