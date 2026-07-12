<?php
$formatDate = static function ($value): string {
    $timestamp = strtotime((string) $value);
    return $timestamp ? date('d.m.Y H:i', $timestamp) : (string) $value;
};
$statusLabels = ['published' => 'Опубліковано', 'draft' => 'Чернетки', 'pending_review' => 'На модерації', 'changes_requested' => 'На доопрацюванні'];
$actionLabels = ['save' => 'Збережено', 'delete' => 'Видалено', 'upload' => 'Завантажено', 'publish' => 'Опубліковано', 'unpublish' => 'Знято з публікації', 'submit' => 'Подано на перевірку', 'request_changes' => 'Повернено на доопрацювання', 'import' => 'Імпортовано', 'metadata' => 'Оновлено метадані'];
$entityLabels = ['page' => 'сторінку', 'pages' => 'сторінки', 'news' => 'новину', 'media' => 'медіафайл', 'form' => 'форму', 'user' => 'користувача', 'system' => 'систему'];
?>
<div class="toolbar dashboard-toolbar"><div><h1><?= e($title) ?></h1><p class="meta">Оперативний стан контенту та останні події сайту.</p></div></div>
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
<?php if ($contentStatuses !== []): ?>
<section class="dashboard-section">
    <div class="dashboard-section-head"><div><p class="eyebrow">Робочий процес</p><h2>Стан контенту</h2></div></div>
    <div class="dashboard-status-grid">
        <?php foreach ($contentStatuses as $type => $statuses): ?>
        <article class="card dashboard-status-card">
            <div class="dashboard-card-title"><span class="mdi <?= $type === 'pages' ? 'mdi-file-document-edit-outline' : 'mdi-newspaper-variant-outline' ?>" aria-hidden="true"></span><div><strong><?= $type === 'pages' ? 'Сторінки' : 'Новини' ?></strong><span><?= e((string) array_sum($statuses)) ?> усього</span></div></div>
            <div class="dashboard-status-list">
                <?php foreach ($statuses as $status => $count): ?>
                <a href="<?= url('/admin/' . $type . '?status=' . rawurlencode((string) $status)) ?>"><span><?= e($statusLabels[$status] ?? $status) ?></span><strong><?= e((string) $count) ?></strong></a>
                <?php endforeach; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($newsPublicationTrend):
    $trendTotal = array_sum(array_column($newsPublicationTrend, 'count'));
    $trendMax = max(1, ...array_column($newsPublicationTrend, 'count'));
    $trendPeak = $newsPublicationTrend[0];
    foreach ($newsPublicationTrend as $point) {
        if ($point['count'] > $trendPeak['count']) $trendPeak = $point;
    }
?>
<section class="dashboard-section">
    <div class="dashboard-section-head" data-news-trend data-endpoint="<?= url('/admin/dashboard/news-trend') ?>"><div><p class="eyebrow" data-trend-label><?= e($newsTrendLabel) ?></p><h2>Тенденція публікацій новин</h2><div class="dashboard-chart-legend" data-trend-legend><span><i></i>Поточний період</span></div></div><div class="dashboard-chart-actions"><div class="dashboard-chart-toolbar"><nav class="dashboard-chart-switch" aria-label="Період графіка"><button type="button" class="<?= $newsTrendPeriod === '30_days' ? 'is-active' : '' ?>" data-trend-period="30_days">30 днів</button><button type="button" class="<?= $newsTrendPeriod === 'academic_year' ? 'is-active' : '' ?>" data-trend-period="academic_year">Навчальний рік</button></nav><div class="dashboard-chart-navigation" aria-label="Навігація періодами"><button type="button" data-trend-previous title="Попередній період"><span class="mdi mdi-chevron-left"></span></button><button type="button" data-trend-next title="Наступний період" disabled><span class="mdi mdi-chevron-right"></span></button></div></div><label class="dashboard-chart-compare"><input type="checkbox" data-trend-compare><span class="dashboard-compare-toggle" aria-hidden="true"></span><span><strong>Порівняти</strong><small>із попереднім періодом</small></span></label><div class="dashboard-chart-summary" data-trend-summary><span><strong><?= e((string) $trendTotal) ?></strong> публікацій</span><span>Пік: <strong><?= e((string) $trendPeak['count']) ?></strong> · <?= e($trendPeak['label']) ?></span></div></div></div>
    <div class="card dashboard-chart" data-trend-chart role="img" aria-label="Графік кількості опублікованих новин: <?= e($newsTrendLabel) ?>">
        <div class="dashboard-chart-grid" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
        <div class="dashboard-chart-bars" style="--chart-points: <?= e((string) count($newsPublicationTrend)) ?>">
            <?php foreach ($newsPublicationTrend as $index => $point): $height = ($point['count'] / $trendMax) * 100; ?>
            <div class="dashboard-chart-point" title="<?= e($point['label'] . ': ' . $point['count']) ?>">
                <span class="dashboard-chart-value"><?= $point['count'] ? e((string) $point['count']) : '' ?></span>
                <span class="dashboard-chart-bar<?= $point['count'] ? ' has-value' : '' ?>" style="--bar-height: <?= e(number_format($height, 2, '.', '')) ?>%"></span>
                <time datetime="<?= e($point['date']) ?>"><?= $newsTrendPeriod === 'academic_year' || $index % 5 === 0 || $index === 29 ? e($point['label']) : '' ?></time>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($visibility['forms']): ?>
