    const adminBody = document.body;
    const adminCsrfToken = adminBody ? (adminBody.dataset.adminCsrfToken || '') : '';
    document.addEventListener('submit', function (event) {
        const form = event.target.closest('[data-optimizer-confirm]');
        if (!form) {
            return;
        }

        if (!window.confirm(form.dataset.optimizerConfirm || 'Виконати дію?')) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);

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
        let visibilityCheckScheduled = false;

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

        function isSentinelNearViewport() {
            const rect = sentinel.getBoundingClientRect();
            const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
            return rect.top <= viewportHeight + 320;
        }

        function checkForNextPage() {
            visibilityCheckScheduled = false;
            if (loading || panel.getAttribute('data-list-has-more') !== '1') {
                return;
            }
            if (isSentinelNearViewport()) {
                loadList(false);
            }
        }

        function scheduleNextPageCheck() {
            if (visibilityCheckScheduled) {
                return;
            }
            visibilityCheckScheduled = true;
            window.requestAnimationFrame(checkForNextPage);
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
            let loadedSuccessfully = false;
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
                loadedSuccessfully = true;
            } catch (error) {
                if (error.name !== 'AbortError') {
                    setStatus(error.message || 'Помилка завантаження.');
                }
            } finally {
                if (activeRequest === controller) {
                    loading = false;
                    activeRequest = null;
                    if (loadedSuccessfully) {
                        scheduleNextPageCheck();
                    }
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
                scheduleNextPageCheck();
            }
        }, {rootMargin: '320px'});

        window.addEventListener('scroll', scheduleNextPageCheck, {passive: true});
        window.addEventListener('resize', scheduleNextPageCheck, {passive: true});
        panel.addEventListener('admin:check-list', scheduleNextPageCheck);

        panel.addEventListener('admin:reload-list', function () {
            panel.setAttribute('data-list-offset', '0');
            panel.setAttribute('data-list-has-more', '1');
            loadList(true);
        });

        observer.observe(sentinel);
        syncEmpty();
        scheduleNextPageCheck();
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

    initTiptapEditors();
    initCategoryPickers();

    document.addEventListener('submit', async function (event) {
        const form = event.target.closest('form[method="post"]:not([data-no-ajax]):not([data-section-save]):not([data-section-delete])');
        if (!form || event.defaultPrevented) {
            return;
        }

        event.preventDefault();
        syncTiptapEditors(form);
        const button = form.querySelector('button[type="submit"]');
        const originalHtml = button ? button.innerHTML : '';
        const isOptimizerServiceAction = form.matches('[data-optimizer-service-action]');
        const pendingMessage = form.dataset.pendingMessage || 'Збереження...';
        const pendingButtonLabel = form.dataset.pendingButtonLabel || pendingMessage;
        if (!isOptimizerServiceAction) {
            setAjaxFormMessage(form, pendingMessage, false, true);
        }
        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="mdi mdi-loading mdi-spin" aria-hidden="true"></span><span>' + escapeHtml(pendingButtonLabel) + '</span>';
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
            if (data.clear_password_fields) {
                form.querySelectorAll('input[type="password"]').forEach(function (input) {
                    input.value = '';
                });
            }
            if (data.user_name) {
                document.querySelectorAll('[data-admin-user-name], [data-profile-summary-name]').forEach(function (node) {
                    node.textContent = data.user_name;
                });
            }
            if (data.user_email) {
                document.querySelectorAll('[data-profile-summary-email]').forEach(function (node) {
                    node.textContent = data.user_email;
                });
            }
            if (form.dataset.replaceTarget && typeof data.html === 'string') {
                const replaceTarget = document.querySelector(form.dataset.replaceTarget);
                if (replaceTarget) {
                    replaceTarget.innerHTML = data.html;
                    document.dispatchEvent(new CustomEvent('admin:content-replaced', {detail: {target: replaceTarget}}));
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

            document.dispatchEvent(new CustomEvent('admin:form-saved', {detail: {form: form, data: data}}));
            if (!isOptimizerServiceAction) {
                setAjaxFormMessage(form, data.message || 'Збережено.', false);
            }
        } catch (error) {
            const errorMessage = error.message || 'Помилка збереження.';
            if (isOptimizerServiceAction) {
                document.dispatchEvent(new CustomEvent('admin:form-error', {detail: {form: form, message: errorMessage}}));
            } else {
                setAjaxFormMessage(form, errorMessage, true);
            }
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        }
    });

    function setAjaxFormMessage(form, message, isError, isPending) {
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
        node.className = isError ? 'alert alert-warning mt-3 mb-0' : (isPending ? 'alert alert-info mt-3 mb-0' : 'alert alert-success mt-3 mb-0');
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
        panel.dispatchEvent(new CustomEvent('admin:check-list'));
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
            syncTiptapEditors(form);
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

    function initNewsCategoryManager() {
        const rowsNode = document.querySelector('[data-news-category-rows]');
        const filter = document.querySelector('[data-news-category-filter]');
        if (!rowsNode || !filter) {
            return;
        }

        const visibleNode = document.querySelector('[data-news-category-visible]');
        const emptyNode = document.querySelector('[data-news-category-empty]');
        const totalNode = document.querySelector('[data-category-total]');
        const usedNode = document.querySelector('[data-category-used]');
        const parentsNode = document.querySelector('[data-category-parents]');
        const linksNode = document.querySelector('[data-category-links]');

        function rows() {
            return Array.from(rowsNode.querySelectorAll('[data-news-category-row]'));
        }

        function updateStats() {
            let used = 0;
            let parents = 0;
            let links = 0;
            const currentRows = rows();
            currentRows.forEach(function (row) {
                const newsCount = Number(row.dataset.categoryNewsCount || 0);
                const childrenCount = Number(row.dataset.categoryChildrenCount || 0);
                used += newsCount > 0 ? 1 : 0;
                parents += childrenCount > 0 ? 1 : 0;
                links += newsCount;
            });
            if (totalNode) {
                totalNode.textContent = String(currentRows.length);
            }
            if (usedNode) {
                usedNode.textContent = String(used);
            }
            if (parentsNode) {
                parentsNode.textContent = String(parents);
            }
            if (linksNode) {
                linksNode.textContent = String(links);
            }
        }

        function applyFilter() {
            const value = filter.value.trim().toLowerCase();
            const currentRows = rows();
            let visible = 0;
            currentRows.forEach(function (row) {
                const haystack = row.dataset.categorySearch || row.textContent.toLowerCase();
                const match = value === '' || haystack.includes(value);
                row.hidden = !match;
                visible += match ? 1 : 0;
            });
            if (visibleNode) {
                visibleNode.textContent = visible + ' показано';
            }
            if (emptyNode) {
                emptyNode.hidden = visible !== 0 || currentRows.length === 0;
            }
        }

        rowsNode.newsCategoryRefresh = function () {
            updateStats();
            applyFilter();
        };
        rowsNode.newsCategoryRefresh();
    }

    document.addEventListener('input', function (event) {
        if (!event.target.closest('[data-news-category-filter]')) {
            return;
        }
        const rowsNode = document.querySelector('[data-news-category-rows]');
        if (rowsNode && typeof rowsNode.newsCategoryRefresh === 'function') {
            rowsNode.newsCategoryRefresh();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && event.target.closest('[data-news-category-filter]')) {
            event.preventDefault();
        }
    });

    document.addEventListener('admin:content-replaced', function (event) {
        if (event.detail && event.detail.target && event.detail.target.matches('[data-news-category-rows]')) {
            initNewsCategoryManager();
        }
    });

    initNewsCategoryManager();

    function initOptimizerTabs() {
        const buttons = Array.from(document.querySelectorAll('[data-optimizer-tab]'));
        const panels = Array.from(document.querySelectorAll('[data-optimizer-tab-panel]'));
        const openMediaButton = document.querySelector('[data-optimizer-open-media]');
        const mediaLaunch = document.querySelector('[data-optimizer-media-launch]');
        if (buttons.length === 0 || panels.length === 0) {
            return;
        }

        buttons.forEach(function (button) {
            button.dataset.optimizerPermanentlyDisabled = button.disabled ? '1' : '0';
        });

        function setAnalysisButtonLoading(isLoading) {
            buttons.forEach(function (button) {
                const icon = button.querySelector('.mdi');
                const label = button.querySelector('[data-optimizer-media-button-label]');
                button.disabled = isLoading || button.dataset.optimizerPermanentlyDisabled === '1';
                button.setAttribute('aria-busy', isLoading ? 'true' : 'false');
                if (icon) {
                    icon.className = isLoading ? 'mdi mdi-loading mdi-spin' : 'mdi mdi-refresh';
                }
                if (label) {
                    label.textContent = isLoading ? 'Аналізуємо…' : 'Оновити аналіз';
                }
            });
        }

        function showAnalysisLoading(panel) {
            panel.innerHTML = '<div class="list-panel optimizer-analysis-placeholder"><div class="empty-state optimizer-loading-state" data-optimizer-tab-status><span class="optimizer-loader" aria-hidden="true"></span><strong>Аналізуємо медіафайли</strong><span>Це може зайняти кілька секунд.</span></div></div>';
        }

        function setServiceMessage(grid, payload, isError) {
            document.querySelectorAll('[data-optimizer-service-message]').forEach(function (node) {
                node.remove();
            });
            const message = typeof payload === 'string' ? payload : (payload && payload.message ? payload.message : '');
            if (!grid || !message) {
                return;
            }

            const node = document.createElement('div');
            node.setAttribute('data-optimizer-service-message', '');
            node.className = 'optimizer-service-notice ' + (isError ? 'is-danger' : 'is-' + ((payload && payload.message_tone) || 'success'));
            node.setAttribute('role', isError ? 'alert' : 'status');

            const icon = document.createElement('span');
            icon.className = 'mdi ' + (isError ? 'mdi-alert-circle-outline' : ((payload && payload.message_icon) || 'mdi-check-circle-outline'));
            icon.setAttribute('aria-hidden', 'true');

            const copy = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = isError ? 'Не вдалося виконати дію' : ((payload && payload.message_title) || 'Дію виконано');
            const detail = document.createElement('span');
            detail.textContent = message;
            copy.append(title, detail);

            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'optimizer-service-notice-close';
            close.setAttribute('aria-label', 'Закрити повідомлення');
            close.innerHTML = '<span class="mdi mdi-close" aria-hidden="true"></span>';
            close.addEventListener('click', function () {
                node.remove();
            });

            node.append(icon, copy, close);
            grid.insertAdjacentElement('beforebegin', node);
        }

        async function refreshServiceGrid(payload) {
            const grid = document.querySelector('.optimizer-service-grid');
            if (!grid) {
                return;
            }

            const response = await fetch(window.location.href, {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const html = await response.text();
            if (!response.ok || !html) {
                throw new Error('Не вдалося оновити стан карток.');
            }

            const doc = new DOMParser().parseFromString(html, 'text/html');
            const freshGrid = doc.querySelector('.optimizer-service-grid');
            if (!freshGrid) {
                throw new Error('Не вдалося знайти оновлені картки.');
            }

            grid.replaceWith(freshGrid);
            setServiceMessage(freshGrid, payload || {}, false);
        }

        async function loadPanel(panel, force) {
            if (!panel || (!force && panel.dataset.loaded === '1')) {
                return;
            }

            showAnalysisLoading(panel);
            setAnalysisButtonLoading(true);

            try {
                const response = await fetch(panel.dataset.loadUrl || '', {
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                });
                const data = await response.json();
                if (!response.ok || !data.ok) {
                    throw new Error(data.message || 'Не вдалося завантажити вкладку.');
                }

                panel.innerHTML = data.html || '';
                panel.dataset.loaded = '1';
                document.dispatchEvent(new CustomEvent('admin:content-replaced', {detail: {target: panel}}));
            } catch (error) {
                panel.dataset.loaded = '0';
                panel.innerHTML = '<div class="optimizer-analysis-error" role="alert"><span class="mdi mdi-alert-circle-outline" aria-hidden="true"></span><div><strong>Не вдалося виконати аналіз</strong><span>' + escapeHtml(error.message || 'Спробуйте ще раз.') + '</span></div><button class="button secondary compact" type="button" data-optimizer-retry><span class="mdi mdi-refresh" aria-hidden="true"></span><span>Повторити</span></button></div>';
            } finally {
                setAnalysisButtonLoading(false);
            }
        }

        function activate(name, force) {
            buttons.forEach(function (button) {
                const active = button.dataset.optimizerTab === name;
                button.setAttribute('aria-expanded', active ? 'true' : 'false');
            });
            panels.forEach(function (panel) {
                const active = panel.dataset.optimizerTabPanel === name;
                panel.hidden = !active;
                if (active) {
                    loadPanel(panel, !!force);
                }
            });
            if (mediaLaunch) {
                mediaLaunch.classList.toggle('is-active', name === 'media');
            }
        }

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                const panel = panels.find(function (item) {
                    return item.dataset.optimizerTabPanel === button.dataset.optimizerTab;
                });
                activate(button.dataset.optimizerTab || '', !!panel && panel.dataset.loaded === '1');
            });
        });

        if (openMediaButton) {
            openMediaButton.addEventListener('click', function () {
                const mediaButton = buttons.find(function (button) {
                    return button.dataset.optimizerTab === 'media';
                });
                if (mediaLaunch) {
                    mediaLaunch.scrollIntoView({behavior: 'smooth', block: 'center'});
                }
                if (mediaButton && !mediaButton.disabled) {
                    mediaButton.click();
                }
            });
        }

        document.addEventListener('click', function (event) {
            const retry = event.target.closest('[data-optimizer-retry]');
            if (!retry) {
                return;
            }
            const panel = retry.closest('[data-optimizer-tab-panel]');
            loadPanel(panel, true);
        });

        const activeButton = buttons.find(function (button) {
            return button.getAttribute('aria-expanded') === 'true';
        });
        if (activeButton) {
            activate(activeButton.dataset.optimizerTab || '');
        }

        document.addEventListener('admin:form-saved', function (event) {
            const form = event.detail && event.detail.form;
            if (!form) {
                return;
            }

            if (form.matches('[data-optimizer-media-apply]')) {
                const panel = document.querySelector('[data-optimizer-tab-panel="media"]');
                if (panel) {
                    loadPanel(panel, true);
                }
                return;
            }

            if (form.matches('[data-optimizer-service-action]')) {
                const data = event.detail && event.detail.data ? event.detail.data : {};
                refreshServiceGrid(data).catch(function (error) {
                    setServiceMessage(document.querySelector('.optimizer-service-grid'), error.message || 'Не вдалося оновити стан карток.', true);
                });
            }
        });

        document.addEventListener('admin:form-error', function (event) {
            const form = event.detail && event.detail.form;
            if (!form || !form.matches('[data-optimizer-service-action]')) {
                return;
            }
            setServiceMessage(document.querySelector('.optimizer-service-grid'), event.detail.message || 'Спробуйте ще раз.', true);
        });
    }

    initOptimizerTabs();

    function initTiptapEditors() {
        if (window.TiptapEditor) {
            window.TiptapEditor.init();
        }
    }

    function syncTiptapEditors(root) {
        if (window.TiptapEditor) {
            window.TiptapEditor.syncAll(root);
        }
    }

    function syncTiptapEditor(textarea) {
        if (window.TiptapEditor) {
            window.TiptapEditor.syncOne(textarea);
        }
    }
