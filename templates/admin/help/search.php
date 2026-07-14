<section class="admin-help-search-results" aria-live="polite">
    <div class="admin-help-search-summary">
        <p class="eyebrow">Результати пошуку</p>
        <h3><?= $results ? 'Знайдено: ' . e((string) count($results)) : 'Нічого не знайдено' ?></h3>
        <p>Запит: «<?= e($query) ?>»</p>
    </div>
    <?php if ($results): ?>
        <div class="admin-help-result-list">
            <?php foreach ($results as $topic): ?>
                <a href="<?= url('/admin/help?topic=' . rawurlencode((string) $topic['key'])) ?>" data-admin-help-topic="<?= e((string) $topic['key']) ?>">
                    <span class="mdi mdi-text-box-outline" aria-hidden="true"></span>
                    <span><strong><?= e((string) $topic['title']) ?></strong><small><?= e((string) ($topic['intro'] ?? '')) ?></small></span>
                    <span class="mdi mdi-chevron-right" aria-hidden="true"></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="admin-help-empty">
            <span class="mdi mdi-magnify-close" aria-hidden="true"></span>
            <p>Спробуйте коротший запит або інше слово, наприклад «модерація», «зображення» чи «пошта».</p>
        </div>
    <?php endif; ?>
</section>
