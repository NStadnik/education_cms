<!doctype html>
<?php
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH) ?: '/admin';
    $auth = \App\Core\Container::get('auth');
    $isSuperAdmin = (string) (($auth->user()['role'] ?? '')) === 'super_admin';
    $canAny = static function (array $permissions) use ($auth, $isSuperAdmin): bool {
        foreach ($permissions as $permission) {
            if ($permission === '__super_admin') {
                return $isSuperAdmin;
            }
            if ($auth->can($permission)) {
                return true;
            }
        }
        return false;
    };
    $adminNav = [
        ['/admin', 'Огляд', 'mdi-view-dashboard-outline', []],
        ['/admin/pages', 'Сторінки', 'mdi-file-document-edit-outline', ['pages.manage']],
        ['/admin/forms', 'Форми', 'mdi-form-select', ['forms.manage']],
        ['/admin/news', 'Новини', 'mdi-newspaper-variant-outline', ['news.manage', 'news.review', 'news.publish']],
        ['/admin/media', 'Медіафайли', 'mdi-image-multiple-outline', ['media.manage']],
        ['/admin/optimizer', 'Оптимізатор', 'mdi-auto-fix', ['__super_admin']],
        ['/admin/users', 'Користувачі', 'mdi-account-group-outline', ['users.manage']],
        ['/admin/templates', 'Шаблони', 'mdi-palette-outline', ['settings.manage']],
        ['/admin/import', 'Імпорт', 'mdi-database-import-outline', ['settings.manage']],
    ];
    $adminNav = array_values(array_filter($adminNav, static fn (array $item): bool => $item[3] === [] || $canAny($item[3])));

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
    <link rel="stylesheet" href="<?= url('/assets/site.css?v=20260712-8') ?>">
    <link rel="stylesheet" href="<?= url('/assets/admin.css?v=20260712-6') ?>">
    <link rel="stylesheet" href="<?= url('/assets/tiptap-editor.css?v=20260710-1') ?>">
    <?php if (strpos($currentPath, '/admin/pages') === 0): ?>
        <link rel="stylesheet" href="<?= url('/assets/admin-pages-form.css?v=20260710-2') ?>">
    <?php endif; ?>
    <?php if (strpos($currentPath, '/admin/forms') === 0): ?><link rel="stylesheet" href="<?= url('/assets/admin-forms.css?v=20260710-1') ?>"><link rel="stylesheet" href="<?= url('/assets/admin-forms-fixes.css?v=20260710-1') ?>"><?php endif; ?>
    <?php if (strpos($currentPath, '/admin/templates') === 0): ?>
        <link rel="stylesheet" href="<?= url('/assets/admin-templates.css') ?>">
    <?php endif; ?>
