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
