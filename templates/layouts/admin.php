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
        ['/admin/settings', 'Налаштування', 'mdi-cog-outline'],
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
</head>
<body>
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <div class="mb-4">
                <h2 class="h4 mb-1 text-white">Education CMS</h2>
                <p class="small text-white-50 mb-0"><?= e($user['name'] ?? '') ?></p>
            </div>
            <nav class="nav nav-pills flex-column gap-1">
                <?php foreach ($adminNav as $navItem): ?>
                    <a class="nav-link <?= $isActiveAdminNav($navItem[0]) ? 'active' : '' ?>" href="<?= url($navItem[0]) ?>">
                        <span class="mdi <?= e($navItem[2]) ?>" aria-hidden="true"></span>
                        <span><?= e($navItem[1]) ?></span>
                    </a>
                <?php endforeach; ?>
                <a class="nav-link" href="<?= url('/') ?>">
                    <span class="mdi mdi-open-in-new" aria-hidden="true"></span>
                    <span>Переглянути сайт</span>
                </a>
            </nav>
            <form method="post" action="<?= url('/admin/logout') ?>" class="mt-4" data-no-ajax>
                <?= \App\Core\Csrf::field() ?>
                <button class="btn btn-outline-light btn-sm admin-icon-button" type="submit">
                    <span class="mdi mdi-logout" aria-hidden="true"></span>
                    <span>Вийти</span>
                </button>
            </form>
        </aside>
        <main class="admin-main">
            <?= $content ?>
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
                    <div class="rich-media-grid" data-rich-media-grid></div>
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
    <script>
    const adminCsrfToken = <?= json_encode(\App\Core\Csrf::token(), JSON_UNESCAPED_UNICODE) ?>;
    document.querySelectorAll('[data-infinite-list]').forEach(function (panel) {
        const input = panel.querySelector('[data-filter-input]');
        const count = panel.querySelector('[data-filter-count]');
        const target = document.querySelector(panel.getAttribute('data-list-target'));
        const sentinel = panel.querySelector('[data-list-sentinel]');
        const status = panel.querySelector('[data-list-status]');
        const empty = panel.querySelector('[data-list-empty]');
        if (!target || !sentinel) {
            return;
        }

        let loading = false;
        let searchTimer = null;
        let activeRequest = null;

        function setStatus(message) {
            if (status) {
                status.textContent = message || '';
            }
        }

        function syncEmpty() {
            if (empty) {
                empty.classList.toggle('d-none', target.querySelector('[data-list-row]') !== null);
            }
        }

        async function loadList(reset) {
            if (reset && activeRequest) {
                activeRequest.abort();
                activeRequest = null;
                loading = false;
            }
            if (loading) {
                return;
            }
            const hasMore = panel.getAttribute('data-list-has-more') === '1';
            if (!reset && !hasMore) {
                return;
            }

            loading = true;
            setStatus('Завантаження...');

            const controller = new AbortController();
            activeRequest = controller;
            const url = new URL(panel.getAttribute('data-list-url'), window.location.origin);
            url.searchParams.set('offset', reset ? '0' : panel.getAttribute('data-list-offset') || '0');
            url.searchParams.set('limit', panel.getAttribute('data-list-limit') || '20');
            if (input && input.value.trim() !== '') {
                url.searchParams.set('q', input.value.trim());
            }
            panel.querySelectorAll('[data-list-filter]').forEach(function (field) {
                if (field.name && field.value !== '') {
                    url.searchParams.set(field.name, field.value);
                }
            });

            try {
                const response = await fetch(url.toString(), {
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    signal: controller.signal
                });
                const data = await response.json();
                if (!response.ok || !data.ok) {
                    throw new Error(data.message || 'Не вдалося завантажити записи.');
                }

                if (reset) {
                    target.innerHTML = data.html || '';
                } else {
                    target.insertAdjacentHTML('beforeend', data.html || '');
                }

                panel.setAttribute('data-list-offset', String(data.next_offset || 0));
                panel.setAttribute('data-list-has-more', data.has_more ? '1' : '0');
                if (count) {
                    count.textContent = String(data.total || 0);
                }
                syncEmpty();
                setStatus(data.has_more ? 'Прокрутіть нижче, щоб завантажити ще.' : 'Усі записи завантажено.');
            } catch (error) {
                if (error.name !== 'AbortError') {
                    setStatus(error.message || 'Помилка завантаження.');
                }
            } finally {
                loading = false;
                if (activeRequest === controller) {
                    activeRequest = null;
                }
            }
        }

        if (input) {
            input.addEventListener('input', function () {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(function () {
                    panel.setAttribute('data-list-offset', '0');
                    panel.setAttribute('data-list-has-more', '1');
                    loadList(true);
                }, 300);
            });
        }

        panel.querySelectorAll('[data-list-filter]').forEach(function (field) {
            field.addEventListener('change', function () {
                panel.setAttribute('data-list-offset', '0');
                panel.setAttribute('data-list-has-more', '1');
                loadList(true);
            });
        });

        const observer = new IntersectionObserver(function (entries) {
            if (entries.some(function (entry) { return entry.isIntersecting; })) {
                loadList(false);
            }
        }, {rootMargin: '240px'});

        panel.addEventListener('admin:reload-list', function () {
            panel.setAttribute('data-list-offset', '0');
            panel.setAttribute('data-list-has-more', '1');
            loadList(true);
        });

        observer.observe(sentinel);
        syncEmpty();
    });

    document.querySelectorAll('[data-filter-list]').forEach(function (panel) {
        const input = panel.querySelector('[data-filter-input]');
        const count = panel.querySelector('[data-filter-count]');
        const rows = Array.from(panel.querySelectorAll('[data-filter-row]'));
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

    document.addEventListener('change', function (event) {
        const toggle = event.target.closest('[data-bulk-check-all]');
        if (!toggle) {
            return;
        }

        const form = toggle.closest('form');
        const targetForm = form || (toggle.getAttribute('form') ? document.getElementById(toggle.getAttribute('form')) : null);
        if (!targetForm) {
            return;
        }

        const scope = toggle.closest('table') || targetForm;
        bulkChecksForForm(targetForm, false, scope).forEach(function (checkbox) {
            checkbox.checked = toggle.checked;
        });
    });

    document.addEventListener('submit', async function (event) {
        const form = event.target.closest('form[action*="/bulk"]');
        if (!form) {
            return;
        }

        event.preventDefault();

        const action = bulkActionForForm(form);
        const checked = bulkChecksForForm(form, true);
        if (!action || action.value === '' || checked.length === 0) {
            setAjaxFormMessage(form, 'Оберіть записи та групову дію.', true);
            return;
        }

        if (action.value.includes('delete') && !confirm(form.dataset.deleteConfirm || 'Видалити вибрані записи?')) {
            return;
        }

        const button = event.submitter || form.querySelector('button[type="submit"]') || (form.id ? document.querySelector('button[type="submit"][form="' + cssEscape(form.id) + '"]') : null);
        const originalHtml = button ? button.innerHTML : '';
        const panel = form.querySelector('[data-infinite-list]') || (form.dataset.listPanel ? document.querySelector(form.dataset.listPanel) : null);
        const body = new FormData(form);
        if (action && action.name && !body.has(action.name)) {
            body.set(action.name, action.value);
        }
        checked.forEach(function (checkbox) {
            const isExternal = form.id && checkbox.getAttribute('form') === form.id;
            if (checkbox.name && (isExternal || !body.has(checkbox.name))) {
                body.append(checkbox.name, checkbox.value);
            }
        });
        if (panel) {
            const input = panel.querySelector('[data-filter-input]');
            if (input && input.value.trim() !== '') {
                body.set('q', input.value.trim());
            }
            panel.querySelectorAll('[data-list-filter]').forEach(function (field) {
                if (field.name && field.value !== '') {
                    body.set(field.name, field.value);
                } else if (field.name) {
                    body.delete(field.name);
                }
            });
            body.set('limit', panel.getAttribute('data-list-limit') || '20');
            body.set('offset', '0');
        }

        setAjaxFormMessage(form, 'Виконання...', false);
        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="mdi mdi-loading mdi-spin" aria-hidden="true"></span><span>Зачекайте...</span>';
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: body,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const text = await response.text();
            let data = {};
            try {
                data = text ? JSON.parse(text) : {};
            } catch (parseError) {
                throw new Error('Сервер повернув неочікувану відповідь.');
            }
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Не вдалося виконати групову дію.');
            }

            if (form.dataset.afterSuccessUrl) {
                window.location.href = form.dataset.afterSuccessUrl;
                return;
            }

            updateListFromPayload(panel, data);
            bulkChecksForForm(form, false).forEach(function (checkbox) {
                checkbox.checked = false;
            });
            form.querySelectorAll('[data-bulk-check-all]').forEach(function (checkbox) {
                checkbox.checked = false;
            });
            if (form.id) {
                document.querySelectorAll('[data-bulk-check-all][form="' + cssEscape(form.id) + '"]').forEach(function (checkbox) {
                    checkbox.checked = false;
                });
            }
            if (action) {
                action.value = '';
            }
            setAjaxFormMessage(form, data.message || 'Групову дію виконано.', false);
        } catch (error) {
            setAjaxFormMessage(form, error.message || 'Помилка виконання групової дії.', true);
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        }
    }, true);

    initRichEditors();
    initCategoryPickers();

    document.addEventListener('submit', async function (event) {
        const form = event.target.closest('form[method="post"]:not([data-no-ajax]):not([data-section-save]):not([data-section-delete])');
        if (!form || event.defaultPrevented) {
            return;
        }

        event.preventDefault();
        syncRichEditors(form);
        const button = form.querySelector('button[type="submit"]');
        const originalHtml = button ? button.innerHTML : '';
        setAjaxFormMessage(form, 'Збереження...', false);
        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="mdi mdi-loading mdi-spin" aria-hidden="true"></span><span>Збереження...</span>';
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const text = await response.text();
            let data = {};
            try {
                data = text ? JSON.parse(text) : {};
            } catch (parseError) {
                throw new Error('Сервер повернув неочікувану відповідь.');
            }

            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Не вдалося зберегти.');
            }

            const idInput = form.querySelector('input[name="id"]');
            if (idInput && data.id) {
                idInput.value = data.id;
            }
            if (data.edit_url && window.history && window.history.replaceState) {
                window.history.replaceState(null, '', data.edit_url);
            }
            if (data.published_at) {
                const publishedInput = form.querySelector('[name="published_at"]');
                if (publishedInput) {
                    publishedInput.value = data.published_at;
                }
            }
            if (data.reset) {
                form.reset();
            }
            if (form.dataset.replaceTarget && typeof data.html === 'string') {
                const replaceTarget = document.querySelector(form.dataset.replaceTarget);
                if (replaceTarget) {
                    replaceTarget.innerHTML = data.html;
                }
            }
            if (form.dataset.optionsTarget && typeof data.options_html === 'string') {
                const optionsTarget = document.querySelector(form.dataset.optionsTarget);
                if (optionsTarget) {
                    optionsTarget.innerHTML = data.options_html;
                }
            }
            if (form.dataset.afterSuccessUrl) {
                window.location.href = form.dataset.afterSuccessUrl;
                return;
            }

            setAjaxFormMessage(form, data.message || 'Збережено.', false);
        } catch (error) {
            setAjaxFormMessage(form, error.message || 'Помилка збереження.', true);
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        }
    });

    function setAjaxFormMessage(form, message, isError) {
        let node = form.querySelector('[data-ajax-message]');
        if (!node) {
            node = document.createElement('div');
            node.setAttribute('data-ajax-message', '');
            const actions = form.querySelector('.form-actions.stacked');
            if (actions) {
                actions.appendChild(node);
            } else {
                form.appendChild(node);
            }
        }
        node.className = isError ? 'alert alert-warning mt-3 mb-0' : 'alert alert-success mt-3 mb-0';
        node.textContent = message;
    }

    function updateListFromPayload(panel, data) {
        if (!panel || typeof data.html === 'undefined') {
            return;
        }

        const target = document.querySelector(panel.getAttribute('data-list-target'));
        if (target) {
            target.innerHTML = data.html || '';
        }
        panel.setAttribute('data-list-offset', String(data.next_offset || 0));
        panel.setAttribute('data-list-has-more', data.has_more ? '1' : '0');

        const count = panel.querySelector('[data-filter-count]');
        if (count) {
            count.textContent = String(data.total || 0);
        }

        const empty = panel.querySelector('[data-list-empty]');
        if (empty && target) {
            empty.classList.toggle('d-none', target.querySelector('[data-list-row]') !== null);
        }

        const status = panel.querySelector('[data-list-status]');
        if (status) {
            const noun = panel.getAttribute('data-list-empty-label') || 'записи';
            status.textContent = data.has_more ? 'Прокрутіть нижче, щоб завантажити ще.' : 'Усі ' + noun + ' завантажено.';
        }

        if (data.stats) {
            Object.keys(data.stats).forEach(function (key) {
                document.querySelectorAll('[data-stat="' + key + '"], [data-media-stat="' + key + '"]').forEach(function (node) {
                    node.textContent = String(data.stats[key]);
                });
            });
        }
    }

    function bulkChecksForForm(form, checkedOnly, scope) {
        const root = scope || form;
        const checkSelector = checkedOnly ? '[data-bulk-check]:checked' : '[data-bulk-check]';
        const hiddenSelector = checkedOnly ? 'input[type="hidden"][name="ids[]"]' : '';
        const nodes = Array.from(root.querySelectorAll(checkSelector));
        if (hiddenSelector) {
            nodes.push(...Array.from(root.querySelectorAll(hiddenSelector)));
        }
        if (form.id) {
            const externalCheckSelector = checkedOnly ? '[data-bulk-check][form="' + cssEscape(form.id) + '"]:checked' : '[data-bulk-check][form="' + cssEscape(form.id) + '"]';
            nodes.push(...Array.from((scope || document).querySelectorAll(externalCheckSelector)));
            if (hiddenSelector) {
                nodes.push(...Array.from((scope || document).querySelectorAll('input[type="hidden"][name="ids[]"][form="' + cssEscape(form.id) + '"]')));
            }
        }
        return Array.from(new Set(nodes));
    }

    function bulkActionForForm(form) {
        return form.querySelector('[name="bulk_action"]') || (form.id ? document.querySelector('[name="bulk_action"][form="' + cssEscape(form.id) + '"]') : null);
    }

    function cssEscape(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }
        return String(value).replace(/["\\]/g, '\\$&');
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[character];
        });
    }

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('form');
        if (form) {
            syncRichEditors(form);
        }
    }, true);

    function initCategoryPickers() {
        document.querySelectorAll('[data-category-picker]').forEach(function (picker) {
            if (picker.dataset.categoryPickerReady === '1') {
                return;
            }
            picker.dataset.categoryPickerReady = '1';

            const filter = picker.querySelector('[data-category-filter]');
            const items = Array.from(picker.querySelectorAll('[data-category-item]'));
            const empty = picker.querySelector('[data-category-empty]');

            function updateSummary() {
                const selected = items.filter(function (item) {
                    const input = item.querySelector('input[type="checkbox"]');
                    return input && input.checked;
                });
                const titles = selected.map(function (item) {
                    return (item.getAttribute('data-category-title') || item.textContent || '').trim();
                });
                const count = picker.querySelector('[data-category-count]');
                const summary = picker.querySelector('[data-category-summary]');
                if (count) {
                    count.textContent = String(selected.length);
                }
                if (summary) {
                    summary.textContent = titles.length ? titles.join(', ') : 'Категорії не вибрано';
                }
            }

            function applyFilter() {
                const needle = filter ? filter.value.trim().toLowerCase() : '';
                let visible = 0;
                items.forEach(function (item) {
                    const title = (item.getAttribute('data-category-title') || item.textContent || '').toLowerCase();
                    const matches = needle === '' || title.indexOf(needle) !== -1;
                    item.hidden = !matches;
                    if (matches) {
                        visible++;
                    }
                });
                if (empty) {
                    empty.hidden = visible !== 0;
                }
            }

            picker.categoryPickerApplyFilter = applyFilter;
            picker.categoryPickerUpdateSummary = updateSummary;

            picker.addEventListener('change', function (event) {
                if (event.target.matches('input[type="checkbox"]')) {
                    updateSummary();
                }
            });
            if (filter) {
                filter.addEventListener('input', applyFilter);
                filter.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                    }
                });
            }

            updateSummary();
            applyFilter();
        });
    }

    document.addEventListener('input', function (event) {
        const filter = event.target.closest('[data-category-filter]');
        const picker = filter ? filter.closest('[data-category-picker]') : null;
        if (picker && typeof picker.categoryPickerApplyFilter === 'function') {
            picker.categoryPickerApplyFilter();
        } else if (picker) {
            const needle = filter.value.trim().toLowerCase();
            let visible = 0;
            picker.querySelectorAll('[data-category-item]').forEach(function (item) {
                const title = (item.getAttribute('data-category-title') || item.textContent || '').toLowerCase();
                const matches = needle === '' || title.indexOf(needle) !== -1;
                item.hidden = !matches;
                if (matches) {
                    visible++;
                }
            });
            const empty = picker.querySelector('[data-category-empty]');
            if (empty) {
                empty.hidden = visible !== 0;
            }
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && event.target.closest('[data-category-filter]')) {
            event.preventDefault();
        }
    });

    function initRichEditors() {
        document.querySelectorAll('textarea[data-rich-editor]').forEach(function (textarea) {
            if (textarea.dataset.richEditorReady === '1') {
                return;
            }
            textarea.dataset.richEditorReady = '1';
            textarea.classList.add('rich-editor-source');
            textarea.required = false;

            const editor = document.createElement('div');
            editor.className = 'rich-editor';
            editor.innerHTML =
                '<div class="rich-editor-toolbar" aria-label="Панель форматування">' +
                    '<select data-rich-command="formatBlock" title="Стиль">' +
                        '<option value="p">Абзац</option>' +
                        '<option value="h2">Заголовок 2</option>' +
                        '<option value="h3">Заголовок 3</option>' +
                        '<option value="blockquote">Цитата</option>' +
                    '</select>' +
                    '<button type="button" data-rich-command="bold" title="Жирний"><span class="mdi mdi-format-bold" aria-hidden="true"></span></button>' +
                    '<button type="button" data-rich-command="italic" title="Курсив"><span class="mdi mdi-format-italic" aria-hidden="true"></span></button>' +
                    '<button type="button" data-rich-command="underline" title="Підкреслення"><span class="mdi mdi-format-underline" aria-hidden="true"></span></button>' +
                    '<button type="button" data-rich-command="insertUnorderedList" title="Маркований список"><span class="mdi mdi-format-list-bulleted" aria-hidden="true"></span></button>' +
                    '<button type="button" data-rich-command="insertOrderedList" title="Нумерований список"><span class="mdi mdi-format-list-numbered" aria-hidden="true"></span></button>' +
                    '<button type="button" data-rich-command="justifyLeft" title="Ліворуч"><span class="mdi mdi-format-align-left" aria-hidden="true"></span></button>' +
                    '<button type="button" data-rich-command="justifyCenter" title="По центру"><span class="mdi mdi-format-align-center" aria-hidden="true"></span></button>' +
                    '<button type="button" data-rich-command="justifyRight" title="Праворуч"><span class="mdi mdi-format-align-right" aria-hidden="true"></span></button>' +
                    '<span class="rich-editor-divider" aria-hidden="true"></span>' +
                    '<button type="button" data-rich-media-open title="Медіафайл"><span class="mdi mdi-image-plus-outline" aria-hidden="true"></span></button>' +
                    '<button type="button" data-rich-align="left" title="Медіа ліворуч"><span class="mdi mdi-image-align-left" aria-hidden="true"></span></button>' +
                    '<button type="button" data-rich-align="center" title="Медіа по центру"><span class="mdi mdi-image-align-center" aria-hidden="true"></span></button>' +
                    '<button type="button" data-rich-align="right" title="Медіа праворуч"><span class="mdi mdi-image-align-right" aria-hidden="true"></span></button>' +
                    '<button type="button" data-rich-align="wide" title="Медіа на всю ширину"><span class="mdi mdi-arrow-expand-horizontal" aria-hidden="true"></span></button>' +
                    '<span class="rich-editor-divider" aria-hidden="true"></span>' +
                    '<button type="button" data-rich-link title="Посилання"><span class="mdi mdi-link-variant" aria-hidden="true"></span></button>' +
                    '<button type="button" data-rich-command="removeFormat" title="Очистити формат"><span class="mdi mdi-format-clear" aria-hidden="true"></span></button>' +
                    '<button type="button" data-rich-source-toggle title="Джерело HTML"><span class="mdi mdi-code-tags" aria-hidden="true"></span></button>' +
                '</div>' +
                '<div class="rich-editor-area" contenteditable="true"></div>' +
                '<textarea class="rich-editor-code" spellcheck="false" aria-label="HTML джерело"></textarea>';

            const area = editor.querySelector('.rich-editor-area');
            const code = editor.querySelector('.rich-editor-code');
            area.innerHTML = textarea.value.trim() === '' ? '' : textarea.value;
            code.value = textarea.value;
            textarea.insertAdjacentElement('afterend', editor);

            editor.querySelectorAll('[data-rich-command]').forEach(function (control) {
                const eventName = control.tagName === 'SELECT' ? 'change' : 'click';
                control.addEventListener(eventName, function () {
                    runRichCommand(control.getAttribute('data-rich-command'), control.value || null, area, textarea);
                });
            });

            editor.querySelector('[data-rich-link]').addEventListener('click', function () {
                area.focus();
                const href = window.prompt('URL посилання');
                if (href) {
                    document.execCommand('createLink', false, href);
                    syncRichEditor(textarea);
                }
            });

            editor.querySelector('[data-rich-media-open]').addEventListener('click', function () {
                openRichMediaModal(area, textarea);
            });

            editor.querySelectorAll('[data-rich-align]').forEach(function (control) {
                control.addEventListener('click', function () {
                    setSelectedMediaAlign(area, control.getAttribute('data-rich-align'));
                    syncRichEditor(textarea);
                });
            });

            editor.querySelector('[data-rich-source-toggle]').addEventListener('click', function () {
                toggleRichSourceMode(editor, textarea);
            });

            area.addEventListener('input', function () {
                syncRichEditor(textarea);
            });
            area.addEventListener('click', function (event) {
                const media = event.target.closest('.rich-media-block, .rich-gallery, img, a');
                area.querySelectorAll('.rich-media-selected').forEach(function (node) {
                    node.classList.remove('rich-media-selected');
                });
                const block = richMediaBlock(media);
                if (block) {
                    block.classList.add('rich-media-selected');
                }
            });
            code.addEventListener('input', function () {
                textarea.value = code.value;
            });
            syncRichEditor(textarea);
        });
    }

    function runRichCommand(command, value, area, textarea) {
        area.focus();
        document.execCommand(command, false, value);
        syncRichEditor(textarea);
    }

    function toggleRichSourceMode(editor, textarea) {
        const area = editor.querySelector('.rich-editor-area');
        const code = editor.querySelector('.rich-editor-code');
        const button = editor.querySelector('[data-rich-source-toggle]');
        const sourceMode = !editor.classList.contains('is-source-mode');

        if (sourceMode) {
            code.value = area.innerHTML.trim();
        } else {
            area.innerHTML = code.value;
        }
        textarea.value = sourceMode ? code.value : area.innerHTML.trim();
        editor.classList.toggle('is-source-mode', sourceMode);
        button.classList.toggle('is-active', sourceMode);
        (sourceMode ? code : area).focus();
    }

    const richMediaState = {
        modal: null,
        area: null,
        textarea: null,
        items: [],
        selected: new Set(),
        loading: false,
        searchTimer: null
    };

    function openRichMediaModal(area, textarea) {
        const modalNode = document.getElementById('richMediaModal');
        if (!modalNode || !window.bootstrap) {
            return;
        }

        richMediaState.area = area;
        richMediaState.textarea = textarea;
        richMediaState.selected = new Set();
        richMediaState.modal = richMediaState.modal || new window.bootstrap.Modal(modalNode);
        modalNode.querySelector('[data-rich-media-caption]').value = '';
        modalNode.querySelector('[data-rich-media-mode]').value = 'single';
        modalNode.querySelector('[data-rich-media-align]').value = 'center';
        bindRichMediaModal(modalNode);
        richMediaState.modal.show();
        loadRichMediaItems();
    }

    function bindRichMediaModal(modalNode) {
        if (modalNode.dataset.richMediaBound === '1') {
            return;
        }
        modalNode.dataset.richMediaBound = '1';

        modalNode.querySelector('[data-rich-media-search]').addEventListener('input', function () {
            window.clearTimeout(richMediaState.searchTimer);
            richMediaState.searchTimer = window.setTimeout(loadRichMediaItems, 250);
        });
        modalNode.querySelector('[data-rich-media-mode]').addEventListener('change', function () {
            if (this.value === 'single' && richMediaState.selected.size > 1) {
                const first = Array.from(richMediaState.selected)[0];
                richMediaState.selected = new Set([first]);
                renderRichMediaItems();
            }
        });
        modalNode.querySelector('[data-rich-media-insert]').addEventListener('click', insertSelectedRichMedia);
        modalNode.querySelector('[data-rich-media-upload]').addEventListener('change', uploadRichMediaFile);
    }

    async function loadRichMediaItems() {
        const modalNode = document.getElementById('richMediaModal');
        const grid = modalNode.querySelector('[data-rich-media-grid]');
        const status = modalNode.querySelector('[data-rich-media-status]');
        const search = modalNode.querySelector('[data-rich-media-search]').value.trim();
        const url = new URL('<?= url('/admin/media/picker') ?>', window.location.origin);
        url.searchParams.set('limit', '80');
        if (search !== '') {
            url.searchParams.set('q', search);
        }

        richMediaState.loading = true;
        status.textContent = 'Завантаження...';
        grid.innerHTML = '';

        try {
            const response = await fetch(url.toString(), {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Не вдалося завантажити медіафайли.');
            }
            richMediaState.items = data.items || [];
            renderRichMediaItems();
            status.textContent = richMediaState.items.length ? 'Знайдено файлів: ' + data.total + '.' : 'Файлів не знайдено.';
        } catch (error) {
            status.textContent = error.message || 'Помилка завантаження.';
        } finally {
            richMediaState.loading = false;
        }
    }

    function renderRichMediaItems() {
        const modalNode = document.getElementById('richMediaModal');
        const grid = modalNode.querySelector('[data-rich-media-grid]');
        const mode = modalNode.querySelector('[data-rich-media-mode]').value;
        grid.innerHTML = '';

        richMediaState.items.forEach(function (item) {
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'rich-media-card' + (richMediaState.selected.has(item.path) ? ' is-selected' : '');
            card.innerHTML = (item.is_image
                ? '<img src="' + escapeHtml(item.url) + '" alt="">'
                : '<span class="mdi mdi-file-outline rich-media-file-icon" aria-hidden="true"></span>') +
                '<span class="rich-media-card-name">' + escapeHtml(item.name) + '</span>' +
                '<small>' + escapeHtml(item.type) + ' · ' + escapeHtml(item.size_label) + '</small>';
            card.addEventListener('click', function () {
                if (mode === 'single') {
                    richMediaState.selected = new Set([item.path]);
                } else {
                    if (!item.is_image) {
                        return;
                    }
                    if (richMediaState.selected.has(item.path)) {
                        richMediaState.selected.delete(item.path);
                    } else {
                        richMediaState.selected.add(item.path);
                    }
                }
                renderRichMediaItems();
            });
            grid.appendChild(card);
        });
    }

    async function uploadRichMediaFile(event) {
        const input = event.target;
        const file = input.files && input.files[0] ? input.files[0] : null;
        if (!file) {
            return;
        }

        const modalNode = document.getElementById('richMediaModal');
        const status = modalNode.querySelector('[data-rich-media-status]');
        const formData = new FormData();
        formData.append('_csrf', adminCsrfToken);
        formData.append('file', file);
        status.textContent = 'Завантаження файлу...';

        try {
            const response = await fetch('<?= url('/admin/media/upload') ?>', {
                method: 'POST',
                body: formData,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Не вдалося завантажити файл.');
            }
            richMediaState.selected = new Set(data.uploaded_path ? [data.uploaded_path] : []);
            input.value = '';
            await loadRichMediaItems();
            status.textContent = 'Файл завантажено.';
        } catch (error) {
            status.textContent = error.message || 'Помилка завантаження.';
        }
    }

    function insertSelectedRichMedia() {
        const modalNode = document.getElementById('richMediaModal');
        const selected = richMediaState.items.filter(function (item) {
            return richMediaState.selected.has(item.path);
        });
        if (!selected.length || !richMediaState.area || !richMediaState.textarea) {
            modalNode.querySelector('[data-rich-media-status]').textContent = 'Оберіть файл для вставки.';
            return;
        }

        const mode = modalNode.querySelector('[data-rich-media-mode]').value;
        const align = modalNode.querySelector('[data-rich-media-align]').value;
        const columns = modalNode.querySelector('[data-rich-media-columns]').value;
        const caption = modalNode.querySelector('[data-rich-media-caption]').value.trim();
        const html = mode === 'gallery'
            ? buildRichGalleryHtml(selected.filter(function (item) { return item.is_image; }), align, columns, caption)
            : buildRichMediaHtml(selected[0], align, caption);
        if (html === '') {
            modalNode.querySelector('[data-rich-media-status]').textContent = 'Для галереї оберіть зображення.';
            return;
        }

        richMediaState.area.focus();
        document.execCommand('insertHTML', false, html);
        syncRichEditor(richMediaState.textarea);
        richMediaState.modal.hide();
    }

    function buildRichMediaHtml(item, align, caption) {
        if (item.is_image) {
            return '<figure class="rich-media-block media-align-' + escapeHtml(align) + '"><img src="' + escapeHtml(item.url) + '" alt="' + escapeHtml(caption || item.name) + '">' +
                (caption ? '<figcaption>' + escapeHtml(caption) + '</figcaption>' : '') + '</figure>';
        }

        return '<p class="rich-file-link media-align-' + escapeHtml(align) + '"><a href="' + escapeHtml(item.url) + '" target="_blank" rel="noopener">' +
            escapeHtml(caption || item.name) + '</a></p>';
    }

    function buildRichGalleryHtml(items, align, columns, caption) {
        if (!items.length) {
            return '';
        }

        const images = items.map(function (item) {
            return '<figure><img src="' + escapeHtml(item.url) + '" alt="' + escapeHtml(item.name) + '"></figure>';
        }).join('');

        return '<div class="rich-gallery rich-gallery-cols-' + escapeHtml(columns) + ' media-align-' + escapeHtml(align) + '">' + images + '</div>' +
            (caption ? '<p class="rich-gallery-caption media-align-' + escapeHtml(align) + '">' + escapeHtml(caption) + '</p>' : '');
    }

    function setSelectedMediaAlign(area, align) {
        const selection = window.getSelection();
        let node = area.querySelector('.rich-media-selected');
        if (!node && selection && selection.rangeCount) {
            const anchor = selection.anchorNode;
            node = anchor ? richMediaBlock(anchor.nodeType === 1 ? anchor : anchor.parentElement) : null;
        }
        node = richMediaBlock(node);
        if (!node) {
            return;
        }

        ['media-align-left', 'media-align-center', 'media-align-right', 'media-align-wide'].forEach(function (className) {
            node.classList.remove(className);
        });
        node.classList.add('media-align-' + align);
    }

    function richMediaBlock(node) {
        if (!node) {
            return null;
        }
        const element = node.nodeType === 1 ? node : node.parentElement;
        if (!element) {
            return null;
        }
        return element.closest('.rich-media-block, .rich-gallery, .rich-gallery-caption, .rich-file-link') || (element.tagName === 'IMG' ? element.closest('figure') : null);
    }

    function syncRichEditors(root) {
        (root || document).querySelectorAll('textarea[data-rich-editor]').forEach(syncRichEditor);
    }

    function syncRichEditor(textarea) {
        const editor = textarea.nextElementSibling;
        const area = editor ? editor.querySelector('.rich-editor-area') : null;
        const code = editor ? editor.querySelector('.rich-editor-code') : null;
        if (editor && editor.classList.contains('is-source-mode') && code) {
            textarea.value = code.value;
            return;
        }
        if (area) {
            textarea.value = area.innerHTML.trim();
            if (code) {
                code.value = textarea.value;
            }
        }
    }
    </script>
</body>
</html>
