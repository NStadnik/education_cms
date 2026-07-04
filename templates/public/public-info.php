<section class="section">
    <div class="container">
        <h1>Прозорість та інформаційна відкритість</h1>
        <p class="meta">Розділ сформовано відповідно до вимог відкритості інформації закладу освіти.</p>
        <div class="grid">
            <?php foreach ($sections as $section): ?>
                <article class="card">
                    <div class="toolbar">
                        <h2><?= e($section['title']) ?></h2>
                        <span class="status <?= $section['status'] === 'published' ? 'ok' : 'warn' ?>"><?= e($section['status'] ?? 'missing') ?></span>
                    </div>
                    <?php if (!empty($section['body'])): ?>
                        <div><?= nl2br(e($section['body'])) ?></div>
                    <?php endif; ?>
                    <p class="meta">
                        Оновлено: <?= e($section['updated_at'] ?? '') ?>
                        <?php if (!empty($section['responsible'])): ?> · Відповідальний: <?= e($section['responsible']) ?><?php endif; ?>
                    </p>
                    <?php if (!empty($section['file_path'])): ?>
                        <a class="button secondary" href="<?= url('/uploads/' . $section['file_path']) ?>">Відкрити документ</a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
