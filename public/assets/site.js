document.addEventListener('DOMContentLoaded', function () {
    const header = document.querySelector('[data-site-header]');
    if (header) {
        const toggle = header.querySelector('[data-site-menu-toggle]');
        const panel = header.querySelector('[data-site-menu-panel]');
        if (toggle && panel) {
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
        }
    }

    document.querySelectorAll('[data-public-news-list]').forEach(function (list) {
        const root = list.closest('.container') || document;
        const form = root.querySelector('[data-news-filter-form]');
        const categorySlot = root.querySelector('[data-news-category-slot]');
        const pagerSlot = root.querySelector('[data-news-pager-slot]');
        const count = root.querySelector('[data-news-filter-count]');
        const totalCount = root.querySelector('[data-news-total-count]');
        const empty = list.querySelector('[data-news-empty]');
        const grid = list.querySelector('[data-news-grid]');
        const sentinel = list.querySelector('[data-news-sentinel]');
        const status = list.querySelector('[data-news-status]');
        if (!grid || !sentinel || !('IntersectionObserver' in window)) {
            return;
        }

        let loading = false;
        let activeRequest = null;
        let searchTimer = null;

        function setStatus(message) {
            if (status) {
                status.textContent = message;
            }
        }

        function filterUrlFromForm() {
            const url = new URL(form ? form.action : list.getAttribute('data-list-url'), window.location.origin);
            if (!form) {
                return url;
            }

            const formData = new FormData(form);
            formData.forEach(function (value, key) {
                const normalized = String(value).trim();
                if (normalized !== '') {
                    url.searchParams.set(key, normalized);
                }
            });
            return url;
        }

        function syncForm(url) {
            if (!form) {
                return;
            }

            const query = url.searchParams.get('q') || '';
            const category = url.searchParams.get('category') || '';
            const input = form.querySelector('input[name="q"]');
            const select = form.querySelector('select[name="category"]');
            const reset = form.querySelector('[data-news-filter-reset]');
            if (input) {
                input.value = query;
            }
            if (select) {
                select.value = category;
            }
            if (reset) {
                reset.classList.toggle('d-none', query === '' && category === '');
            }
        }

        function updateList(data, append) {
            if (append) {
                grid.insertAdjacentHTML('beforeend', data.html || '');
            } else {
                grid.innerHTML = data.html || '';
            }

            list.setAttribute('data-list-offset', String(data.next_offset || 0));
            list.setAttribute('data-list-has-more', data.has_more ? '1' : '0');
            list.setAttribute('data-list-category', data.category || '');
            list.setAttribute('data-list-query', data.query || '');
            if (count) {
                count.textContent = String(data.total || 0) + ' знайдено';
            }
            if (totalCount) {
                totalCount.textContent = String(data.total || 0) + ' записів';
            }
            if (empty) {
                empty.classList.toggle('d-none', grid.children.length > 0);
            }
            if (!append && pagerSlot) {
                pagerSlot.innerHTML = data.pager_html || '';
            }
            if (!append && categorySlot) {
                categorySlot.innerHTML = data.categories_html || '';
            }
            setStatus(data.has_more ? 'Прокрутіть нижче, щоб завантажити ще.' : 'Усі новини завантажено.');
        }

        async function loadNews(url, append, pushState) {
            if (append) {
                const hasMore = list.getAttribute('data-list-has-more') === '1';
                if (loading || !hasMore) {
                    return;
                }
            } else if (activeRequest) {
                activeRequest.abort();
                activeRequest = null;
                loading = false;
            } else if (loading) {
                return;
            }

            loading = true;
            setStatus('Завантаження...');

            const requestUrl = new URL(url.toString(), window.location.origin);
            requestUrl.searchParams.set('limit', list.getAttribute('data-list-limit') || '9');
            if (append) {
                requestUrl.searchParams.set('offset', list.getAttribute('data-list-offset') || '0');
                const category = list.getAttribute('data-list-category') || '';
                const query = list.getAttribute('data-list-query') || '';
                if (category !== '') {
                    requestUrl.searchParams.set('category', category);
                }
                if (query !== '') {
                    requestUrl.searchParams.set('q', query);
                }
            } else {
                requestUrl.searchParams.delete('offset');
                requestUrl.searchParams.delete('limit');
            }

            const controller = new AbortController();
            activeRequest = controller;
            try {
                const response = await fetch(requestUrl.toString(), {
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    signal: controller.signal
                });
                const data = await response.json();
                if (!response.ok || !data.ok) {
                    throw new Error(data.message || 'Не вдалося завантажити новини.');
                }

                updateList(data, append);
                if (!append) {
                    syncForm(requestUrl);
                    if (pushState) {
                        requestUrl.searchParams.delete('limit');
                        window.history.pushState({}, '', requestUrl.toString());
                    }
                }
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

        function loadMore() {
            loadNews(new URL(list.getAttribute('data-list-url'), window.location.origin), true, false);
        }

        const observer = new IntersectionObserver(function (entries) {
            if (entries.some(function (entry) { return entry.isIntersecting; })) {
                loadMore();
            }
        }, {rootMargin: '360px'});

        observer.observe(sentinel);

        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                loadNews(filterUrlFromForm(), false, true);
            });

            const search = form.querySelector('input[name="q"]');
            if (search) {
                search.addEventListener('input', function () {
                    window.clearTimeout(searchTimer);
                    searchTimer = window.setTimeout(function () {
                        loadNews(filterUrlFromForm(), false, true);
                    }, 350);
                });
            }

            const category = form.querySelector('select[name="category"]');
            if (category) {
                category.addEventListener('change', function () {
                    loadNews(filterUrlFromForm(), false, true);
                });
            }

            form.addEventListener('click', function (event) {
                const reset = event.target.closest('[data-news-filter-reset]');
                if (!reset) {
                    return;
                }

                event.preventDefault();
                loadNews(new URL(reset.href, window.location.origin), false, true);
            });
        }

        root.addEventListener('click', function (event) {
            const link = event.target.closest('[data-news-filter-link], [data-news-pager-slot] a');
            if (!link) {
                return;
            }

            event.preventDefault();
            loadNews(new URL(link.href, window.location.origin), false, true);
        });

        root.addEventListener('change', function (event) {
            const select = event.target.closest('[data-news-pager-slot] [data-page-jump]');
            if (!select || !select.value) {
                return;
            }

            loadNews(new URL(select.value, window.location.origin), false, true);
        });
    });

    document.querySelectorAll('[data-page-jump]').forEach(function (select) {
        select.addEventListener('change', function () {
            if (select.closest('[data-news-pager-slot]')) {
                return;
            }
            if (select.value) {
                window.location.href = select.value;
            }
        });
    });
});
