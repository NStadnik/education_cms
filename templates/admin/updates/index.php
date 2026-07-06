<?php unset($_SESSION['updates_message']); ?>
<div class="toolbar">
    <div>
        <h1><?= e($title) ?></h1>
        <p class="meta mb-0">Поточна версія: <strong><?= e($currentVersion) ?></strong></p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-info"><?= e($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-warning">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="card admin-form-card">
    <?php if ($latest): ?>
        <div class="form-section-head">
            <div>
                <h2>GitHub Release <?= e($latest['tag'] ?: ('v' . $latest['version'])) ?></h2>
                <p class="meta">
                    <?= $latest['published_at'] ? e(date('d.m.Y H:i', strtotime($latest['published_at']))) : 'Дата релізу недоступна' ?>
                    <?php if ($latest['html_url']): ?>
                        · <a href="<?= e($latest['html_url']) ?>" target="_blank" rel="noopener">Відкрити на GitHub</a>
                    <?php endif; ?>
                </p>
            </div>
            <?php if ($latest['has_update']): ?>
                <span class="badge text-bg-primary">Доступне оновлення</span>
            <?php else: ?>
                <span class="badge text-bg-success">Актуально</span>
            <?php endif; ?>
        </div>

        <div class="grid grid-3 mb-3">
            <div class="metric">
                <div><span>Встановлено</span><strong><?= e($latest['current_version']) ?></strong></div>
                <span class="mdi mdi-package-variant-closed metric-icon" aria-hidden="true"></span>
            </div>
            <div class="metric">
                <div><span>Останній реліз</span><strong><?= e($latest['version']) ?></strong></div>
                <span class="mdi mdi-cloud-download-outline metric-icon" aria-hidden="true"></span>
            </div>
            <div class="metric">
                <div><span>Архів</span><strong><?= e($latest['package_name'] ?: 'немає') ?></strong></div>
                <span class="mdi mdi-zip-box-outline metric-icon" aria-hidden="true"></span>
            </div>
        </div>

        <?php if ($latest['body']): ?>
            <div class="hint-box mb-3">
                <?= nl2br(e($latest['body'])) ?>
            </div>
        <?php endif; ?>

        <?php if ($latest['has_update']): ?>
            <?php if ($latest['package_url']): ?>
                <form method="post" action="<?= url('/admin/updates/install') ?>" data-no-ajax>
                    <?= \App\Core\Csrf::field() ?>
                    <button class="button" type="submit">
                        <span class="mdi mdi-update" aria-hidden="true"></span>
                        <span>Встановити оновлення</span>
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-warning mb-0">У релізі немає zip-архіву `education-cms-v*.zip`.</div>
            <?php endif; ?>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state mb-0">
            Не вдалося отримати інформацію про останній GitHub Release.
        </div>
    <?php endif; ?>
</div>

<div class="hint-box">
    Оновлення не перезаписує <code>config/local.php</code>, <code>storage/</code>, завантажені файли та локальні backup-архіви.
</div>
