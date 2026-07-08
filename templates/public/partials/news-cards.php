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
