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
<div class="page-head optimizer-page-head">
    <div class="optimizer-page-copy">
        <p class="eyebrow">Сервісні інструменти</p>
        <h1>Оптимізатор</h1>
        <p class="page-subtitle">Керуйте кешем і режимом діагностики, а також перевіряйте порядок у медіатеці з одного екрана.</p>
    </div>
    <button class="button secondary optimizer-page-cta" type="button" data-optimizer-open-media>
        <span class="mdi mdi-folder-search-outline" aria-hidden="true"></span>
        <span>Перевірити медіафайли</span>
    </button>
</div>

<?php if (!empty($error)): ?>
    <div class="alert"><?= e((string) $error) ?></div>
<?php endif; ?>
<?php if (!empty($applied)): ?>
    <div class="alert alert-success">Оновлено віртуальні папки для файлів: <?= e((string) $applied) ?>.</div>
<?php endif; ?>
<?php if (!empty($cacheChecked)): ?>
    <div class="optimizer-service-notice is-success" role="status" data-optimizer-service-message>
        <span class="mdi <?= !empty($cacheCleared) ? 'mdi-check-circle-outline' : 'mdi-information-outline' ?>" aria-hidden="true"></span>
        <?php if (!empty($cacheCleared)): ?>
            <div><strong>Кеш сайту очищено</strong><span>Видалено файлів: <?= e((string) $cacheCleared) ?>. Нові превʼю створяться автоматично.</span></div>
        <?php else: ?>
            <div><strong>Кеш уже порожній</strong><span>Файлів для видалення не знайдено. Додаткових дій не потрібно.</span></div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if (($debugChanged ?? '') === 'enabled'): ?>
    <div class="optimizer-service-notice is-warning" role="status" data-optimizer-service-message>
        <span class="mdi mdi-alert-outline" aria-hidden="true"></span>
        <div><strong>Debug режим увімкнено</strong><span>Технічні помилки можуть відображатися відвідувачам. Вимкніть режим після завершення діагностики.</span></div>
    </div>
<?php elseif (($debugChanged ?? '') === 'disabled'): ?>
    <div class="optimizer-service-notice is-success" role="status" data-optimizer-service-message>
        <span class="mdi mdi-shield-check-outline" aria-hidden="true"></span>
        <div><strong>Debug режим вимкнено</strong><span>Технічні повідомлення більше не відображатимуться відвідувачам.</span></div>
    </div>
<?php endif; ?>

<div class="optimizer-section-head">
    <div>
        <span class="optimizer-section-kicker">Стан системи</span>
        <h2>Швидкі дії</h2>
    </div>
    <p>Стан оновлюється автоматично після виконання дії.</p>
</div>

<div class="optimizer-service-grid mb-4" aria-label="Сервісні інструменти">
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
            <div class="optimizer-path-row" title="Директорія кешу">
                <span class="mdi mdi-folder-outline" aria-hidden="true"></span>
                <span>Директорія</span>
                <code><?= e((string) ($cacheInfo['path'] ?? 'storage/cache')) ?></code>
            </div>
            <form class="optimizer-action-row" method="post" action="<?= url('/admin/optimizer/cache/clear') ?>" data-optimizer-service-action data-optimizer-action="cache" data-pending-message="Очищаємо кеш…" data-pending-button-label="Очищаємо кеш…" data-optimizer-confirm="Очистити кеш сайту? Будуть видалені <?= e((string) ($cacheInfo['files'] ?? 0)) ?> кешованих файлів (<?= e((string) ($cacheInfo['size'] ?? '0 Б')) ?>). Нові превʼю створяться автоматично.">
                <?= \App\Core\Csrf::field() ?>
                <button class="button secondary optimizer-danger-action" type="submit" <?= $cacheDisabled ? 'disabled' : '' ?>>
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
            <div class="optimizer-path-row" title="Файл журналу помилок">
                <span class="mdi mdi-file-document-outline" aria-hidden="true"></span>
                <span>Журнал</span>
                <code><?= e((string) ($debugInfo['log_path'] ?? 'storage/debug.log')) ?></code>
            </div>
            <?php if ($debugEnabled): ?>
                <p class="optimizer-note is-danger"><span class="mdi mdi-alert-outline" aria-hidden="true"></span><span>Debug краще вимикати після діагностики, щоб технічні помилки не показувалися відвідувачам.</span></p>
            <?php endif; ?>
            <form class="optimizer-action-row" method="post" action="<?= url('/admin/optimizer/debug/toggle') ?>" data-optimizer-service-action data-optimizer-action="debug" data-pending-message="<?= $debugEnabled ? 'Вимикаємо debug…' : 'Вмикаємо debug…' ?>" data-pending-button-label="<?= $debugEnabled ? 'Вимикаємо debug…' : 'Вмикаємо debug…' ?>" data-optimizer-confirm="<?= $debugEnabled ? 'Вимкнути debug режим? Технічні повідомлення більше не відображатимуться відвідувачам.' : 'Увімкнути debug режим? Технічні помилки можуть відображатися відвідувачам, доки режим не буде вимкнено.' ?>">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="enabled" value="<?= $debugEnabled ? '0' : '1' ?>">
                <button type="submit" class="<?= $debugEnabled ? 'button secondary' : 'button optimizer-warning-action' ?>" <?= $debugDisabled ? 'disabled' : '' ?>>
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

<div class="optimizer-section-head optimizer-media-section-head">
    <div>
        <span class="optimizer-section-kicker">Медіатека</span>
        <h2>Порядок у медіафайлах</h2>
        <p>Знайдіть файли з неактуальними віртуальними папками та перегляньте зміни до їх застосування.</p>
    </div>
</div>

<div class="optimizer-media-launch <?= $mediaTabActive ? 'is-active' : '' ?>" data-optimizer-media-launch>
    <div class="optimizer-media-launch-icon"><span class="mdi mdi-folder-search-outline" aria-hidden="true"></span></div>
    <div>
        <strong><?= $canManageMedia ? 'Готово до перевірки' : 'Потрібен доступ до медіатеки' ?></strong>
        <span><?= $canManageMedia ? 'Аналіз нічого не змінює — ви спочатку побачите повний список запропонованих змін.' : 'Зверніться до адміністратора, щоб отримати право керування медіафайлами.' ?></span>
    </div>
    <button class="button" type="button" data-optimizer-tab="media" aria-controls="optimizer-media-panel" aria-expanded="<?= $mediaTabActive ? 'true' : 'false' ?>" <?= $canManageMedia ? '' : 'disabled' ?>>
        <span class="mdi <?= $mediaTabActive ? 'mdi-refresh' : 'mdi-magnify' ?>" aria-hidden="true"></span>
        <span data-optimizer-media-button-label><?= $mediaTabActive ? 'Оновити аналіз' : 'Запустити аналіз' ?></span>
    </button>
</div>

<section id="optimizer-media-panel" class="optimizer-tab-panel" data-optimizer-tab-panel="media" data-load-url="<?= url('/admin/optimizer/media-folders') ?>" data-loaded="0" <?= $mediaTabActive ? '' : 'hidden' ?> aria-live="polite">
    <div class="list-panel optimizer-analysis-placeholder">
        <div class="empty-state optimizer-loading-state" data-optimizer-tab-status>
            <span class="optimizer-loader" aria-hidden="true"></span>
            <strong>Аналізуємо медіафайли</strong>
            <span>Це може зайняти кілька секунд.</span>
        </div>
    </div>
</section>
