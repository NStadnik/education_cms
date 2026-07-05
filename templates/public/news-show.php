<article class="section">
    <div class="container">
        <p class="meta"><?= e($item['category'] ?? 'Загальні') ?> · <?= e($item['published_at'] ?? '') ?></p>
        <h1><?= e($item['title']) ?></h1>
        <div class="rich-content"><?= safe_html($item['body']) ?></div>
    </div>
</article>
