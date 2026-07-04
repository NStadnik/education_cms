<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'CMS') ?></title>
    <link rel="stylesheet" href="<?= url('/assets/site.css') ?>">
</head>
<body>
    <main class="section">
        <div class="container">
            <?= $content ?>
        </div>
    </main>
</body>
</html>
