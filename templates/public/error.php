<main class="site-error-page">
    <section class="site-error-shell">
        <div class="site-error-copy">
            <p class="eyebrow"><?= e($label ?? 'Помилка') ?></p>
            <h1><?= e($headline ?? 'Сторінка недоступна') ?></h1>
            <p><?= e($message ?? 'Спробуйте повернутися на головну сторінку.') ?></p>
            <div class="site-error-actions">
                <a class="button" href="<?= e($primaryUrl ?? url('/')) ?>">
                    <span class="mdi mdi-home-outline" aria-hidden="true"></span>
                    <span><?= e($primaryLabel ?? 'На головну') ?></span>
                </a>
                <a class="button secondary" href="<?= e($secondaryUrl ?? url('/news')) ?>">
                    <span class="mdi mdi-newspaper-variant-outline" aria-hidden="true"></span>
                    <span><?= e($secondaryLabel ?? 'До новин') ?></span>
                </a>
            </div>
        </div>
        <div class="site-error-code" aria-label="Код помилки">
            <span><?= e((string) ($status ?? 404)) ?></span>
        </div>
    </section>
</main>
