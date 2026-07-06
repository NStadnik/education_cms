<?php if (!$blocks): ?>
    <section class="section">
        <div class="container">
            <div class="page-head public-head">
                <div>
                    <h1><?= e($page['title']) ?></h1>
                    <?php if (!empty($page['excerpt'])): ?><p class="page-subtitle"><?= e($page['excerpt']) ?></p><?php endif; ?>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>
<?php foreach ($blocks as $block): ?>
    <?= $this->partial('public/partials/page-block', ['page' => $page, 'block' => $block, 'latestNews' => $latestNews]) ?>
<?php endforeach; ?>
