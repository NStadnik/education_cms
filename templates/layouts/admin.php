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
            <form method="post" action="<?= url('/admin/logout') ?>" class="mt-4" data-no-ajax>
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

    document.addEventListener('submit', async function (event) {
        const form = event.target.closest('form[method="post"]:not([data-no-ajax]):not([data-section-save]):not([data-section-delete])');
        if (!form || event.defaultPrevented) {
            return;
        }

        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        const originalText = button ? button.textContent : '';
        setAjaxFormMessage(form, 'Збереження...', false);
        if (button) {
            button.disabled = true;
            button.textContent = 'Збереження...';
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

            setAjaxFormMessage(form, data.message || 'Збережено.', false);
        } catch (error) {
            setAjaxFormMessage(form, error.message || 'Помилка збереження.', true);
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = originalText;
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
    </script>
</body>
</html>
