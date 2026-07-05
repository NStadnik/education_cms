<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Адмінка') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('/assets/site.css') ?>">
</head>
<body>
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <div class="mb-4">
                <h2 class="h4 mb-1 text-white">CMS</h2>
                <p class="small text-white-50 mb-0"><?= e($user['name'] ?? '') ?></p>
            </div>
            <nav class="nav nav-pills flex-column gap-1">
                <a class="nav-link" href="<?= url('/admin') ?>">Огляд</a>
                <a class="nav-link" href="<?= url('/admin/pages') ?>">Сторінки</a>
                <a class="nav-link" href="<?= url('/admin/news') ?>">Новини</a>
                <a class="nav-link" href="<?= url('/admin/documents') ?>">Документи</a>
                <a class="nav-link" href="<?= url('/admin/public-info') ?>">Публічна інформація</a>
                <a class="nav-link" href="<?= url('/admin/users') ?>">Користувачі</a>
                <a class="nav-link" href="<?= url('/admin/settings') ?>">Налаштування</a>
                <a class="nav-link" href="<?= url('/') ?>">Переглянути сайт</a>
            </nav>
            <form method="post" action="<?= url('/admin/logout') ?>" class="mt-4">
                <?= \App\Core\Csrf::field() ?>
                <button class="btn btn-outline-light btn-sm" type="submit">Вийти</button>
            </form>
        </aside>
        <main class="admin-main">
            <?= $content ?>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.querySelectorAll('[data-filter-list]').forEach(function (panel) {
        const input = panel.querySelector('[data-filter-input]');
        const count = panel.querySelector('[data-filter-count]');
        const root = panel.nextElementSibling && panel.nextElementSibling.id === 'publicInfoAccordion'
            ? panel.nextElementSibling
            : panel;
        const rows = Array.from(root.querySelectorAll('[data-filter-row]'));
        if (!input || rows.length === 0) {
            return;
        }

        input.addEventListener('input', function () {
            const query = input.value.trim().toLowerCase();
            let visible = 0;
            rows.forEach(function (row) {
                const text = (row.getAttribute('data-filter-text') || '').toLowerCase();
                const show = query === '' || text.includes(query);
                row.hidden = !show;
                if (show) {
                    visible += 1;
                }
            });
            if (count) {
                count.textContent = String(visible);
            }
        });
    });
    </script>
</body>
</html>
