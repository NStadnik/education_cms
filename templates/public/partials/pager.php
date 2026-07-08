<?php
    $currentPage = max(1, (int) ($currentPage ?? 1));
    $pages = max(1, (int) ($pages ?? 1));
    $label = (string) ($label ?? 'Навігація сторінками');
    $jumpLabel = (string) ($jumpLabel ?? 'Сторінка');
    $class = trim('site-pager ' . (string) ($class ?? ''));
    $urlFactory = $urlFactory ?? static fn (int $page): string => '#';
    $window = max(1, (int) ($window ?? 2));
    $windowStart = max(1, $currentPage - $window);
    $windowEnd = min($pages, $currentPage + $window);
?>
<?php if ($pages > 1): ?>
    <nav class="<?= e($class) ?>" aria-label="<?= e($label) ?>">
        <a class="site-pager-control <?= $currentPage <= 1 ? 'is-disabled' : '' ?>" href="<?= e($urlFactory(max(1, $currentPage - 1))) ?>" aria-label="Попередня сторінка" <?= $currentPage <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
            <span class="mdi mdi-chevron-left" aria-hidden="true"></span>
        </a>
        <div class="site-pager-pages">
            <?php if ($windowStart > 1): ?>
                <a href="<?= e($urlFactory(1)) ?>">1</a>
                <?php if ($windowStart > 2): ?><span aria-hidden="true">...</span><?php endif; ?>
            <?php endif; ?>
            <?php for ($i = $windowStart; $i <= $windowEnd; $i++): ?>
                <a class="<?= $i === $currentPage ? 'is-active' : '' ?>" href="<?= e($urlFactory($i)) ?>" <?= $i === $currentPage ? 'aria-current="page"' : '' ?>><?= e((string) $i) ?></a>
            <?php endfor; ?>
            <?php if ($windowEnd < $pages): ?>
                <?php if ($windowEnd < $pages - 1): ?><span aria-hidden="true">...</span><?php endif; ?>
                <a href="<?= e($urlFactory($pages)) ?>"><?= e((string) $pages) ?></a>
            <?php endif; ?>
        </div>
        <a class="site-pager-control <?= $currentPage >= $pages ? 'is-disabled' : '' ?>" href="<?= e($urlFactory(min($pages, $currentPage + 1))) ?>" aria-label="Наступна сторінка" <?= $currentPage >= $pages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
            <span class="mdi mdi-chevron-right" aria-hidden="true"></span>
        </a>
        <label class="site-page-jump">
            <span><?= e($jumpLabel) ?></span>
            <select data-page-jump aria-label="Швидкий перехід до сторінки">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <option value="<?= e($urlFactory($i)) ?>" <?= $i === $currentPage ? 'selected' : '' ?>><?= e((string) $i) ?> / <?= e((string) $pages) ?></option>
                <?php endfor; ?>
            </select>
        </label>
    </nav>
<?php endif; ?>
