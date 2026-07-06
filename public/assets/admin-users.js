document.addEventListener('DOMContentLoaded', function () {
    const matrix = document.querySelector('[data-permission-matrix]');
    const roleSelect = document.querySelector('[data-role-select]');
    const selectedRoleLabel = document.querySelector('[data-selected-role-label]');

    if (!matrix || !roleSelect) {
        return;
    }

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
});
