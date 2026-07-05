<!doctype html>
<?php
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH) ?: '/admin';
    $adminNav = [
        ['/admin', 'Огляд', 'mdi-view-dashboard-outline'],
        ['/admin/pages', 'Сторінки', 'mdi-file-document-edit-outline'],
        ['/admin/news', 'Новини', 'mdi-newspaper-variant-outline'],
        ['/admin/documents', 'Документи', 'mdi-file-cabinet'],
        ['/admin/media', 'Медіафайли', 'mdi-image-multiple-outline'],
        ['/admin/public-info', 'Публічна інформація', 'mdi-folder-information-outline'],
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('form');
        if (form) {
            syncRichEditors(form);
        }
    }, true);

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
                    '<button type="button" data-rich-link title="Посилання"><span class="mdi mdi-link-variant" aria-hidden="true"></span></button>' +
                    '<button type="button" data-rich-command="removeFormat" title="Очистити формат"><span class="mdi mdi-format-clear" aria-hidden="true"></span></button>' +
                '</div>' +
                '<div class="rich-editor-area" contenteditable="true"></div>';

            const area = editor.querySelector('.rich-editor-area');
            area.innerHTML = textarea.value.trim() === '' ? '' : textarea.value;
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

            area.addEventListener('input', function () {
                syncRichEditor(textarea);
            });
            syncRichEditor(textarea);
        });
    }

    function runRichCommand(command, value, area, textarea) {
        area.focus();
        document.execCommand(command, false, value);
        syncRichEditor(textarea);
    }

    function syncRichEditors(root) {
        (root || document).querySelectorAll('textarea[data-rich-editor]').forEach(syncRichEditor);
    }

    function syncRichEditor(textarea) {
        const editor = textarea.nextElementSibling;
        const area = editor ? editor.querySelector('.rich-editor-area') : null;
        if (area) {
            textarea.value = area.innerHTML.trim();
        }
    }
    </script>
</body>
</html>
