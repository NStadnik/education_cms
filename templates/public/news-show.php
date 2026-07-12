<?php
$viewsCount = max(0, (int) ($item['views_count'] ?? 0));
$viewsRemainder100 = $viewsCount % 100;
$viewsRemainder10 = $viewsCount % 10;
$viewsLabel = ($viewsRemainder100 >= 11 && $viewsRemainder100 <= 14)
    ? 'переглядів'
    : ($viewsRemainder10 === 1 ? 'перегляд' : ($viewsRemainder10 >= 2 && $viewsRemainder10 <= 4 ? 'перегляди' : 'переглядів'));
?>
<article class="section">
    <div class="container">
        <nav class="breadcrumbs" aria-label="Хлібні крихти">
            <ol>
                <li><a href="<?= url('/') ?>"><span class="mdi mdi-home-outline" aria-hidden="true"></span><span>Головна</span></a></li>
                <li><a href="<?= url('/news') ?>">Новини</a></li>
                <li aria-current="page"><?= e($item['title']) ?></li>
            </ol>
        </nav>
        <p class="meta news-article-meta">
            <span><?= e($item['category_titles'] ?: ($item['category'] ?? 'Загальні')) ?></span>
            <?php if (!empty($item['published_at'])): ?>
                <span><?= e($item['published_at']) ?></span>
            <?php endif; ?>
            <span class="news-article-views"><span class="mdi mdi-eye-outline" aria-hidden="true"></span><?= e((string) $viewsCount) ?> <?= e($viewsLabel) ?></span>
        </p>
        <h1><?= e($item['title']) ?></h1>
        <?php if (!empty($item['image_path'])): ?>
            <img class="news-main-image" src="<?= url('/thumb/' . \App\Services\Files::normalize((string) $item['image_path']) . '?w=1120&h=630&fit=crop') ?>" alt="<?= e($item['title']) ?>">
        <?php endif; ?>
        <div class="rich-content"><?= safe_html($item['body']) ?></div>
    </div>
</article>
