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
<?php elseif ($type === 'form'): ?>
    <?php $embeddedForm = $formsById[(int) ($block['form_id'] ?? 0)] ?? null; ?>
    <?php if ($embeddedForm): ?><section class="section"><div class="container"><?= $this->partial('public/partials/form', ['form'=>$embeddedForm,'page'=>$page]) ?></div></section><?php endif; ?>
<?php elseif ($type === 'news_list'): ?>
    <section class="section">
        <div class="container">
            <div class="toolbar">
                <h2><?= e($block['title'] ?? 'Новини') ?></h2>
                <a class="button secondary" href="<?= url('/news') ?>">Усі новини</a>
            </div>
            <div class="grid grid-3">
                <?php foreach (($latestNews ?? []) as $item): ?>
                    <article class="card content-card">
                        <?php if (!empty($item['image_path'])): ?>
                            <a class="news-card-image" href="<?= url('/news/' . $item['slug']) ?>">
                                <img src="<?= url('/thumb/' . \App\Services\Files::normalize((string) $item['image_path']) . '?w=720&h=405&fit=crop') ?>" alt="<?= e($item['title']) ?>" loading="lazy">
                            </a>
                        <?php endif; ?>
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
                                <?php $embeddedForm = !empty($card['form_id']) ? ($formsById[(int) $card['form_id']] ?? null) : null; ?>
                                <?php if ($embeddedForm): ?>
                                    <?= $this->partial('public/partials/form', ['form' => $embeddedForm, 'page' => $page]) ?>
                                    <?php continue; ?>
                                <?php endif; ?>
                                <?php $style = preg_replace('/[^a-z0-9_-]/i', '', (string) ($card['style'] ?? 'default')) ?: 'default'; ?>
                                <article class="card content-card page-layout-card page-layout-card-<?= e($style) ?>">
                                    <?php if (!empty($card['image'])): ?>
                                        <img src="<?= e((string) $card['image']) ?>" alt="" loading="lazy">
                                    <?php endif; ?>
                                    <?php if (!empty($card['title'])): ?><h3><?= e($card['title']) ?></h3><?php endif; ?>
                                    <?php if (!empty($card['text'])): ?><div class="rich-content"><?= safe_html($card['text']) ?></div><?php endif; ?>
                                    <?php
                                        $cardLinks = [];
                                        foreach (($card['links'] ?? []) as $link) {
                                            if (!is_array($link)) {
                                                continue;
                                            }
                                            $label = trim((string) ($link['label'] ?? $link['text'] ?? $link['title'] ?? ''));
                                            $href = trim((string) ($link['url'] ?? $link['href'] ?? ''));
                                            if ($label !== '' && $href !== '') {
                                                $cardLinks[] = ['label' => $label, 'url' => $href];
                                            }
                                        }
                                        if (!$cardLinks && !empty($card['button_text']) && !empty($card['button_url'])) {
                                            $cardLinks[] = ['label' => (string) $card['button_text'], 'url' => (string) $card['button_url']];
                                        }
                                    ?>
                                    <?php if ($cardLinks): ?>
                                        <div class="page-layout-card-links">
                                            <?php foreach ($cardLinks as $link): ?>
                                                <a class="button read-more" href="<?= e($link['url']) ?>"><?= e($link['label']) ?></a>
                                            <?php endforeach; ?>
                                        </div>
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
                <?php if (trim((string) ($block['title'] ?? '')) !== ''): ?>
                    <h2><?= e((string) $block['title']) ?></h2>
                <?php endif; ?>
                <div class="rich-content"><?= safe_html($block['text'] ?? '') ?></div>
            </article>
        </div>
    </section>
<?php endif; ?>
