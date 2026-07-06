<?php if (!$blocks): ?>
    <section class="section">
        <div class="container">
            <div class="page-head public-head">
                <div>
                    <p class="eyebrow">Сторінка</p>
                    <h1><?= e($page['title']) ?></h1>
                    <?php if (!empty($page['excerpt'])): ?><p class="page-subtitle"><?= e($page['excerpt']) ?></p><?php endif; ?>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>
<?php foreach ($blocks as $block): ?>
    <?php if (($block['type'] ?? '') === 'hero'): ?>
        <section class="hero">
            <div class="container hero-inner">
                <h1><?= e($block['title'] ?? $page['title']) ?></h1>
                <p><?= e($block['text'] ?? '') ?></p>
            </div>
        </section>
    <?php elseif (($block['type'] ?? '') === 'news_list'): ?>
        <section class="section">
            <div class="container">
                <div class="toolbar">
                    <h2><?= e($block['title'] ?? 'Новини') ?></h2>
                    <a class="button secondary" href="<?= url('/news') ?>">Усі новини</a>
                </div>
                <div class="grid grid-3">
                    <?php foreach ($latestNews as $item): ?>
                        <article class="card">
                            <p class="meta"><?= e($item['published_at'] ?? '') ?></p>
                            <h3><a href="<?= url('/news/' . $item['slug']) ?>"><?= e($item['title']) ?></a></h3>
                            <p><?= e(excerpt($item['body'], 120)) ?></p>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$latestNews): ?>
                        <div class="card">Новини ще не додані.</div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php else: ?>
        <section class="section">
            <div class="container">
                <article class="card content-card">
                    <p class="eyebrow">Сторінка</p>
                    <h2><?= e($block['title'] ?? $page['title']) ?></h2>
                    <div class="rich-content"><?= safe_html($block['text'] ?? '') ?></div>
                </article>
            </div>
        </section>
    <?php endif; ?>
<?php endforeach; ?>
