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
