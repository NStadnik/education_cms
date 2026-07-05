<section class="section page-template-wide">
    <div class="container">
        <div class="page-head public-head">
            <div>
                <p class="eyebrow">Сторінка</p>
                <h1><?= e($page['title']) ?></h1>
                <?php if (!empty($page['excerpt'])): ?><p class="page-subtitle"><?= e($page['excerpt']) ?></p><?php endif; ?>
            </div>
        </div>
        <div class="rich-content wide-content">
            <?php foreach ($blocks as $block): ?>
                <?php if (($block['type'] ?? '') === 'text'): ?>
                    <?= safe_html($block['text'] ?? '') ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
