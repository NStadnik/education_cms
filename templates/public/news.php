<section class="section">
    <div class="container">
        <h1>Новини</h1>
        <div class="grid grid-3">
            <?php foreach ($items as $item): ?>
                <article class="card">
                    <p class="meta"><?= e($item['published_at'] ?? '') ?></p>
                    <h2><a href="<?= url('/news/' . $item['slug']) ?>"><?= e($item['title']) ?></a></h2>
                    <p><?= e(excerpt($item['body'], 180)) ?></p>
                </article>
            <?php endforeach; ?>
            <?php if (!$items): ?><div class="card">Новини ще не додані.</div><?php endif; ?>
        </div>
    </div>
</section>
