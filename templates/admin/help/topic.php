<?php if (!$topic): ?>
    <div class="admin-help-empty" role="status">
        <span class="mdi mdi-help-circle-outline" aria-hidden="true"></span>
        <h3>Тему не знайдено</h3>
        <p>Спробуйте знайти потрібну відповідь через пошук.</p>
    </div>
<?php else: ?>
    <article class="admin-help-article" data-admin-help-current-topic="<?= e((string) $topic['key']) ?>" data-admin-help-target-anchor="<?= e($anchor) ?>">
        <header class="admin-help-article-head">
            <p class="eyebrow">Контекстна довідка</p>
            <h3><?= e((string) $topic['title']) ?></h3>
            <?php if (!empty($topic['intro'])): ?><p><?= e((string) $topic['intro']) ?></p><?php endif; ?>
        </header>

        <nav class="admin-help-toc" aria-label="Зміст теми">
            <?php foreach ($topic['sections'] ?? [] as $section): ?>
                <a href="#admin-help-<?= e((string) $section['id']) ?>" data-admin-help-anchor-link="<?= e((string) $section['id']) ?>"><?= e((string) $section['title']) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="admin-help-sections">
            <?php foreach ($topic['sections'] ?? [] as $section): ?>
                <section id="admin-help-<?= e((string) $section['id']) ?>" data-admin-help-section="<?= e((string) $section['id']) ?>" tabindex="-1">
                    <h4><?= e((string) $section['title']) ?></h4>
                    <?php if (!empty($section['body'])): ?><p><?= e((string) $section['body']) ?></p><?php endif; ?>
                    <?php if (!empty($section['steps'])): ?>
                        <ol>
                            <?php foreach ($section['steps'] as $step): ?><li><?= e((string) $step) ?></li><?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                    <?php if (!empty($section['note'])): ?>
                        <div class="admin-help-note"><span class="mdi mdi-information-outline" aria-hidden="true"></span><p><?= e((string) $section['note']) ?></p></div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>

        <?php if ($related): ?>
            <aside class="admin-help-related">
                <h4>Пов’язані теми</h4>
                <div>
                    <?php foreach ($related as $item): ?>
                        <a href="<?= url('/admin/help?topic=' . rawurlencode((string) $item['key'])) ?>" data-admin-help-topic="<?= e((string) $item['key']) ?>">
                            <span><?= e((string) $item['title']) ?></span><span class="mdi mdi-chevron-right" aria-hidden="true"></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </aside>
        <?php endif; ?>
    </article>
<?php endif; ?>
