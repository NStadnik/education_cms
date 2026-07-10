document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-public-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const status = form.querySelector('[data-form-status]');
            const button = form.querySelector('[type="submit"]');
            form.querySelectorAll('[data-form-error]').forEach(function (node) { node.textContent = ''; });
            form.classList.remove('is-success','has-error');
            if (button) { button.disabled = true; button.dataset.label = button.textContent; button.textContent = 'Надсилання…'; }
            if (status) status.textContent = 'Надсилання…';
            fetch(form.action, {method: 'POST', body: new FormData(form), headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(function (response) { return response.json().then(function (data) { return {ok: response.ok, data: data}; }); })
                .then(function (result) {
                    if (!result.ok || !result.data.ok) throw result.data;
                    form.reset(); form.classList.add('is-success'); if (status) status.textContent = result.data.message;
                })
                .catch(function (error) {
                    Object.keys(error.errors || {}).forEach(function (key) { const node=form.querySelector('[data-form-error="'+key+'"]'); if(node) node.textContent=error.errors[key]; });
                    form.classList.add('has-error'); if (status) status.textContent = error.message || 'Не вдалося надіслати форму.';
                    const firstError=form.querySelector('[data-form-error]:not(:empty)'); if(firstError) firstError.closest('label').querySelector('input,textarea,select')?.focus();
                }).finally(function () { if (button) { button.disabled = false; button.textContent = button.dataset.label || 'Надіслати'; } });
        });
    });
    function initRichGalleryViewer() {
        const galleries = Array.from(document.querySelectorAll('.rich-gallery'));
        if (!galleries.length) {
            return;
        }

        const modal = document.createElement('div');
        modal.className = 'rich-gallery-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-label', 'Перегляд фото');
        modal.hidden = true;
        modal.innerHTML = [
            '<button class="rich-gallery-modal-close" type="button" aria-label="Закрити">',
                '<span class="mdi mdi-close" aria-hidden="true"></span>',
            '</button>',
            '<button class="rich-gallery-modal-nav rich-gallery-modal-prev" type="button" aria-label="Попереднє фото">',
                '<span class="mdi mdi-chevron-left" aria-hidden="true"></span>',
            '</button>',
            '<figure class="rich-gallery-modal-figure">',
                '<img class="rich-gallery-modal-image" alt="">',
                '<figcaption class="rich-gallery-modal-caption"></figcaption>',
            '</figure>',
            '<button class="rich-gallery-modal-nav rich-gallery-modal-next" type="button" aria-label="Наступне фото">',
                '<span class="mdi mdi-chevron-right" aria-hidden="true"></span>',
            '</button>'
        ].join('');
        document.body.appendChild(modal);

        const image = modal.querySelector('.rich-gallery-modal-image');
        const caption = modal.querySelector('.rich-gallery-modal-caption');
        const closeButton = modal.querySelector('.rich-gallery-modal-close');
        const prevButton = modal.querySelector('.rich-gallery-modal-prev');
        const nextButton = modal.querySelector('.rich-gallery-modal-next');
        let activeItems = [];
        let activeIndex = 0;

        function itemFromGalleryNode(node) {
            const img = node.matches('img') ? node : node.querySelector('img');
            const link = node.matches('a') ? node : (img ? img.closest('a') : null);
            const figure = node.closest('figure');
            const figureCaption = figure ? figure.querySelector('figcaption') : null;
            const text = figureCaption ? figureCaption.textContent.trim() : '';
            return {
                src: link ? link.href : (img ? img.currentSrc || img.src : ''),
                alt: img ? img.getAttribute('alt') || '' : '',
                caption: text || (img ? img.getAttribute('alt') || '' : '')
            };
        }

        function renderItem() {
            const item = activeItems[activeIndex];
            if (!item) {
                return;
            }

            image.src = item.src;
            image.alt = item.alt;
            caption.textContent = item.caption;
            caption.hidden = item.caption === '';
            const hasNavigation = activeItems.length > 1;
            prevButton.hidden = !hasNavigation;
            nextButton.hidden = !hasNavigation;
        }

        function openViewer(items, index) {
            activeItems = items;
            activeIndex = index;
            renderItem();
            modal.hidden = false;
            document.body.classList.add('rich-gallery-modal-open');
            closeButton.focus();
        }

        function closeViewer() {
            modal.hidden = true;
            image.removeAttribute('src');
            document.body.classList.remove('rich-gallery-modal-open');
        }

        function moveViewer(direction) {
            if (activeItems.length < 2) {
                return;
            }
            activeIndex = (activeIndex + direction + activeItems.length) % activeItems.length;
            renderItem();
        }

        galleries.forEach(function (gallery) {
            gallery.addEventListener('click', function (event) {
                const target = event.target.closest('a, img');
                if (!target || !gallery.contains(target)) {
                    return;
                }

                const items = Array.from(gallery.querySelectorAll('figure')).map(function (figure) {
                    return figure.querySelector('a') || figure.querySelector('img');
                }).filter(function (item) {
                    return item && (item.matches('img') || item.querySelector('img'));
                });
                const index = items.findIndex(function (item) {
                    return item === target || item.contains(target);
                });
                if (index === -1) {
                    return;
                }

                event.preventDefault();
                openViewer(items.map(itemFromGalleryNode), index);
            });
        });

        closeButton.addEventListener('click', closeViewer);
        prevButton.addEventListener('click', function () { moveViewer(-1); });
        nextButton.addEventListener('click', function () { moveViewer(1); });
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeViewer();
            }
        });
        document.addEventListener('keydown', function (event) {
            if (modal.hidden) {
                return;
            }
            if (event.key === 'Escape') {
                closeViewer();
            } else if (event.key === 'ArrowLeft') {
                moveViewer(-1);
            } else if (event.key === 'ArrowRight') {
                moveViewer(1);
            }
        });
    }

    initRichGalleryViewer();

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
        let scrollFrame = null;
        let visiblePage = null;

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

        function pageFromUrl(url) {
            const parsed = new URL(url, window.location.origin);
            return Math.max(1, Number(parsed.searchParams.get('page') || '1') || 1);
        }

        function currentPageUrl() {
            const select = pagerSlot ? pagerSlot.querySelector('[data-page-jump]') : null;
            const selected = select ? select.options[select.selectedIndex] : null;
            if (selected && selected.value) {
                return selected.value;
            }
            return window.location.href;
        }

        function assignCardsPage(cards, page, pageUrl) {
            cards.forEach(function (card) {
                card.dataset.newsPage = String(page);
                card.dataset.newsPageUrl = pageUrl;
            });
        }

        function syncPagerPage(page, pageUrl, replaceUrl) {
            if (!pagerSlot || !page) {
                return;
            }

            pagerSlot.querySelectorAll('.site-pager-pages a').forEach(function (link) {
                const linkPage = pageFromUrl(link.href);
                const active = linkPage === page;
                link.classList.toggle('is-active', active);
                if (active) {
                    link.setAttribute('aria-current', 'page');
                    link.scrollIntoView({block: 'nearest', inline: 'center'});
                } else {
                    link.removeAttribute('aria-current');
                }
            });

            const select = pagerSlot.querySelector('[data-page-jump]');
            if (select) {
                const option = Array.from(select.options).find(function (item) {
                    return pageFromUrl(item.value) === page;
                });
                if (option) {
                    select.value = option.value;
                }
            }

            const previous = pagerSlot.querySelector('.site-pager-control:first-child');
            const next = pagerSlot.querySelector('.site-pager-control:last-of-type');
            if (previous && select) {
                const prevOption = Array.from(select.options).find(function (item) {
                    return pageFromUrl(item.value) === Math.max(1, page - 1);
                });
                if (prevOption) {
                    previous.href = prevOption.value;
                }
                previous.classList.toggle('is-disabled', page <= 1);
                previous.toggleAttribute('aria-disabled', page <= 1);
                previous.tabIndex = page <= 1 ? -1 : 0;
            }
            if (next && select) {
                const maxPage = select.options.length;
                const nextOption = Array.from(select.options).find(function (item) {
                    return pageFromUrl(item.value) === Math.min(maxPage, page + 1);
                });
                if (nextOption) {
                    next.href = nextOption.value;
                }
                next.classList.toggle('is-disabled', page >= maxPage);
                next.toggleAttribute('aria-disabled', page >= maxPage);
                next.tabIndex = page >= maxPage ? -1 : 0;
            }

            if (replaceUrl && pageUrl) {
                window.history.replaceState({}, '', pageUrl);
            }
        }

        function syncVisiblePage() {
            scrollFrame = null;
            const cards = Array.from(grid.querySelectorAll('[data-news-page]'));
            if (!cards.length) {
                return;
            }

            const targetY = window.innerHeight * 0.42;
            let bestCard = null;
            let bestDistance = Infinity;
            cards.forEach(function (card) {
                const rect = card.getBoundingClientRect();
                if (rect.bottom < 0 || rect.top > window.innerHeight) {
                    return;
                }

                const middle = rect.top + rect.height / 2;
                const distance = Math.abs(middle - targetY);
                if (distance < bestDistance) {
                    bestDistance = distance;
                    bestCard = card;
                }
            });

            if (!bestCard) {
                return;
            }

            const page = Number(bestCard.dataset.newsPage || '1') || 1;
            if (page === visiblePage) {
                return;
            }

            visiblePage = page;
            syncPagerPage(page, bestCard.dataset.newsPageUrl || '', true);
        }

        function scheduleVisiblePageSync() {
            if (scrollFrame !== null) {
                return;
            }
            scrollFrame = window.requestAnimationFrame(syncVisiblePage);
        }

        function scrollToLoadedPage(url) {
            const page = pageFromUrl(url);
            const card = grid.querySelector('[data-news-page="' + String(page) + '"]');
            if (!card) {
                return false;
            }

            visiblePage = page;
            syncPagerPage(page, url.toString(), true);
            card.scrollIntoView({block: 'start', behavior: 'smooth'});
            return true;
        }

        function updateList(data, append) {
            const oldCount = grid.children.length;
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
            if (pagerSlot) {
                pagerSlot.innerHTML = data.pager_html || '';
            }
            if (data.current_page) {
                const pageUrl = data.page_url || currentPageUrl();
                const cards = append ? Array.from(grid.children).slice(oldCount) : Array.from(grid.children);
                assignCardsPage(cards, Number(data.current_page), pageUrl);
                visiblePage = Number(data.current_page);
                syncPagerPage(Number(data.current_page), pageUrl, false);
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
                }
                if (append && data.page_url) {
                    window.history.replaceState({}, '', data.page_url);
                } else if (!append && pushState) {
                    requestUrl.searchParams.delete('limit');
                    window.history.pushState({}, '', requestUrl.toString());
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
        assignCardsPage(Array.from(grid.children), pageFromUrl(window.location.href), window.location.href);
        visiblePage = pageFromUrl(window.location.href);
        window.addEventListener('scroll', scheduleVisiblePageSync, {passive: true});

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
            const targetUrl = new URL(link.href, window.location.origin);
            if (link.closest('[data-news-pager-slot]') && scrollToLoadedPage(targetUrl)) {
                return;
            }
            loadNews(targetUrl, false, true);
        });

        root.addEventListener('change', function (event) {
            const select = event.target.closest('[data-news-pager-slot] [data-page-jump]');
            if (!select || !select.value) {
                return;
            }

            const targetUrl = new URL(select.value, window.location.origin);
            if (scrollToLoadedPage(targetUrl)) {
                return;
            }
            loadNews(targetUrl, false, true);
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
