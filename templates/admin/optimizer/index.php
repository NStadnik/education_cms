<?php
    $cacheInfo = is_array($cacheInfo ?? null) ? $cacheInfo : [];
    $debugInfo = is_array($debugInfo ?? null) ? $debugInfo : [];
    $canManageMedia = !empty($canManageMedia);
    $canManageSystem = !empty($canManageSystem);
    $mediaTabActive = !empty($mediaTabActive);
    $debugEnabled = !empty($debugInfo['enabled']);
    $cacheWritable = !empty($cacheInfo['writable']);
    $debugStorageWritable = !empty($debugInfo['storage_writable']);
    $cacheDisabled = !$canManageSystem || !$cacheWritable;
    $debugDisabled = !$canManageSystem || !$debugStorageWritable;
    $cacheStatusClass = !$canManageSystem ? 'is-muted' : ($cacheWritable ? 'is-ok' : 'is-warning');
    $cacheStatusIcon = !$canManageSystem ? 'mdi-lock-outline' : ($cacheWritable ? 'mdi-check-circle-outline' : 'mdi-alert-circle-outline');
    $cacheStatusLabel = !$canManageSystem ? 'Немає доступу' : ($cacheWritable ? 'Готово' : 'Недоступно');
    $debugStatusClass = !$canManageSystem ? 'is-muted' : ($debugEnabled ? 'is-danger' : 'is-muted');
    $debugStatusIcon = !$canManageSystem ? 'mdi-lock-outline' : ($debugEnabled ? 'mdi-alert-circle-outline' : 'mdi-shield-check-outline');
    $debugStatusLabel = !$canManageSystem ? 'Немає доступу' : ($debugEnabled ? 'Активний' : 'Вимкнено');
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Сервісні інструменти</p>
        <h1>Оптимізатор</h1>
        <p class="page-subtitle">Автоматично впорядковує медіафайли у віртуальні папки за категоріями новин, де ці файли використані.</p>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert"><?= e((string) $error) ?></div>
<?php endif; ?>
<?php if (!empty($applied)): ?>
    <div class="alert alert-success">Оновлено віртуальні папки для файлів: <?= e((string) $applied) ?>.</div>
<?php endif; ?>
<?php if (!empty($cacheCleared)): ?>
    <div class="alert alert-success">Кеш очищено. Видалено файлів: <?= e((string) $cacheCleared) ?>.</div>
<?php endif; ?>
<?php if (($debugChanged ?? '') === 'enabled'): ?>
    <div class="alert alert-success">Debug режим увімкнено.</div>
<?php elseif (($debugChanged ?? '') === 'disabled'): ?>
    <div class="alert alert-success">Debug режим вимкнено.</div>
<?php endif; ?>

