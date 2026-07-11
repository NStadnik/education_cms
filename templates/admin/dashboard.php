<div class="toolbar"><h1><?= e($title) ?></h1></div>
<div class="grid grid-3">
    <?php if (array_key_exists('pages', $stats)): ?>
    <div class="card metric">
        <div><span>Сторінки</span><strong><?= e((string) $stats['pages']) ?></strong></div>
        <span class="mdi mdi-file-document-edit-outline metric-icon" aria-hidden="true"></span>
    </div>
    <?php endif; ?>
    <?php if (array_key_exists('news', $stats)): ?>
    <div class="card metric">
        <div><span>Новини</span><strong><?= e((string) $stats['news']) ?></strong></div>
        <span class="mdi mdi-newspaper-variant-outline metric-icon" aria-hidden="true"></span>
    </div>
    <?php endif; ?>
    <?php if (array_key_exists('media', $stats)): ?>
    <div class="card metric">
        <div><span>Медіафайли</span><strong><?= e((string) $stats['media']) ?></strong></div>
        <span class="mdi mdi-image-multiple-outline metric-icon" aria-hidden="true"></span>
    </div>
    <?php endif; ?>
</div>
<?php if ($stats === []): ?>
    <div class="empty-state"><span class="mdi mdi-shield-account-outline" aria-hidden="true"></span><h2>Немає доступних розділів</h2><p>Зверніться до адміністратора, щоб отримати потрібні права.</p></div>
<?php endif; ?>
