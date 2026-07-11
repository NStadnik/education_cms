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
const settingsTabs = document.querySelector('[data-settings-tabs]');
if (settingsTabs) {
    const tabButtons = Array.from(settingsTabs.querySelectorAll('[data-settings-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-settings-panel]'));
    const available = tabButtons.map(function (button) { return button.dataset.settingsTab; });

    function activateSettingsTab(tab) {
        if (!available.includes(tab)) {
            tab = 'general';
        }
        tabButtons.forEach(function (button) {
            const active = button.dataset.settingsTab === tab;
            button.setAttribute('aria-selected', active ? 'true' : 'false');
            button.classList.toggle('is-active', active);
            button.tabIndex = active ? 0 : -1;
        });
        panels.forEach(function (panel) {
            panel.hidden = panel.dataset.settingsPanel !== tab;
        });
        try { window.localStorage.setItem('education-cms-settings-tab', tab); } catch (error) {}
    }

    tabButtons.forEach(function (button, index) {
        button.addEventListener('click', function () { activateSettingsTab(button.dataset.settingsTab); });
        button.addEventListener('keydown', function (event) {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') { return; }
            event.preventDefault();
            const direction = event.key === 'ArrowRight' ? 1 : -1;
            const next = tabButtons[(index + direction + tabButtons.length) % tabButtons.length];
            activateSettingsTab(next.dataset.settingsTab);
            next.focus();
        });
    });
    const settingsForm = settingsTabs.closest('form');
    if (settingsForm) {
        settingsForm.addEventListener('invalid', function (event) {
            const panel = event.target.closest('[data-settings-panel]');
            if (panel && panel.hidden) { activateSettingsTab(panel.dataset.settingsPanel); }
        }, true);
    }
    let initialTab = 'general';
    try { initialTab = window.localStorage.getItem('education-cms-settings-tab') || initialTab; } catch (error) {}
    activateSettingsTab(initialTab);
}

const mailEnabled = document.querySelector('[data-mail-enabled]');
const mailStatus = document.querySelector('[data-mail-status]');
const mailTransport = document.querySelector('[data-mail-transport]');
const mailFromEmail = document.querySelector('[data-mail-from-email]');
const mailSmtpHost = document.querySelector('[data-mail-smtp-host]');
const siteModeSelect = document.querySelector('[data-site-mode-select]');
const siteModeStatus = document.querySelector('[data-site-mode-status]');
function renderSiteModeStatus(mode) {
    if (!siteModeStatus) { return; }
    const online = mode === 'online';
    siteModeStatus.textContent = online ? 'Сайт відкритий' : 'Заглушка активна';
    siteModeStatus.classList.toggle('ok', online);
    siteModeStatus.classList.toggle('warn', !online);
}
function renderMailStatus(enabled) {
    if (!mailStatus) { return; }
    mailStatus.textContent = enabled ? 'Увімкнено' : 'Вимкнено';
    mailStatus.classList.toggle('ok', enabled);
    mailStatus.classList.toggle('warn', !enabled);
}
function syncMailRequirements() {
    const enabled = Boolean(mailEnabled && mailEnabled.checked);
    if (mailFromEmail) { mailFromEmail.required = enabled; }
    if (mailSmtpHost) { mailSmtpHost.required = enabled && Boolean(mailTransport && mailTransport.value === 'smtp'); }
}
if (mailEnabled) { mailEnabled.addEventListener('change', syncMailRequirements); }
if (mailTransport) { mailTransport.addEventListener('change', syncMailRequirements); }
syncMailRequirements();
document.addEventListener('admin:form-saved', function (event) {
    const form = event.detail && event.detail.form;
    const data = event.detail && event.detail.data ? event.detail.data : {};
    if (!form || !form.matches('form[action*="/admin/settings/save"]') || typeof data.mail_enabled !== 'boolean') { return; }
    if (typeof data.site_mode === 'string') {
        if (siteModeSelect) { siteModeSelect.value = data.site_mode; }
        renderSiteModeStatus(data.site_mode);
    }
    if (mailEnabled) { mailEnabled.checked = data.mail_enabled; }
    const newsToggle = form.querySelector('[name="mail_notify_news"]');
    const formsToggle = form.querySelector('[name="mail_notify_forms"]');
    if (newsToggle) { newsToggle.checked = Boolean(data.mail_notify_news); }
    if (formsToggle) { formsToggle.checked = Boolean(data.mail_notify_forms); }
    const password = form.querySelector('[data-mail-password]');
    if (password) {
        password.value = '';
        password.placeholder = data.mail_password_configured ? 'Пароль уже налаштовано' : 'Введіть пароль';
    }
    syncMailRequirements();
    renderMailStatus(data.mail_enabled);
});

const mailTestButton = document.querySelector('[data-mail-test-button]');
if (mailTestButton) {
    mailTestButton.addEventListener('click', async function () {
        const emailInput = document.querySelector('[data-mail-test-email]');
        const status = document.querySelector('[data-mail-test-status]');
        const email = emailInput ? emailInput.value.trim() : '';
        if (!emailInput || !emailInput.checkValidity()) {
            if (emailInput) { emailInput.reportValidity(); }
            return;
        }
        const originalHtml = mailTestButton.innerHTML;
        mailTestButton.disabled = true;
        mailTestButton.innerHTML = '<span class="mdi mdi-loading mdi-spin" aria-hidden="true"></span><span>Надсилання...</span>';
        if (status) { status.textContent = 'Перевіряємо поштове підключення...'; status.className = 'meta'; }
        try {
            const body = new FormData();
            body.set('_csrf', document.body.dataset.adminCsrfToken || '');
            body.set('email', email);
            const response = await fetch(mailTestButton.dataset.testUrl, {method: 'POST', body: body, headers: {'X-Requested-With': 'XMLHttpRequest'}});
            const data = await response.json();
            if (!response.ok || !data.ok) { throw new Error(data.message || 'Не вдалося надіслати тестовий лист.'); }
            if (status) { status.textContent = data.message; status.className = 'alert alert-success'; }
        } catch (error) {
            if (status) { status.textContent = error.message || 'Помилка надсилання.'; status.className = 'alert alert-danger'; }
        } finally {
            mailTestButton.disabled = false;
            mailTestButton.innerHTML = originalHtml;
        }
    });
}

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
        limit: 10,
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
        url.searchParams.set('images_only', '1');
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