<div class="optimizer-service-grid mb-4">
    <div class="col-lg-6">
        <section class="card admin-form-card optimizer-service-card h-100">
            <div class="form-section-head optimizer-service-head">
                <div>
                    <div class="optimizer-title-row">
                        <span class="optimizer-icon mdi mdi-cached" aria-hidden="true"></span>
                        <h2>Кеш сайту</h2>
                    </div>
                    <p class="meta">Очищення видаляє згенеровані файли з storage/cache, зокрема превʼю зображень. Потрібні превʼю створяться знову при відкритті сайту.</p>
                </div>
                <span class="optimizer-status-pill <?= $cacheStatusClass ?>">
                    <span class="mdi <?= $cacheStatusIcon ?>" aria-hidden="true"></span>
                    <span><?= $cacheStatusLabel ?></span>
                </span>
            </div>
            <div class="metrics optimizer-metrics">
                <div class="metric"><div><span>Файлів у кеші</span><strong><?= e((string) ($cacheInfo['files'] ?? 0)) ?></strong></div><span class="mdi mdi-file-multiple-outline metric-icon" aria-hidden="true"></span></div>
                <div class="metric"><div><span>Розмір</span><strong><?= e((string) ($cacheInfo['size'] ?? '0 Б')) ?></strong></div><span class="mdi mdi-harddisk metric-icon" aria-hidden="true"></span></div>
            </div>
            <div class="optimizer-path-row">
                <span class="mdi mdi-folder-outline" aria-hidden="true"></span>
                <code><?= e((string) ($cacheInfo['path'] ?? 'storage/cache')) ?></code>
            </div>
            <form class="optimizer-action-row" method="post" action="<?= url('/admin/optimizer/cache/clear') ?>" data-optimizer-service-action data-optimizer-confirm="Очистити кеш сайту? Згенеровані превʼю будуть створені знову при відкритті сайту.">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" <?= $cacheDisabled ? 'disabled' : '' ?>>
                    <span class="mdi mdi-delete-sweep-outline" aria-hidden="true"></span><span>Очистити кеш</span>
                </button>
            </form>
            <?php if (!$canManageSystem): ?>
                <p class="optimizer-note is-warning"><span class="mdi mdi-lock-outline" aria-hidden="true"></span><span>Для очищення кешу потрібен доступ до налаштувань.</span></p>
            <?php elseif (!$cacheWritable): ?>
                <p class="optimizer-note is-warning"><span class="mdi mdi-folder-lock-outline" aria-hidden="true"></span><span>Директорія кешу недоступна для запису.</span></p>
            <?php endif; ?>
        </section>
    </div>
    <div class="col-lg-6">
        <section class="card admin-form-card optimizer-service-card h-100 <?= $debugEnabled ? 'is-debug-on' : '' ?>">
            <div class="form-section-head optimizer-service-head">
                <div>
                    <div class="optimizer-title-row">
                        <span class="optimizer-icon mdi <?= $debugEnabled ? 'mdi-bug-check-outline' : 'mdi-bug-outline' ?>" aria-hidden="true"></span>
                        <h2>Debug режим</h2>
                    </div>
                    <p class="meta">Увімкнений debug показує технічні помилки на екрані та корисний тільки для діагностики.</p>
                </div>
                <span class="optimizer-status-pill <?= $debugStatusClass ?>">
                    <span class="mdi <?= $debugStatusIcon ?>" aria-hidden="true"></span>
                    <span><?= $debugStatusLabel ?></span>
                </span>
            </div>
            <div class="metrics optimizer-metrics">
                <div class="metric"><div><span>Стан</span><strong><?= $debugEnabled ? 'Увімкнено' : 'Вимкнено' ?></strong></div><span class="mdi mdi-power metric-icon" aria-hidden="true"></span></div>
                <div class="metric"><div><span>Маркер</span><strong><?= !empty($debugInfo['marker_exists']) ? 'Є' : 'Немає' ?></strong></div><span class="mdi mdi-file-check-outline metric-icon" aria-hidden="true"></span></div>
            </div>
            <div class="optimizer-path-row">
                <span class="mdi mdi-file-document-outline" aria-hidden="true"></span>
                <code><?= e((string) ($debugInfo['log_path'] ?? 'storage/debug.log')) ?></code>
            </div>
            <?php if ($debugEnabled): ?>
                <p class="optimizer-note is-danger"><span class="mdi mdi-alert-outline" aria-hidden="true"></span><span>Debug краще вимикати після діагностики, щоб технічні помилки не показувалися відвідувачам.</span></p>
            <?php endif; ?>
            <form class="optimizer-action-row" method="post" action="<?= url('/admin/optimizer/debug/toggle') ?>" data-optimizer-service-action data-optimizer-confirm="<?= $debugEnabled ? 'Вимкнути debug режим?' : 'Увімкнути debug режим? Технічні помилки можуть показуватися на екрані.' ?>">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="enabled" value="<?= $debugEnabled ? '0' : '1' ?>">
                <button type="submit" class="<?= $debugEnabled ? 'button secondary' : '' ?>" <?= $debugDisabled ? 'disabled' : '' ?>>
                    <span class="mdi <?= $debugEnabled ? 'mdi-toggle-switch-off-outline' : 'mdi-toggle-switch-outline' ?>" aria-hidden="true"></span>
                    <span><?= $debugEnabled ? 'Вимкнути debug' : 'Увімкнути debug' ?></span>
                </button>
            </form>
            <?php if (!$canManageSystem): ?>
                <p class="optimizer-note is-warning"><span class="mdi mdi-lock-outline" aria-hidden="true"></span><span>Для зміни debug режиму потрібен доступ до налаштувань.</span></p>
            <?php elseif (!$debugStorageWritable): ?>
                <p class="optimizer-note is-warning"><span class="mdi mdi-folder-lock-outline" aria-hidden="true"></span><span>Директорія storage недоступна для запису.</span></p>
            <?php endif; ?>
        </section>
    </div>
</div>

<div class="optimizer-tabs admin-link-picker-tabs mb-3" role="tablist" aria-label="Розділи оптимізатора">
    <button class="button <?= $mediaTabActive ? '' : 'secondary' ?> compact" type="button" data-optimizer-tab="media" aria-selected="<?= $mediaTabActive ? 'true' : 'false' ?>">
        <span class="mdi mdi-folder-sync-outline" aria-hidden="true"></span><span>Медіафайли новин</span>
    </button>
</div>

<section class="optimizer-tab-panel" data-optimizer-tab-panel="media" data-load-url="<?= url('/admin/optimizer/media-folders') ?>" data-loaded="0" <?= $mediaTabActive ? '' : 'hidden' ?>>
    <div class="list-panel">
        <div class="empty-state optimizer-loading-state" data-optimizer-tab-status aria-live="polite">
            <?= $canManageMedia ? 'Відкрийте вкладку, щоб завантажити аналіз медіафайлів.' : 'Для сортування медіафайлів потрібен доступ до медіатеки.' ?>
        </div>
    </div>
</section>
