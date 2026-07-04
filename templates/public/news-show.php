<article class="section">
    <div class="container">
        <p class="meta"><?= e($item['published_at'] ?? '') ?></p>
        <h1><?= e($item['title']) ?></h1>
        <div><?= nl2br(e($item['body'])) ?></div>
    </div>
</article>
