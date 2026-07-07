document.addEventListener('DOMContentLoaded', function () {
    const header = document.querySelector('[data-site-header]');
    if (!header) {
        return;
    }

    const toggle = header.querySelector('[data-site-menu-toggle]');
    const panel = header.querySelector('[data-site-menu-panel]');
    if (!toggle || !panel) {
        return;
    }

    function setMenuOpen(open) {
        header.classList.toggle('is-menu-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    toggle.addEventListener('click', function () {
        setMenuOpen(!header.classList.contains('is-menu-open'));
    });

    panel.addEventListener('click', function (event) {
        if (event.target.closest('a')) {
            setMenuOpen(false);
        }
    });

    window.addEventListener('resize', function () {
        if (window.matchMedia('(min-width: 801px)').matches) {
            setMenuOpen(false);
        }
    });
});
