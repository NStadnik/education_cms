<section class="section page-template-document">
    <div class="container">
        <article class="document-page">
            <p class="eyebrow">Документ</p>
            <h1><?= e($page['title']) ?></h1>
            <?php if (!empty($page['excerpt'])): ?><p class="page-subtitle"><?= e($page['excerpt']) ?></p><?php endif; ?>
            <div class="rich-content">
                <?php foreach ($blocks as $block): ?>
                    <?php if (($block['type'] ?? '') === 'text'): ?>
                        <?= safe_html($block['text'] ?? '') ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </article>
    </div>
</section>
