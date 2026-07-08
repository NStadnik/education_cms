<article class="section">
    <div class="container">
        <p class="meta"><?= e($item['category_titles'] ?: ($item['category'] ?? 'Загальні')) ?> · <?= e($item['published_at'] ?? '') ?></p>
        <h1><?= e($item['title']) ?></h1>
        <?php if (!empty($item['image_path'])): ?>
            <img class="news-main-image" src="<?= url('/thumb/' . \App\Services\Files::normalize((string) $item['image_path']) . '?w=1120&h=630&fit=crop') ?>" alt="<?= e($item['title']) ?>">
        <?php endif; ?>
        <div class="rich-content"><?= safe_html($item['body']) ?></div>
    </div>
</article>
