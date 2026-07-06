<!doctype html>
<?php
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH) ?: '/admin';
    $adminNav = [
        ['/admin', 'Огляд', 'mdi-view-dashboard-outline'],
        ['/admin/pages', 'Сторінки', 'mdi-file-document-edit-outline'],
        ['/admin/news', 'Новини', 'mdi-newspaper-variant-outline'],
        ['/admin/media', 'Медіафайли', 'mdi-image-multiple-outline'],
        ['/admin/users', 'Користувачі', 'mdi-account-group-outline'],
        ['/admin/templates', 'Шаблони', 'mdi-palette-outline'],
        ['/admin/import', 'Імпорт', 'mdi-database-import-outline'],
    ];

    $isActiveAdminNav = static function (string $path) use ($currentPath): bool {
        return $path === '/admin' ? $currentPath === '/admin' : strpos($currentPath, $path) === 0;
    };
?>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Адмінка') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('/assets/site.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/admin.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/tinymce-editor.css') ?>">
    <?php if (strpos($currentPath, '/admin/pages') === 0): ?>
        <link rel="stylesheet" href="<?= url('/assets/admin-pages-form.css') ?>">
    <?php endif; ?>
    <?php if (strpos($currentPath, '/admin/templates') === 0): ?>
        <link rel="stylesheet" href="<?= url('/assets/admin-templates.css') ?>">
    <?php endif; ?>
</head>
<body data-admin-csrf-token="<?= e(\App\Core\Csrf::token()) ?>" data-rich-media-picker-url="<?= url('/admin/media/picker') ?>" data-rich-media-upload-url="<?= url('/admin/media/upload') ?>">
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <div class="mb-4">
                <h2 class="h4 mb-1 text-white">Education CMS</h2>
                <p class="small text-white-50 mb-0">Панель керування</p>
            </div>
            <nav class="nav nav-pills flex-column gap-1">
                <?php foreach ($adminNav as $navItem): ?>
                    <a class="nav-link <?= $isActiveAdminNav($navItem[0]) ? 'active' : '' ?>" href="<?= url($navItem[0]) ?>">
                        <span class="mdi <?= e($navItem[2]) ?>" aria-hidden="true"></span>
                        <span><?= e($navItem[1]) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>
        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-header-title">
                    <span class="admin-header-kicker">Адмінка</span>
                    <strong><?= e($title ?? 'Панель керування') ?></strong>
                </div>
                <div class="admin-header-actions">
                    <span class="admin-user">
                        <span class="mdi mdi-account-circle-outline" aria-hidden="true"></span>
                        <span data-admin-user-name><?= e($user['name'] ?? 'Адміністратор') ?></span>
                    </span>
                    <a class="admin-header-button <?= strpos($currentPath, '/admin/profile') === 0 ? 'active' : '' ?>" href="<?= url('/admin/profile') ?>" title="Профіль">
                        <span class="mdi mdi-account-cog-outline" aria-hidden="true"></span>
                        <span>Профіль</span>
                    </a>
                    <a class="admin-header-button" href="<?= url('/admin/updates') ?>" title="Оновлення">
                        <span class="mdi mdi-update" aria-hidden="true"></span>
                        <span>Оновлення</span>
                    </a>
                    <a class="admin-header-button" href="<?= url('/admin/settings') ?>" title="Налаштування">
                        <span class="mdi mdi-cog-outline" aria-hidden="true"></span>
                        <span>Налаштування</span>
                    </a>
                    <a class="admin-header-button" href="<?= url('/') ?>" target="_blank" rel="noopener" title="Переглянути сайт">
                        <span class="mdi mdi-open-in-new" aria-hidden="true"></span>
                        <span>Сайт</span>
                    </a>
                    <form method="post" action="<?= url('/admin/logout') ?>" data-no-ajax>
                        <?= \App\Core\Csrf::field() ?>
                        <button class="admin-header-button admin-header-button-danger" type="submit" title="Вийти">
                            <span class="mdi mdi-logout" aria-hidden="true"></span>
                            <span>Вийти</span>
                        </button>
                    </form>
                </div>
            </header>
            <div class="admin-content">
                <?= $content ?>
            </div>
        </main>
    </div>
    <div class="modal fade" id="richMediaModal" tabindex="-1" aria-labelledby="richMediaTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title h5" id="richMediaTitle">Вставити медіафайл</h2>
                        <p class="meta mb-0">Оберіть один файл або кілька зображень для галереї.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
                </div>
                <div class="modal-body">
                    <div class="rich-media-tools">
                        <input type="search" class="form-control" data-rich-media-search placeholder="Пошук за назвою або шляхом">
                        <label class="button secondary mb-0">
                            <span class="mdi mdi-upload" aria-hidden="true"></span><span>Завантажити</span>
                            <input type="file" data-rich-media-upload hidden>
                        </label>
                    </div>
                    <div class="rich-media-options">
                        <label>Режим
                            <select data-rich-media-mode>
                                <option value="single">Один файл</option>
                                <option value="gallery">Галерея</option>
                            </select>
                        </label>
                        <label>Розташування
                            <select data-rich-media-align>
                                <option value="center">По центру</option>
                                <option value="left">Ліворуч</option>
                                <option value="right">Праворуч</option>
                                <option value="wide">На всю ширину</option>
                            </select>
                        </label>
                        <label>Колонки галереї
                            <select data-rich-media-columns>
                                <option value="2">2</option>
                                <option value="3" selected>3</option>
                                <option value="4">4</option>
                            </select>
                        </label>
                        <label>Підпис
                            <input type="text" data-rich-media-caption placeholder="Необов'язково">
                        </label>
                    </div>
                    <div class="rich-media-status meta" data-rich-media-status></div>
                    <div class="rich-media-workspace">
                        <div class="rich-media-grid" data-rich-media-grid></div>
                        <aside class="rich-media-selection" aria-live="polite">
                            <div class="rich-media-selection-head">
                                <div>
                                    <strong data-rich-media-selection-count>Нічого не вибрано</strong>
                                    <span data-rich-media-selection-help>Оберіть файл у списку.</span>
                                </div>
                                <button type="button" class="button secondary compact" data-rich-media-clear hidden>
                                    <span class="mdi mdi-close" aria-hidden="true"></span><span>Очистити</span>
                                </button>
                            </div>
                            <div class="rich-media-selected-list" data-rich-media-selected-list></div>
                            <div class="rich-media-preview" data-rich-media-preview>
                                <div class="rich-media-preview-empty">Попередній перегляд зʼявиться після вибору медіафайлів.</div>
                            </div>
                        </aside>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="button secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="button" class="button" data-rich-media-insert>
                        <span class="mdi mdi-plus" aria-hidden="true"></span><span>Вставити</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= url('/assets/tinymce/tinymce.min.js') ?>"></script>
    <script src="<?= url('/assets/tinymce-editor.js') ?>"></script>
    <script src="<?= url('/assets/admin.js') ?>"></script>
</body>
</html>
