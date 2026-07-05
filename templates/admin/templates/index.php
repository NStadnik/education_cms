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
                <p class="meta">Цей шаблон застосовується до шапки, кольорів, hero-блоків і загального вигляду сайту.</p>
            </div>
        </div>
        <div class="template-options">
            <?php foreach ($siteTemplates as $key => $template): ?>
                <label class="template-option">
                    <input type="radio" name="site_template" value="<?= e($key) ?>" <?= checked(($settings['site_template'] ?? 'official') === $key) ?>>
                    <span class="template-preview site-template-preview-<?= e($key) ?>" aria-hidden="true">
                        <span class="template-preview-top"></span>
                        <span class="template-preview-hero"></span>
                        <span class="template-preview-lines"><span></span><span></span><span></span></span>
                    </span>
                    <span class="template-option-body">
                        <strong><?= e($template['name']) ?></strong>
                        <span><?= e($template['description']) ?></span>
                        <button class="button secondary compact" type="button" data-site-template-preview data-template="<?= e($key) ?>" data-name="<?= e($template['name']) ?>" data-description="<?= e($template['description']) ?>">
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
    modalNode.querySelector('#siteTemplatePreviewTitle').textContent = button.getAttribute('data-name') || 'Попередній перегляд';
    modalNode.querySelector('[data-template-preview-description]').textContent = button.getAttribute('data-description') || '';
    preview.className = 'site-template-preview-large site-template-preview-' + (button.getAttribute('data-template') || 'official');
    new window.bootstrap.Modal(modalNode).show();
});
</script>
