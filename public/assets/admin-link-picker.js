(function () {
    let modal = null;
    let modalNode = null;
    let state = {
        type: 'pages',
        offset: 0,
        limit: 20,
        total: 0,
        hasMore: false,
        loading: false,
        searchTimer: null,
        selected: new Map(),
        multiple: false,
        onSelect: null,
        token: 0,
        view: 'list'
    };

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (character) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character];
        });
    }

    function itemKey(item) {
        return String(item.url || '') + '|' + String(item.label || '');
    }

    function iconForType(type) {
        return {
            pages: 'mdi-file-document-outline',
            categories: 'mdi-shape-outline',
            news: 'mdi-newspaper-variant-outline',
            media: 'mdi-file-image-outline'
        }[type] || 'mdi-link-variant';
    }

    function node(selector) {
        return modalNode ? modalNode.querySelector(selector) : null;
    }

    function setStatus(message) {
        const status = node('[data-admin-link-picker-status-text]');
        if (status) {
            status.textContent = message;
        }
    }

    function updateSelection() {
        const count = state.selected.size;
        const apply = node('[data-admin-link-picker-apply]');
        const label = node('[data-admin-link-picker-apply-label]');
        if (apply) {
            apply.disabled = count === 0;
        }
        if (label) {
            label.textContent = state.multiple
                ? (count ? 'Додати вибрані (' + count + ')' : 'Додати вибрані')
                : 'Обрати';
        }
        modalNode.querySelectorAll('.admin-link-picker-item').forEach(function (button) {
            const selected = state.selected.has(itemKey(button.dataset));
            button.classList.toggle('is-selected', selected);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            const stateIcon = button.querySelector('[data-admin-link-picker-state-icon]');
            if (stateIcon) {
                stateIcon.className = 'admin-link-picker-state mdi ' + (selected ? 'mdi-check-circle' : 'mdi-plus-circle-outline');
            }
        });
    }

    function clearSelection() {
        state.selected.clear();
        updateSelection();
    }

    function setType(type) {
        state.type = ['pages', 'categories', 'news', 'media'].indexOf(type) !== -1 ? type : 'pages';
        clearSelection();
        modalNode.querySelectorAll('[data-admin-link-picker-type]').forEach(function (button) {
            button.classList.toggle('secondary', button.dataset.adminLinkPickerType !== state.type);
        });
        const statusFilter = node('[data-admin-link-picker-filter="status"]');
        const scopeFilter = node('[data-admin-link-picker-filter="scope"]');
        if (statusFilter) {
            statusFilter.hidden = state.type === 'categories' || state.type === 'media';
        }
        if (scopeFilter) {
            scopeFilter.hidden = state.type !== 'categories';
        }
        const viewWrap = node('[data-admin-link-picker-view-wrap]');
        if (viewWrap) {
            viewWrap.hidden = state.type !== 'media';
        }
        updateViewButtons();
        reset();
    }

    function reset() {
        state.offset = 0;
        state.total = 0;
        state.hasMore = false;
        load(false);
    }

    async function load(append) {
        if (!modalNode || state.loading) {
            return;
        }
        const list = node('[data-admin-link-picker-list]');
        const more = node('[data-admin-link-picker-more]');
        const search = node('[data-admin-link-picker-search]');
        const status = node('[data-admin-link-picker-status]');
        const scope = node('[data-admin-link-picker-scope]');
        const endpoint = document.body.dataset.adminLinkPickerUrl || '/admin/link-picker';
        const url = new URL(endpoint, window.location.origin);
        const token = append ? state.token : ++state.token;
        url.searchParams.set('type', state.type);
        url.searchParams.set('limit', String(state.limit));
        url.searchParams.set('offset', append ? String(state.offset) : '0');
        if (search && search.value.trim() !== '') {
            url.searchParams.set('q', search.value.trim());
        }
        if (state.type === 'categories' && scope && scope.value !== '') {
            url.searchParams.set('scope', scope.value);
        } else if ((state.type === 'pages' || state.type === 'news') && status) {
            url.searchParams.set('status', status.value);
        }

        state.loading = true;
        if (!append) {
            list.innerHTML = '';
            state.offset = 0;
        }
        if (more) {
            more.hidden = true;
        }
        setStatus('Завантаження...');

        try {
            const response = await fetch(url.toString(), {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            const data = await response.json();
            if (token !== state.token) {
                return;
            }
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Не вдалося завантажити посилання.');
            }
            state.offset = Number(data.next_offset || 0);
            state.total = Number(data.total || 0);
            state.hasMore = Boolean(data.has_more);
            renderItems(Array.isArray(data.items) ? data.items : [], append);
            setStatus(state.total ? 'Знайдено: ' + state.total + '.' : 'Нічого не знайдено.');
            if (more) {
                more.hidden = !state.hasMore;
            }
        } catch (error) {
            if (token === state.token) {
                setStatus(error.message || 'Помилка завантаження.');
            }
        } finally {
            if (token === state.token) {
                state.loading = false;
            }
        }
    }

    function renderItems(items, append) {
        const list = node('[data-admin-link-picker-list]');
        if (!append) {
            list.innerHTML = '';
        }
        list.dataset.viewMode = state.type === 'media' ? state.view : 'list';
        if (!items.length && !append) {
            list.innerHTML = '<div class="admin-link-picker-empty">За цими фільтрами немає результатів.</div>';
            return;
        }
        if (append) {
            const empty = list.querySelector('.admin-link-picker-empty');
            if (empty) {
                empty.remove();
            }
        }
        items.forEach(function (item) {
            const selected = state.selected.has(itemKey(item));
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'admin-link-picker-item' + (selected ? ' is-selected' : '');
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            button.dataset.label = item.label || '';
            button.dataset.cleanLabel = item.clean_label || item.cleanLabel || item.label || '';
            button.dataset.url = item.url || '';
            const displayLabel = item.display_label || item.displayLabel || item.label || item.url || 'Посилання';
            const preview = state.type === 'media'
                ? '<span class="admin-link-picker-item-preview">' + (item.is_image && item.thumb_url
                    ? '<img src="' + escapeHtml(item.thumb_url) + '" alt="">'
                    : '<span class="mdi ' + iconForType(state.type) + '" aria-hidden="true"></span>') + '</span>'
                : '<span class="admin-link-picker-item-icon mdi ' + iconForType(state.type) + '" aria-hidden="true"></span>';
            button.innerHTML =
                preview +
                '<span class="admin-link-picker-item-body">' +
                    '<strong>' + escapeHtml(displayLabel) + '</strong>' +
                    '<small>' + escapeHtml(item.meta || item.url || '') + '</small>' +
                    '<code class="w-100">' + escapeHtml(item.url || '#') + '</code>' +
                '</span>' +
                '<span class="admin-link-picker-state mdi ' + (selected ? 'mdi-check-circle' : 'mdi-plus-circle-outline') + '" data-admin-link-picker-state-icon aria-hidden="true"></span>';
            list.appendChild(button);
        });
        updateSelection();
    }

    function setView(mode) {
        state.view = mode === 'grid' ? 'grid' : 'list';
        if (window.localStorage) {
            localStorage.setItem('adminLinkPickerMediaView', state.view);
        }
        const list = node('[data-admin-link-picker-list]');
        if (list) {
            list.dataset.viewMode = state.type === 'media' ? state.view : 'list';
        }
        updateViewButtons();
    }

    function updateViewButtons() {
        modalNode.querySelectorAll('[data-admin-link-picker-view]').forEach(function (button) {
            const active = button.dataset.adminLinkPickerView === state.view;
            button.classList.toggle('secondary', !active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function toggleItem(button) {
        const item = {
            label: button.dataset.label || '',
            cleanLabel: button.dataset.cleanLabel || button.dataset.label || '',
            url: button.dataset.url || ''
        };
        if (!state.multiple) {
            state.selected.clear();
        }
        const key = itemKey(item);
        if (state.selected.has(key)) {
            state.selected.delete(key);
        } else {
            state.selected.set(key, item);
        }
        updateSelection();
        if (!state.multiple && state.selected.size) {
            applySelection();
        }
    }

    function applySelection() {
        const items = Array.from(state.selected.values());
        if (!items.length) {
            return;
        }
        if (typeof state.onSelect === 'function') {
            state.onSelect(state.multiple ? items : items[0]);
        }
        clearSelection();
        if (modal) {
            modal.hide();
        }
    }

    function open(options) {
        if (!modalNode || !window.bootstrap) {
            return false;
        }
        const settings = options || {};
        state.multiple = Boolean(settings.multiple);
        state.onSelect = settings.onSelect || null;
        state.type = settings.type || 'pages';
        const title = node('[data-admin-link-picker-title]');
        const hint = node('[data-admin-link-picker-hint]');
        const eyebrow = node('[data-admin-link-picker-eyebrow]');
        const search = node('[data-admin-link-picker-search]');
        if (title) {
            title.textContent = settings.title || 'Обрати посилання';
        }
        if (hint) {
            hint.textContent = settings.hint || 'Оберіть сторінку, категорію, новину або медіафайл.';
        }
        if (eyebrow) {
            eyebrow.textContent = settings.eyebrow || 'Посилання';
        }
        if (search) {
            search.value = '';
        }
        state.view = window.localStorage ? (localStorage.getItem('adminLinkPickerMediaView') || 'list') : 'list';
        modal.show();
        setType(state.type);
        setTimeout(function () {
            if (search) {
                search.focus();
            }
        }, 180);
        return true;
    }

    document.addEventListener('DOMContentLoaded', function () {
        modalNode = document.getElementById('adminLinkPickerModal');
        if (!modalNode || !window.bootstrap) {
            return;
        }
        modal = new window.bootstrap.Modal(modalNode);
        modalNode.addEventListener('click', function (event) {
            const typeButton = event.target.closest('[data-admin-link-picker-type]');
            if (typeButton) {
                setType(typeButton.dataset.adminLinkPickerType);
                return;
            }
            if (event.target.closest('[data-admin-link-picker-more]')) {
                load(true);
                return;
            }
            const viewButton = event.target.closest('[data-admin-link-picker-view]');
            if (viewButton) {
                setView(viewButton.dataset.adminLinkPickerView || 'list');
                return;
            }
            if (event.target.closest('[data-admin-link-picker-apply]')) {
                applySelection();
                return;
            }
            const itemButton = event.target.closest('.admin-link-picker-item');
            if (itemButton) {
                toggleItem(itemButton);
            }
        });
        modalNode.addEventListener('input', function (event) {
            if (!event.target.closest('[data-admin-link-picker-search]')) {
                return;
            }
            window.clearTimeout(state.searchTimer);
            clearSelection();
            state.searchTimer = window.setTimeout(reset, 250);
        });
        modalNode.addEventListener('change', function (event) {
            if (event.target.closest('[data-admin-link-picker-status], [data-admin-link-picker-scope]')) {
                clearSelection();
                reset();
            }
        });
        const body = modalNode.querySelector('.modal-body');
        if (body) {
            body.addEventListener('scroll', function () {
                const nearBottom = body.scrollTop + body.clientHeight >= body.scrollHeight - 80;
                if (nearBottom && state.hasMore && !state.loading) {
                    load(true);
                }
            }, {passive: true});
        }
    });

    window.AdminLinkPicker = {open: open};
})();
