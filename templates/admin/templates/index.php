<div class="page-head">
    <div>
        <p class="eyebrow">Оформлення</p>
        <h1>Шаблони сайту</h1>
        <p class="page-subtitle">Виберіть глобальне оформлення публічного сайту та перегляньте доступні варіанти.</p>
    </div>
</div>

<form class="form-grid wide" method="post" action="<?= url('/admin/templates/save') ?>">
    <?= \App\Core\Csrf::field() ?>
    <section class="card admin-form-card">
        <div class="form-section-head">
            <div>
                <h2>Глобальний шаблон</h2>
                <p class="meta">Кожен шаблон винесений в окрему папку: метадані в <code>templates/site-themes/{key}/theme.php</code>, стилі в <code>public/assets/site-themes/{key}/theme.css</code>.</p>
            </div>
        </div>
        <div class="template-options">
            <?php foreach ($siteTemplates as $key => $template): ?>
                <?php
                    $preview = is_array($template['preview'] ?? null) ? $template['preview'] : [];
                    $previewTop = (string) ($preview['top'] ?? '#10233f');
                    $previewHero = (string) ($preview['hero'] ?? '#dfeafb');
                    $previewLine = (string) ($preview['line'] ?? '#c8d1df');
                    $features = is_array($template['features'] ?? null) ? $template['features'] : [];
                ?>
                <label class="template-option">
                    <input type="radio" name="site_template" value="<?= e($key) ?>" <?= checked(($settings['site_template'] ?? 'official') === $key) ?>>
                    <span class="template-preview" aria-hidden="true">
                        <span class="template-preview-top" style="background: <?= e($previewTop) ?>"></span>
                        <span class="template-preview-hero" style="background: <?= e($previewHero) ?>"></span>
                        <span class="template-preview-lines" style="--template-preview-line: <?= e($previewLine) ?>"><span></span><span></span><span></span></span>
                    </span>
                    <span class="template-option-body">
                        <strong><?= e($template['name']) ?></strong>
                        <span><?= e($template['description']) ?></span>
                        <?php if ($features): ?>
                            <span class="template-feature-list">
                                <?php foreach ($features as $feature): ?>
                                    <small><?= e((string) $feature) ?></small>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                        <code><?= e($key) ?></code>
                        <button
                            class="button secondary compact"
                            type="button"
                            data-site-template-preview
                            data-template="<?= e($key) ?>"
                            data-name="<?= e($template['name']) ?>"
                            data-description="<?= e($template['description']) ?>"
                            data-preview-top="<?= e($previewTop) ?>"
                            data-preview-hero="<?= e($previewHero) ?>"
                            data-preview-line="<?= e($previewLine) ?>"
                        >
                            <span class="mdi mdi-eye-outline" aria-hidden="true"></span><span>Переглянути</span>
                        </button>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <button type="submit"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span>Зберегти шаблон</span></button>
</form>

<div class="modal fade" id="siteTemplatePreviewModal" tabindex="-1" aria-labelledby="siteTemplatePreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5" id="siteTemplatePreviewTitle">Попередній перегляд</h2>
                    <p class="meta mb-0" data-template-preview-description></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
            </div>
            <div class="modal-body">
                <div class="site-template-preview-large" data-template-preview-large>
                    <div class="template-preview-top"></div>
                    <div class="template-preview-hero"></div>
                    <div class="template-preview-grid">
                        <span></span><span></span><span></span>
                    </div>
                    <div class="template-preview-lines"><span></span><span></span><span></span></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button secondary" data-bs-dismiss="modal"><span class="mdi mdi-close" aria-hidden="true"></span><span>Закрити</span></button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-site-template-preview]');
    if (!button || !window.bootstrap) {
        return;
    }
    event.preventDefault();
    event.stopPropagation();

    const modalNode = document.getElementById('siteTemplatePreviewModal');
    const preview = modalNode.querySelector('[data-template-preview-large]');
    const top = preview.querySelector('.template-preview-top');
    const hero = preview.querySelector('.template-preview-hero');
    const lines = preview.querySelector('.template-preview-lines');
    modalNode.querySelector('#siteTemplatePreviewTitle').textContent = button.getAttribute('data-name') || 'Попередній перегляд';
    modalNode.querySelector('[data-template-preview-description]').textContent = button.getAttribute('data-description') || '';
    preview.className = 'site-template-preview-large';
    top.style.background = button.getAttribute('data-preview-top') || '#10233f';
    hero.style.background = button.getAttribute('data-preview-hero') || '#dfeafb';
    lines.style.setProperty('--template-preview-line', button.getAttribute('data-preview-line') || '#c8d1df');
    new window.bootstrap.Modal(modalNode).show();
});
</script>
