<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Сайт закладу освіти') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('/assets/site.css') ?>">
</head>
<body>
    <header class="topbar">
        <div class="container topbar-inner">
            <a class="brand" href="<?= url('/') ?>"><?= e($settings['institution_name'] ?? 'Заклад освіти') ?></a>
            <nav class="nav" aria-label="Головне меню">
                <a href="<?= url('/') ?>">Головна</a>
                <?php foreach (($menu ?? []) as $item): ?>
                    <?php if ($item['slug'] !== 'home'): ?>
                        <a href="<?= url('/page/' . $item['slug']) ?>"><?= e($item['title']) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <a href="<?= url('/news') ?>">Новини</a>
                <a href="<?= url('/documents') ?>">Документи</a>
                <a href="<?= url('/public-info') ?>">Публічна інформація</a>
            </nav>
        </div>
    </header>
    <?= $content ?>
    <footer class="footer">
        <div class="container">
            <strong><?= e($settings['institution_name'] ?? 'Заклад освіти') ?></strong><br>
            <?= e($settings['address'] ?? '') ?> <?= e($settings['phone'] ?? '') ?>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
