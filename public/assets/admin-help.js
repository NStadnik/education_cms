(function () {
    'use strict';

    const body = document.body;
    const drawer = document.getElementById('adminHelpDrawer');
    const content = drawer ? drawer.querySelector('[data-admin-help-content]') : null;
    const search = drawer ? drawer.querySelector('[data-admin-help-search]') : null;
    const clearSearch = drawer ? drawer.querySelector('[data-admin-help-search-clear]') : null;
    const endpoint = body ? body.dataset.adminHelpUrl : '';

    if (!drawer || !content || !endpoint) {
        return;
    }

    const cache = new Map();
    let activeRequest = null;
    let searchTimer = null;
    let currentTopic = body.dataset.adminHelpTopic || 'dashboard';
    let requestedAnchor = '';
    let loadedTopic = '';
    const offcanvas = window.bootstrap && window.bootstrap.Offcanvas
        ? window.bootstrap.Offcanvas.getOrCreateInstance(drawer)
        : null;

    function setBusy(busy) {
        content.setAttribute('aria-busy', busy ? 'true' : 'false');
        if (busy) {
            content.innerHTML = '<div class="admin-help-loading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Завантаження довідки…</span></div>';
        }
    }

    function showError(message) {
        content.innerHTML = '<div class="admin-help-empty" role="alert"><span class="mdi mdi-alert-circle-outline" aria-hidden="true"></span><h3>Не вдалося відкрити довідку</h3><p></p><button type="button" class="button secondary compact" data-admin-help-retry>Спробувати ще раз</button></div>';
        const paragraph = content.querySelector('p');
        if (paragraph) {
            paragraph.textContent = message || 'Перевірте з’єднання та повторіть спробу.';
        }
    }

    function scrollToAnchor(anchor) {
        if (!anchor) {
            content.scrollTop = 0;
            return;
        }
        const target = Array.from(content.querySelectorAll('[data-admin-help-section]')).find(function (section) {
            return section.dataset.adminHelpSection === anchor;
        });
        if (!target) {
            content.scrollTop = 0;
            return;
        }
        window.requestAnimationFrame(function () {
            target.scrollIntoView({block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
            target.classList.add('is-highlighted');
            window.setTimeout(function () { target.classList.remove('is-highlighted'); }, 1600);
        });
    }

    async function loadTopic(topic, anchor) {
        currentTopic = topic || body.dataset.adminHelpTopic || 'dashboard';
        requestedAnchor = anchor || '';
        if (search) {
            search.value = '';
        }
        if (clearSearch) {
            clearSearch.hidden = true;
        }

        if (cache.has(currentTopic)) {
            content.innerHTML = cache.get(currentTopic);
            loadedTopic = currentTopic;
            scrollToAnchor(requestedAnchor);
            return;
        }

        await loadUrl(new URL(endpoint, window.location.origin), {topic: currentTopic, anchor: requestedAnchor}, true);
    }

    async function loadSearch(query) {
        const url = new URL(endpoint, window.location.origin);
        await loadUrl(url, {q: query}, false);
    }

    async function loadUrl(url, params, cacheTopic) {
        if (activeRequest) {
            activeRequest.abort();
        }
        Object.keys(params).forEach(function (key) {
            if (params[key]) {
                url.searchParams.set(key, params[key]);
            }
        });

        const controller = new AbortController();
        activeRequest = controller;
        setBusy(true);
        try {
            const response = await fetch(url.toString(), {
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                credentials: 'same-origin',
                signal: controller.signal
            });
            const html = await response.text();
            if (!response.ok) {
                throw new Error('Сервер повернув помилку ' + response.status + '.');
            }
            content.innerHTML = html;
            content.setAttribute('aria-busy', 'false');
            if (cacheTopic) {
                cache.set(currentTopic, html);
                loadedTopic = currentTopic;
                scrollToAnchor(requestedAnchor);
            } else {
                content.scrollTop = 0;
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                content.setAttribute('aria-busy', 'false');
                showError(error.message);
            }
        } finally {
            if (activeRequest === controller) {
                activeRequest = null;
            }
        }
    }

    function openHelp(topic, anchor, trigger) {
        const nextTopic = topic || body.dataset.adminHelpTopic || 'dashboard';
        requestedAnchor = anchor || '';
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'true');
        }
        if (offcanvas) {
            offcanvas.show();
        }
        if (loadedTopic !== nextTopic || requestedAnchor) {
            loadTopic(nextTopic, requestedAnchor);
        }
    }

    document.addEventListener('click', function (event) {
        const opener = event.target.closest('[data-admin-help-open]');
        if (opener) {
            event.preventDefault();
            openHelp(opener.dataset.helpTopic, opener.dataset.helpAnchor, opener);
            return;
        }

        const topicLink = event.target.closest('[data-admin-help-topic]');
        if (topicLink && drawer.contains(topicLink)) {
            event.preventDefault();
            loadTopic(topicLink.dataset.adminHelpTopic || topicLink.dataset.helpTopic, topicLink.dataset.helpAnchor || '');
            return;
        }

        const anchorLink = event.target.closest('[data-admin-help-anchor-link]');
        if (anchorLink && drawer.contains(anchorLink)) {
            event.preventDefault();
            scrollToAnchor(anchorLink.dataset.adminHelpAnchorLink);
            return;
        }

        if (event.target.closest('[data-admin-help-retry]')) {
            loadTopic(currentTopic, requestedAnchor);
        }
    });

    if (search) {
        search.addEventListener('input', function () {
            window.clearTimeout(searchTimer);
            const query = search.value.trim();
            if (clearSearch) {
                clearSearch.hidden = query === '';
            }
            searchTimer = window.setTimeout(function () {
                if (query === '') {
                    loadTopic(currentTopic, requestedAnchor);
                } else {
                    loadSearch(query);
                }
            }, 280);
        });
    }

    if (clearSearch) {
        clearSearch.addEventListener('click', function () {
            if (search) {
                search.value = '';
                search.focus();
            }
            clearSearch.hidden = true;
            loadTopic(currentTopic, requestedAnchor);
        });
    }

    drawer.addEventListener('shown.bs.offcanvas', function () {
        if (!loadedTopic && !activeRequest) {
            loadTopic(currentTopic, requestedAnchor);
        }
    });
    drawer.addEventListener('hidden.bs.offcanvas', function () {
        document.querySelectorAll('[data-admin-help-open]').forEach(function (button) {
            button.setAttribute('aria-expanded', 'false');
        });
    });
})();
