document.addEventListener('DOMContentLoaded', function () {
    const matrix = document.querySelector('[data-permission-matrix]');
    const roleSelect = document.querySelector('[data-role-select]');
    const selectedRoleLabel = document.querySelector('[data-selected-role-label]');

    if (matrix && roleSelect) {
        function syncSelectedRole() {
            const role = roleSelect.value;
            matrix.dataset.selectedRole = role;
            matrix.querySelectorAll('[data-role-column]').forEach(function (cell) {
                cell.classList.toggle('is-selected-role', cell.dataset.roleColumn === role);
            });
            if (selectedRoleLabel) {
                selectedRoleLabel.textContent = roleSelect.options[roleSelect.selectedIndex]?.textContent || role;
            }
        }

        roleSelect.addEventListener('change', syncSelectedRole);
        syncSelectedRole();
    }

    const roleRows = document.querySelector('[data-role-rows]');
    const roleFilter = document.querySelector('[data-filter-list] [data-filter-input]');
    const roleEmpty = document.querySelector('[data-role-empty]');
    const roleMessage = document.querySelector('[data-role-ajax-message]');

    function applyRoleFilter() {
        if (!roleRows) {
            return;
        }

        const query = roleFilter ? roleFilter.value.trim().toLowerCase() : '';
        let visible = 0;
        roleRows.querySelectorAll('[data-filter-row]').forEach(function (row) {
            const text = (row.getAttribute('data-filter-text') || '').toLowerCase();
            const show = query === '' || text.includes(query);
            row.hidden = !show;
            if (show) {
                visible++;
            }
        });
        if (roleEmpty) {
            roleEmpty.classList.toggle('d-none', visible > 0);
        }
    }

    function showRoleMessage(message, isError) {
        if (!roleMessage) {
            return;
        }

        roleMessage.textContent = message || '';
        roleMessage.classList.toggle('d-none', !message);
        roleMessage.classList.toggle('alert-success', !isError);
        roleMessage.classList.toggle('alert-danger', isError);
    }

    if (roleFilter && roleRows) {
        roleFilter.addEventListener('input', applyRoleFilter);
    }

    document.addEventListener('submit', async function (event) {
        const form = event.target.closest('[data-role-delete-form]');
        if (!form) {
            return;
        }

        event.preventDefault();
        if (!confirm(form.dataset.deleteConfirm || 'Видалити цю роль?')) {
            return;
        }

        const button = form.querySelector('button[type="submit"]');
        const originalHtml = button ? button.innerHTML : '';
        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="mdi mdi-loading mdi-spin" aria-hidden="true"></span><span>Видалення...</span>';
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Не вдалося видалити роль.');
            }

            const rows = document.querySelector('[data-role-rows]');
            if (rows && typeof data.html === 'string') {
                rows.innerHTML = data.html;
            }
            const total = document.querySelector('[data-role-total]');
            if (total && typeof data.total !== 'undefined') {
                total.textContent = String(data.total);
            }
            applyRoleFilter();
            showRoleMessage(data.message || 'Роль видалено.', false);
        } catch (error) {
            showRoleMessage(error.message || 'Помилка видалення.', true);
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        }
    });

    const roleForm = document.querySelector('[data-role-form]');
    if (!roleForm) {
        return;
    }

    function normalizeSlug(value) {
        return value.toLowerCase().trim().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '');
    }

    const label = roleForm.querySelector('[data-role-label-input]');
    const slug = roleForm.querySelector('[data-role-slug-input]');
    if (!label || !slug) {
        return;
    }

    label.addEventListener('input', function () {
        if (slug.readOnly || slug.dataset.touched === '1') {
            return;
        }
        slug.value = normalizeSlug(label.value);
    });
    slug.addEventListener('input', function () {
        if (slug.readOnly) {
            return;
        }
        slug.dataset.touched = '1';
        slug.value = normalizeSlug(slug.value);
    });

    document.addEventListener('admin:form-saved', function (event) {
        if (event.detail?.form !== roleForm || !event.detail?.data?.role_slug) {
            return;
        }

        const oldSlug = roleForm.querySelector('input[name="old_slug"]');
        if (oldSlug) {
            oldSlug.value = event.detail.data.role_slug;
        }
        slug.value = event.detail.data.role_slug;

        const title = document.querySelector('[data-role-form-title]');
        if (title) {
            title.textContent = 'Редагувати роль';
        }
    });
});
