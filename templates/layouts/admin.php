<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Адмінка') ?></title>
    <link rel="stylesheet" href="<?= url('/assets/site.css') ?>">
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <h2>CMS</h2>
            <p class="meta" style="color:#dbe7ff"><?= e($user['name'] ?? '') ?></p>
            <a href="<?= url('/admin') ?>">Огляд</a>
            <a href="<?= url('/admin/pages') ?>">Сторінки</a>
            <a href="<?= url('/admin/news') ?>">Новини</a>
            <a href="<?= url('/admin/documents') ?>">Документи</a>
            <a href="<?= url('/admin/public-info') ?>">Публічна інформація</a>
            <a href="<?= url('/admin/users') ?>">Користувачі</a>
            <a href="<?= url('/admin/settings') ?>">Налаштування</a>
            <a href="<?= url('/') ?>">Переглянути сайт</a>
            <form method="post" action="<?= url('/admin/logout') ?>" style="margin-top:16px">
                <?= App\Core\Csrf::field() ?>
                <button class="button secondary" type="submit">Вийти</button>
            </form>
        </aside>
        <main class="main">
            <?= $content ?>
        </main>
    </div>
</body>
</html>
