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
        <div class="grid grid-3 news-grid">
            <?php foreach ($items as $item): ?>
                <article class="card content-card">
                    <p class="meta"><?= e($item['published_at'] ?? '') ?></p>
                    <h2><a href="<?= url('/news/' . $item['slug']) ?>"><?= e($item['title']) ?></a></h2>
                    <p><?= e(excerpt($item['body'], 180)) ?></p>
                    <a class="read-more" href="<?= url('/news/' . $item['slug']) ?>">Читати</a>
                </article>
            <?php endforeach; ?>
            <?php if (!$items): ?><div class="empty-state">Новини ще не додані.</div><?php endif; ?>
        </div>
    </div>
</section>
