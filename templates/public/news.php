<section class="section">
    <div class="container">
        <div class="page-head public-head">
            <div>
                <p class="eyebrow">Останні події</p>
                <h1>Новини</h1>
                <p class="page-subtitle">Актуальні повідомлення, оголошення та події закладу.</p>
            </div>
            <span class="status"><?= e((string) count($items)) ?> записів</span>
        </div>
        <?php if (!empty($categories)): ?>
            <div class="bulk-actions news-category-filter">
                <a class="status <?= empty($activeCategory) ? 'ok' : '' ?>" href="<?= url('/news') ?>">Усі</a>
                <?php foreach ($categories as $category): ?>
                    <a class="status <?= ($activeCategory ?? '') === $category['category'] ? 'ok' : '' ?>" href="<?= url('/news?category=' . urlencode($category['category'])) ?>">
                        <?= e($category['category']) ?> · <?= e((string) $category['items_count']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="grid grid-3 news-grid">
            <?php foreach ($items as $item): ?>
                <article class="card content-card">
                    <?php if (!empty($item['image_path'])): ?>
                        <a class="news-card-image" href="<?= url('/news/' . $item['slug']) ?>">
                            <img src="<?= url('/thumb/' . \App\Services\Files::normalize((string) $item['image_path']) . '?w=720&h=405&fit=crop') ?>" alt="<?= e($item['title']) ?>" loading="lazy">
                        </a>
                    <?php endif; ?>
                    <p class="meta"><?= e($item['category_titles'] ?: ($item['category'] ?? 'Загальні')) ?> · <?= e($item['published_at'] ?? '') ?></p>
                    <h2><a href="<?= url('/news/' . $item['slug']) ?>"><?= e($item['title']) ?></a></h2>
                    <p><?= e(excerpt($item['body'], 180)) ?></p>
                    <a class="read-more" href="<?= url('/news/' . $item['slug']) ?>">Читати</a>
                </article>
            <?php endforeach; ?>
            <?php if (!$items): ?><div class="empty-state">Новини ще не додані.</div><?php endif; ?>
        </div>
    </div>
</section>
