<div class="toolbar"><h1><?= e($title) ?></h1></div>
<div class="grid grid-3">
    <div class="card"><h3>Сторінки</h3><strong><?= e((string) $stats['pages']) ?></strong></div>
    <div class="card"><h3>Новини</h3><strong><?= e((string) $stats['news']) ?></strong></div>
    <div class="card"><h3>Документи</h3><strong><?= e((string) $stats['documents']) ?></strong></div>
</div>
<div class="card" style="margin-top:16px">
    <h2>Публічна інформація</h2>
    <p>Заповнено <?= e((string) $stats['publicFilled']) ?> з <?= e((string) $stats['publicTotal']) ?> обов'язкових позицій.</p>
    <a class="button" href="<?= url('/admin/public-info') ?>">Оновити розділи</a>
</div>
