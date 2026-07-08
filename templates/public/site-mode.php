<?php
    $institutionName = trim((string) ($settings['institution_name'] ?? 'Заклад освіти')) ?: 'Заклад освіти';
    $accent = preg_replace('/[^a-z0-9_-]/i', '', (string) ($modeAccent ?? 'blue')) ?: 'blue';
?>
<section class="site-mode-page site-mode-<?= e($accent) ?>">
    <div class="site-mode-shell">
        <div class="site-mode-brand">
            <span class="site-mode-mark" aria-hidden="true"></span>
            <span><?= e($institutionName) ?></span>
        </div>
        <div class="site-mode-panel">
            <p class="site-mode-kicker"><?= e($modeLabel ?? 'Сайт тимчасово недоступний') ?></p>
            <h1><?= e($modeTitle ?? 'Сайт тимчасово недоступний') ?></h1>
            <p><?= e($modeMessage ?? 'Будь ласка, завітайте пізніше.') ?></p>
            <div class="site-mode-actions">
                <a class="button" href="<?= url('/admin/login') ?>">Увійти в адмінку</a>
            </div>
        </div>
    </div>
</section>
