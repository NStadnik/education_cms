<?php
    $type = (string) ($block['type'] ?? 'text');
?>
<?php if ($type === 'hero'): ?>
    <?php if (empty($homeHeroVisible)): ?>
        <section class="hero">
            <div class="container hero-inner">
                <h1><?= e($block['title'] ?? $page['title']) ?></h1>
                <p><?= e($block['text'] ?? '') ?></p>
            </div>
        </section>
    <?php endif; ?>
<?php elseif ($type === 'news_list'): ?>
    <section class="section">
        <div class="container">
            <div class="toolbar">
                <h2><?= e($block['title'] ?? 'Новини') ?></h2>
                <a class="button secondary" href="<?= url('/news') ?>">Усі новини</a>
            </div>
            <div class="grid grid-3">
                <?php foreach (($latestNews ?? []) as $item): ?>
                    <article class="card">
                        <p class="meta"><?= e($item['published_at'] ?? '') ?></p>
                        <h3><a href="<?= url('/news/' . $item['slug']) ?>"><?= e($item['title']) ?></a></h3>
                        <p><?= e(excerpt($item['body'], 120)) ?></p>
                    </article>
                <?php endforeach; ?>
                <?php if (empty($latestNews)): ?>
                    <div class="card">Новини ще не додані.</div>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php elseif ($type === 'layout'): ?>
    <?php $background = preg_replace('/[^a-z0-9_-]/i', '', (string) ($block['background'] ?? 'default')) ?: 'default'; ?>
    <section class="section page-layout-section page-layout-bg-<?= e($background) ?>">
        <div class="container">
            <?php if (!empty($block['title'])): ?>
                <div class="page-head public-head">
                    <div>
                        <h2><?= e($block['title']) ?></h2>
                    </div>
                </div>
            <?php endif; ?>
            <?php foreach (($block['rows'] ?? []) as $row): ?>
                <div class="row g-4 page-layout-row">
                    <?php foreach (($row['columns'] ?? []) as $column): ?>
                        <?php $width = in_array(($column['width'] ?? ''), ['col-md-12', 'col-md-8', 'col-md-6', 'col-md-4'], true) ? $column['width'] : 'col-md-12'; ?>
                        <div class="<?= e($width) ?>">
                            <?php foreach (($column['cards'] ?? []) as $card): ?>
                                <?php $style = preg_replace('/[^a-z0-9_-]/i', '', (string) ($card['style'] ?? 'default')) ?: 'default'; ?>
                                <article class="card content-card page-layout-card page-layout-card-<?= e($style) ?>">
                                    <?php if (!empty($card['image'])): ?>
                                        <img src="<?= e((string) $card['image']) ?>" alt="" loading="lazy">
                                    <?php endif; ?>
                                    <?php if (!empty($card['title'])): ?><h3><?= e($card['title']) ?></h3><?php endif; ?>
                                    <?php if (!empty($card['text'])): ?><div class="rich-content"><?= safe_html($card['text']) ?></div><?php endif; ?>
                                    <?php if (!empty($card['button_text']) && !empty($card['button_url'])): ?>
                                        <a class="button read-more" href="<?= e((string) $card['button_url']) ?>"><?= e((string) $card['button_text']) ?></a>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php else: ?>
    <section class="section">
        <div class="container">
            <article class="card content-card">
                <h2><?= e($block['title'] ?? $page['title']) ?></h2>
                <div class="rich-content"><?= safe_html($block['text'] ?? '') ?></div>
            </article>
        </div>
    </section>
<?php endif; ?>
