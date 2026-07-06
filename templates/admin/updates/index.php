<div class="toolbar">
    <div>
        <h1><?= e($title) ?></h1>
        <p class="meta mb-0">Поточна версія: <strong data-update-current-version><?= e($currentVersion) ?></strong></p>
    </div>
    <button class="button secondary" type="button" data-update-check>
        <span class="mdi mdi-refresh" aria-hidden="true"></span>
        <span>Перевірити</span>
    </button>
</div>

<?php if ($message): ?>
    <div class="alert alert-info"><?= e($message) ?></div>
<?php endif; ?>

<div
    class="card admin-form-card"
    data-updates-panel
    data-check-url="<?= e(url('/admin/updates/check')) ?>"
    data-install-url="<?= e(url('/admin/updates/install')) ?>"
>
    <div class="form-section-head">
        <div>
            <h2 data-update-title><?= !empty($recentUpdate) ? 'Оновлення встановлено' : 'Перевірка оновлень' ?></h2>
            <p class="meta mb-0" data-update-subtitle><?= !empty($recentUpdate) ? 'Повторна перевірка GitHub запускається тільки вручну.' : 'Натисніть кнопку перевірки, щоб отримати останній GitHub Release.' ?></p>
        </div>
        <span class="badge text-bg-<?= !empty($recentUpdate) ? 'success' : 'secondary' ?>" data-update-badge><?= !empty($recentUpdate) ? 'Оновлено' : 'Очікує' ?></span>
    </div>

    <div class="alert alert-<?= !empty($recentUpdate) ? 'success' : 'info' ?>" data-update-status>
        <?= !empty($recentUpdate) ? 'Локальне оновлення завершено. Натисніть “Перевірити”, якщо потрібно заново звернутися до GitHub.' : 'Запит до GitHub ще не виконувався.' ?>
    </div>

    <div class="grid grid-3 mb-3">
        <div class="metric">
            <div><span>Встановлено</span><strong data-update-installed><?= e($currentVersion) ?></strong></div>
            <span class="mdi mdi-package-variant-closed metric-icon" aria-hidden="true"></span>
        </div>
        <div class="metric">
            <div><span>Останній реліз</span><strong data-update-latest>—</strong></div>
            <span class="mdi mdi-cloud-download-outline metric-icon" aria-hidden="true"></span>
        </div>
        <div class="metric">
            <div><span>Архів</span><strong data-update-package>—</strong></div>
            <span class="mdi mdi-zip-box-outline metric-icon" aria-hidden="true"></span>
        </div>
    </div>

    <div class="hint-box mb-3 d-none" data-update-zipball-warning>
        У релізі немає окремого архіву <code>education-cms-v*.zip</code>, тому буде використаний стандартний GitHub source zip без checksum.
    </div>

    <div class="hint-box mb-3 d-none" data-update-body></div>

    <div class="form-actions">
        <a class="button secondary d-none" href="#" target="_blank" rel="noopener" data-update-release-link>
            <span class="mdi mdi-open-in-new" aria-hidden="true"></span>
            <span>Відкрити реліз</span>
        </a>
        <button class="button d-none" type="button" data-update-install disabled>
            <span class="mdi mdi-update" aria-hidden="true"></span>
            <span>Встановити оновлення</span>
        </button>
    </div>
</div>

<div class="hint-box">
    Оновлення не перезаписує <code>config/local.php</code>, <code>storage/</code>, завантажені файли та локальні backup-архіви.
</div>

<script src="<?= url('/assets/admin-updates.js') ?>"></script>
