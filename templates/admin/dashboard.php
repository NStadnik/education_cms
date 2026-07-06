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
        <div><span>Медіафайли</span><strong><?= e((string) $stats['media']) ?></strong></div>
        <span class="mdi mdi-image-multiple-outline metric-icon" aria-hidden="true"></span>
    </div>
</div>