<section class="dashboard-section">
    <div class="dashboard-section-head"><div><p class="eyebrow">Збір даних</p><h2>Форми та відповіді</h2></div><a class="button secondary compact" href="<?= url('/admin/forms/submissions') ?>">Усі відповіді</a></div>
    <div class="metrics dashboard-form-metrics">
        <div class="metric"><div><span>Активні форми</span><strong><?= e((string) ($formsStats['published'] ?? 0)) ?></strong></div><span class="mdi mdi-form-select metric-icon"></span></div>
        <div class="metric"><div><span>Нові відповіді</span><strong><?= e((string) ($formsStats['new_submissions'] ?? 0)) ?></strong></div><span class="mdi mdi-inbox-arrow-down-outline metric-icon"></span></div>
        <div class="metric"><div><span>За останні 7 днів</span><strong><?= e((string) ($formsStats['recent_submissions'] ?? 0)) ?></strong></div><span class="mdi mdi-calendar-week-outline metric-icon"></span></div>
        <div class="metric"><div><span>Всього відповідей</span><strong><?= e((string) ($formsStats['submissions'] ?? 0)) ?></strong></div><span class="mdi mdi-message-text-outline metric-icon"></span></div>
    </div>
    <?php if ($recentSubmissions): ?><div class="card dashboard-feed"><h3>Останні відповіді</h3><?php foreach ($recentSubmissions as $item): ?><a class="dashboard-feed-row" href="<?= url('/admin/forms/submissions?form_id=' . (int) $item['form_id']) ?>"><span class="dashboard-feed-icon mdi mdi-email-outline"></span><span><strong><?= e($item['form_title']) ?></strong><small><?= e($item['submitter_email'] ?: 'Контакт не вказано') ?></small></span><time><?= e($formatDate($item['created_at'])) ?></time></a><?php endforeach; ?></div><?php endif; ?>
</section>
<?php endif; ?>

<?php if ($recentActivity): ?>
<section class="dashboard-section">
    <div class="dashboard-section-head"><div><p class="eyebrow">Журнал</p><h2>Остання активність</h2></div></div>
    <div class="card dashboard-feed"><?php foreach ($recentActivity as $item): ?><div class="dashboard-feed-row"><span class="dashboard-feed-icon mdi mdi-history"></span><span><strong><?= e($item['user_name']) ?> · <?= e($actionLabels[$item['action']] ?? $item['action']) ?> <?= e($entityLabels[$item['entity']] ?? $item['entity']) ?></strong><small><?= $item['entity_id'] ? 'ID ' . e((string) $item['entity_id']) : e((string) $item['details']) ?></small></span><time><?= e($formatDate($item['created_at'])) ?></time></div><?php endforeach; ?></div>
</section>
<?php endif; ?>
<?php if ($stats === [] && !$visibility['forms'] && !$visibility['activity']): ?>
    <div class="empty-state"><span class="mdi mdi-shield-account-outline" aria-hidden="true"></span><h2>Немає доступних розділів</h2><p>Зверніться до адміністратора, щоб отримати потрібні права.</p></div>
<?php endif; ?>
