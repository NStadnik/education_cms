document.querySelectorAll('[data-global-fields]').forEach(function (panel) {
    const list = panel.querySelector('[data-global-fields-list]');
    const template = panel.querySelector('[data-global-field-template]');
    const addButton = panel.querySelector('[data-add-global-field]');
    if (!list || !template || !addButton) {
        return;
    }

    function addRow() {
        const fragment = template.content.cloneNode(true);
        list.appendChild(fragment);
        const input = list.lastElementChild ? list.lastElementChild.querySelector('input') : null;
        if (input) {
            input.focus();
        }
    }

    addButton.addEventListener('click', addRow);
    panel.addEventListener('click', function (event) {
        const button = event.target.closest('[data-remove-global-field]');
        if (button) {
            button.closest('[data-global-field-row]').remove();
        }
    });

    if (!list.querySelector('[data-global-field-row]')) {
        addRow();
    }
});

document.addEventListener('DOMContentLoaded', function () {
document.querySelectorAll('[data-settings-logo-picker]').forEach(function (picker) {
    const input = picker.querySelector('[data-settings-logo-input]');
    const preview = picker.querySelector('[data-settings-logo-preview]');
    const name = picker.querySelector('[data-settings-logo-name]');
    const openButton = picker.querySelector('[data-settings-logo-open]');
    const clearButton = picker.querySelector('[data-settings-logo-clear]');
    const modalNode = document.getElementById('settingsLogoPickerModal');
    const modal = modalNode && window.bootstrap ? new window.bootstrap.Modal(modalNode) : null;
    const grid = modalNode ? modalNode.querySelector('[data-settings-logo-grid]') : null;
    const search = modalNode ? modalNode.querySelector('[data-settings-logo-search]') : null;
    const status = modalNode ? modalNode.querySelector('[data-settings-logo-status]') : null;
    const more = modalNode ? modalNode.querySelector('[data-settings-logo-more]') : null;
    const state = {
        offset: 0,
        limit: 24,
        hasMore: false,
        loading: false,
        timer: null
    };

    if (!input || !preview || !name || !openButton || !modalNode || !modal || !grid) {
        return;
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (character) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character];
        });
    }

    function thumbUrl(path, size) {
        const base = picker.dataset.thumbBase || '/thumb/';
        return base + String(path || '').replace(/^\/+/, '') + '?w=' + size + '&h=' + size + '&fit=contain';
    }

    function setStatus(text) {
        if (status) {
            status.textContent = text;
        }
    }

    function setLogo(path) {
        input.value = path || '';
        if (path) {
            preview.innerHTML = '<img src="' + escapeHtml(thumbUrl(path, 160)) + '" alt="">';
            name.textContent = path;
            if (clearButton) {
                clearButton.hidden = false;
            }
        } else {
            preview.innerHTML = '<span class="mdi mdi-image-outline" aria-hidden="true"></span>';
            name.textContent = 'Логотип не вибрано';
            if (clearButton) {
                clearButton.hidden = true;
            }
        }
    }

    async function loadItems(append) {
        if (state.loading) {
            return;
        }
        state.loading = true;
        if (!append) {
            state.offset = 0;
            grid.innerHTML = '';
        }
        if (more) {
            more.hidden = true;
        }
        setStatus('Завантаження...');

        const url = new URL(document.body.dataset.richMediaPickerUrl || '/admin/media/picker', window.location.origin);
        url.searchParams.set('limit', String(state.limit));
        url.searchParams.set('offset', append ? String(state.offset) : '0');
        if (search && search.value.trim() !== '') {
            url.searchParams.set('q', search.value.trim());
        }

        try {
            const response = await fetch(url.toString(), {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Не вдалося завантажити медіафайли.');
            }
            state.offset = Number(data.next_offset || 0);
            state.hasMore = Boolean(data.has_more);
            renderItems((Array.isArray(data.items) ? data.items : []).filter(function (item) {
                return item.is_image;
            }), append);
            setStatus(grid.querySelector('[data-settings-logo-item]') ? 'Оберіть зображення.' : 'Зображень не знайдено.');
            if (more) {
                more.hidden = !state.hasMore;
            }
        } catch (error) {
            setStatus(error.message || 'Помилка завантаження.');
        } finally {
            state.loading = false;
        }
    }

    function renderItems(items, append) {
        if (!append) {
            grid.innerHTML = '';
        }
        items.forEach(function (item) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'settings-logo-item';
            button.dataset.settingsLogoItem = '';
            button.dataset.path = item.path || '';
            button.innerHTML =
                '<img src="' + escapeHtml(thumbUrl(item.path || '', 180)) + '" alt="">' +
                '<span>' + escapeHtml(item.name || item.path || 'Зображення') + '</span>';
            grid.appendChild(button);
        });
    }

    openButton.addEventListener('click', function () {
        modal.show();
        loadItems(false);
        if (search) {
            setTimeout(function () {
                search.focus();
            }, 180);
        }
    });

    if (clearButton) {
        clearButton.addEventListener('click', function () {
            setLogo('');
        });
    }

    modalNode.addEventListener('click', function (event) {
        const item = event.target.closest('[data-settings-logo-item]');
        if (item) {
            setLogo(item.dataset.path || '');
            modal.hide();
            return;
        }
        if (event.target.closest('[data-settings-logo-more]')) {
            loadItems(true);
        }
    });

    if (search) {
        search.addEventListener('input', function () {
            window.clearTimeout(state.timer);
            state.timer = window.setTimeout(function () {
                loadItems(false);
            }, 250);
        });
    }
});
});
