<?php
    $activeCategory = (string) ($activeCategory ?? '');
    $activeQuery = (string) ($activeQuery ?? '');
    $newsUrl = $newsUrl ?? static fn (?string $category = null, string $search = '', int $page = 1): string => '#';
?>
<?php if (!empty($categories)): ?>
    <div class="news-category-rail" data-news-category-rail aria-label="Категорії новин">
        <a class="<?= $activeCategory === '' ? 'is-active' : '' ?>" href="<?= e($newsUrl(null, $activeQuery)) ?>" data-news-filter-link>
            <span>Усі</span>
        </a>
        <?php foreach ($categories as $category): ?>
            <?php $categoryTitle = (string) $category['category']; ?>
            <a class="<?= $activeCategory === $categoryTitle ? 'is-active' : '' ?>" href="<?= e($newsUrl($categoryTitle, $activeQuery)) ?>" data-news-filter-link>
                <span><?= e($categoryTitle) ?></span>
                <strong><?= e((string) $category['items_count']) ?></strong>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