</head>
<body data-admin-csrf-token="<?= e(\App\Core\Csrf::token()) ?>" data-rich-media-picker-url="<?= url('/admin/media/picker') ?>" data-rich-media-upload-url="<?= url('/admin/media/upload') ?>" data-admin-link-picker-url="<?= url('/admin/link-picker') ?>">
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <div class="admin-brand mb-4">
                <h2 class="h4 mb-1 text-white"><img src="<?= url('/assets/images/education_cms_logo_for_black.png') ?>" alt="Education CMS" class="admin-logo"></h2>
                <a href="https://lcloud.in.ua" target="_blank" rel="noopener">Навчальна хмара «ЛКЛАУД»</a>
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
                    <?php if ($auth->can('updates.manage')): ?><a class="admin-header-button" href="<?= url('/admin/updates') ?>" title="Оновлення">
                        <span class="mdi mdi-update" aria-hidden="true"></span>
                        <span>Оновлення</span>
                    </a><?php endif; ?>
                    <?php if ($auth->can('settings.manage')): ?><a class="admin-header-button" href="<?= url('/admin/settings') ?>" title="Налаштування">
                        <span class="mdi mdi-cog-outline" aria-hidden="true"></span>
                        <span>Налаштування</span>
                    </a><?php endif; ?>
                    <a class="admin-header-button" href="<?= url('/') ?>"  rel="noopener" title="Переглянути сайт">
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
    <div class="modal fade" id="adminLinkPickerModal" tabindex="-1" aria-labelledby="adminLinkPickerTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <p class="eyebrow mb-1" data-admin-link-picker-eyebrow>Посилання</p>
                        <h2 class="modal-title h5" id="adminLinkPickerTitle" data-admin-link-picker-title>Обрати посилання</h2>
                        <p class="meta mb-0" data-admin-link-picker-hint>Оберіть сторінку, категорію, новину або медіафайл.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
                </div>
                <div class="modal-body">
                    <div class="admin-link-picker">
                        <div class="admin-link-picker-tabs" role="tablist" aria-label="Тип посилання">
                            <button class="button compact" type="button" data-admin-link-picker-type="pages"><span class="mdi mdi-file-document-outline" aria-hidden="true"></span><span>Сторінки</span></button>
                            <button class="button secondary compact" type="button" data-admin-link-picker-type="categories"><span class="mdi mdi-shape-outline" aria-hidden="true"></span><span>Категорії</span></button>
                            <button class="button secondary compact" type="button" data-admin-link-picker-type="news"><span class="mdi mdi-newspaper-variant-outline" aria-hidden="true"></span><span>Новини</span></button>
                            <button class="button secondary compact" type="button" data-admin-link-picker-type="media"><span class="mdi mdi-folder-image" aria-hidden="true"></span><span>Медіафайли</span></button>
                        </div>
                        <div class="admin-link-picker-filters">
                            <label class="admin-link-picker-search">Пошук
                                <input type="search" data-admin-link-picker-search placeholder="Назва, slug, файл або текст">
                            </label>
                            <label data-admin-link-picker-filter="status">Статус
                                <select data-admin-link-picker-status>
                                    <option value="published">Опубліковані</option>
                                    <option value="draft">Чернетки</option>
                                    <option value="">Усі</option>
                                </select>
                            </label>
                            <label data-admin-link-picker-filter="scope" hidden>Категорії
                                <select data-admin-link-picker-scope>
                                    <option value="">Усі</option>
                                    <option value="root">Кореневі</option>
                                    <option value="children">Дочірні</option>
                                </select>
                            </label>
                            <div class="admin-view-switch" data-admin-link-picker-view-wrap hidden role="group" aria-label="Режим перегляду медіафайлів">
                                <button class="button secondary compact" type="button" data-admin-link-picker-view="list" title="Список"><span class="mdi mdi-format-list-bulleted" aria-hidden="true"></span></button>
                                <button class="button secondary compact" type="button" data-admin-link-picker-view="grid" title="Великі превʼю"><span class="mdi mdi-view-grid-outline" aria-hidden="true"></span></button>
                            </div>
                        </div>
                        <div class="admin-link-picker-status" data-admin-link-picker-status-text>Оберіть тип посилання.</div>
                        <div class="admin-link-picker-list" data-admin-link-picker-list></div>
                        <button class="button secondary compact admin-link-picker-more" type="button" data-admin-link-picker-more hidden>
                            <span class="mdi mdi-chevron-down" aria-hidden="true"></span><span>Показати ще</span>
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="button" data-admin-link-picker-apply disabled>
                        <span class="mdi mdi-check" aria-hidden="true"></span><span data-admin-link-picker-apply-label>Обрати</span>
                    </button>
                    <button type="button" class="button secondary" data-bs-dismiss="modal"><span class="mdi mdi-close" aria-hidden="true"></span><span>Закрити</span></button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="richMediaModal" tabindex="-1" aria-labelledby="richMediaTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title h5" id="richMediaTitle">Додати медіа</h2>
                        <p class="meta mb-0">Оберіть фото чи файл. Для галереї виберіть кілька зображень у потрібному порядку.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
                </div>
                <div class="modal-body">
                    <div class="rich-media-tools">
                        <input type="search" class="form-control" data-rich-media-search placeholder="Пошук за назвою або шляхом">
                        <div class="admin-view-switch" role="group" aria-label="Режим перегляду медіафайлів">
                            <button class="button secondary compact" type="button" data-rich-media-view="compact" title="Компактні превʼю"><span class="mdi mdi-view-grid-outline" aria-hidden="true"></span></button>
                            <button class="button secondary compact" type="button" data-rich-media-view="large" title="Великі превʼю"><span class="mdi mdi-view-dashboard-outline" aria-hidden="true"></span></button>
                        </div>
                        <label class="button secondary mb-0">
                            <span class="mdi mdi-upload" aria-hidden="true"></span><span>Завантажити</span>
                            <input type="file" data-rich-media-upload hidden>
                        </label>
                    </div>
                    <div class="rich-media-options">
                        <label>Режим
                            <select data-rich-media-mode>
                                <option value="single">Одне фото або файл</option>
                                <option value="gallery">Галерея</option>
                            </select>
                        </label>
                        <label>Ширина і розташування
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
                            <input type="text" data-rich-media-caption placeholder="Короткий опис під медіа">
                        </label>
                    </div>
                    <div class="rich-media-status meta" data-rich-media-status></div>
                    <div class="rich-media-workspace">
                        <div class="rich-media-grid" data-rich-media-grid></div>
                        <aside class="rich-media-selection" aria-live="polite">
                            <div class="rich-media-selection-head">
                                <div>
                                    <strong data-rich-media-selection-count>Нічого не вибрано</strong>
                                    <span data-rich-media-selection-help>Оберіть файл у списку. Порядок вибору стане порядком фото.</span>
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
    <script src="<?= url('/assets/tiptap.bundle.20260709-7.js?v=20260709-13') ?>"></script>
    <script src="<?= url('/assets/tiptap-editor.js?v=20260710-6') ?>"></script>
    <script src="<?= url('/assets/admin-link-picker.js') ?>"></script>
    <script src="<?= url('/assets/admin.js?v=20260711-1') ?>"></script>
    <?php if ($currentPath === '/admin'): ?><script src="<?= url('/assets/admin-dashboard.js?v=20260712-2') ?>"></script><?php endif; ?>
    <?php if (strpos($currentPath, '/admin/forms') === 0): ?><script src="<?= url('/assets/admin-forms.js?v=20260710-1') ?>"></script><?php endif; ?>
</body>
</html>
