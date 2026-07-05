<section class="section">
    <div class="container">
        <?php
            $filled = 0;
            foreach ($sections as $section) {
                if ((int) ($section['documents_count'] ?? 0) > 0) {
                    $filled++;
                }
            }
        ?>
        <div class="page-head public-head">
            <div>
                <p class="eyebrow">Відкриті дані</p>
                <h1>Прозорість та інформаційна відкритість</h1>
                <p class="page-subtitle">Розділ сформовано відповідно до вимог відкритості інформації закладу освіти.</p>
            </div>
            <span class="status ok"><?= e((string) $filled) ?> / <?= e((string) count($sections)) ?> заповнено</span>
        </div>
        <div class="grid public-info-grid">
            <?php foreach ($sections as $section): ?>
                <article class="card content-card">
                    <div class="toolbar">
                        <h2><?= e($section['title']) ?></h2>
                        <span class="status <?= ((int) $section['documents_count']) > 0 ? 'ok' : 'warn' ?>">
                            <?= ((int) $section['documents_count']) > 0 ? 'опубліковано' : 'очікує документів' ?>
                        </span>
                    </div>
                    <?php $documents = $documentsBySection[$section['id']] ?? []; ?>
                    <?php if ($documents): ?>
                        <div class="document-stack">
                            <?php foreach ($documents as $document): ?>
                                <div class="document-row">
                                    <div>
                                        <strong><?= e($document['title']) ?></strong>
                                        <p class="meta"><?= e($document['description'] ?? '') ?><?= !empty($document['responsible']) ? ' · Відповідальний: ' . e($document['responsible']) : '' ?></p>
                                    </div>
                                    <div class="document-actions">
                                        <span class="meta"><?= e($document['published_at'] ?? $document['approved_at'] ?? $document['updated_at']) ?></span>
                                        <?php if ($document['file_path']): ?><a class="button secondary compact" href="<?= url('/uploads/' . $document['file_path']) ?>">Відкрити</a><?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state small">Документи ще не додані.</div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if (!$sections): ?><div class="empty-state">Розділи публічної інформації ще не додані.</div><?php endif; ?>
    </div>
</section>
