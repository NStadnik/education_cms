<?php $hasLayoutBlocks = !empty(array_filter($blocks, static fn ($block): bool => is_array($block) && ($block['type'] ?? '') !== 'text')); ?>
<section class="section page-template-wide">
    <div class="container">
        <div class="page-head public-head">
            <div>
                <h1><?= e($page['title']) ?></h1>
                <?php if (!empty($page['excerpt'])): ?><p class="page-subtitle"><?= e($page['excerpt']) ?></p><?php endif; ?>
            </div>
        </div>
        <?php foreach ($blocks as $block): ?>
            <?php if (($block['type'] ?? '') === 'text'): ?>
                <div class="rich-content wide-content"><?= safe_html($block['text'] ?? '') ?></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php if ($hasLayoutBlocks): ?>
    <?php foreach ($blocks as $block): ?>
        <?php if (($block['type'] ?? '') !== 'text'): ?>
            <?= $this->partial('public/partials/page-block', ['page' => $page, 'block' => $block, 'latestNews' => $latestNews]) ?>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>
