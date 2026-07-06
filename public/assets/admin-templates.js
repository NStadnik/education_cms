function setTemplatePreviewColors(preview, topColor, heroColor, lineColor) {
    if (!preview) {
        return;
    }
    preview.style.setProperty('--template-preview-top', topColor || '#10233f');
    preview.style.setProperty('--template-preview-hero', heroColor || '#dfeafb');
    preview.style.setProperty('--template-preview-line', lineColor || '#c8d1df');
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.template-preview[data-preview-top], .template-preview[data-preview-hero], .template-preview[data-preview-line]').forEach(function (preview) {
        setTemplatePreviewColors(preview, preview.dataset.previewTop, preview.dataset.previewHero, preview.dataset.previewLine);
    });
});

document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-site-template-preview]');
    if (!button || !window.bootstrap) {
        return;
    }
    event.preventDefault();
    event.stopPropagation();

    const modalNode = document.getElementById('siteTemplatePreviewModal');
    const preview = modalNode.querySelector('[data-template-preview-large]');
    modalNode.querySelector('#siteTemplatePreviewTitle').textContent = button.getAttribute('data-name') || 'Попередній перегляд';
    modalNode.querySelector('[data-template-preview-description]').textContent = button.getAttribute('data-description') || '';
    preview.className = 'site-template-preview-large';
    setTemplatePreviewColors(preview, button.getAttribute('data-preview-top'), button.getAttribute('data-preview-hero'), button.getAttribute('data-preview-line'));
    new window.bootstrap.Modal(modalNode).show();
});

document.addEventListener('DOMContentLoaded', function () {
    const headerEditor = document.querySelector('[data-template-header-editor]');
    const footerEditor = document.querySelector('[data-template-footer-editor]');
    if (!headerEditor || !footerEditor) {
        return;
    }

    const layoutsInput = document.querySelector('[data-template-layouts-json]');
    const headerInput = document.querySelector('[data-template-header-json]');
    const footerInput = document.querySelector('[data-template-footer-json]');
    const templateRadios = document.querySelectorAll('input[name="template_editor_key"]');
    const currentTemplateName = document.querySelector('[data-current-template-name]');
    const previewFrame = document.querySelector('[data-template-home-preview]');
    const previewContext = previewFrame ? parseJson(previewFrame.dataset.context, {}) : {};
    const linkPicker = previewContext.linkPicker || {pages: [], categories: [], news: []};
    const defaultHeader = {
        variant: 'default',
        show_brand: true,
        show_home: true,
        show_news: true,
        links: [],
        cta_label: '',
        cta_url: ''
    };
    const defaultFooter = {
        variant: 'default',
        columns: [],
        bottom_text: ''
    };
    let selectedTemplate = document.querySelector('input[name="template_editor_key"]:checked')?.value || 'official';
    let layouts = parseJson(headerEditor.dataset.layouts, {});
    ensureTemplateLayout(selectedTemplate);
    let header = cloneLayout(layouts[selectedTemplate].header);
    let footer = cloneLayout(layouts[selectedTemplate].footer);
    const menuPickerNode = document.getElementById('templateMenuPickerModal');
    const menuPickerModal = menuPickerNode && window.bootstrap ? new window.bootstrap.Modal(menuPickerNode) : null;
    const menuPickerState = {
        type: 'pages',
        offset: 0,
        limit: 20,
        total: 0,
        hasMore: false,
        loading: false,
        searchTimer: null
    };

    function parseJson(value, fallback) {
        try {
            const parsed = JSON.parse(value || '');
            return parsed && typeof parsed === 'object' ? parsed : fallback;
        } catch (error) {
            return fallback;
        }
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (character) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character];
        });
    }

    function previewUrl(value) {
        const url = String(value || '').trim();
        return /^(https?:\/\/|mailto:|tel:|\/|#)/i.test(url) ? url : '#';
    }

    function makeMenuItem(label, url) {
        return {label: label || '', url: url || '', children: []};
    }

    function cloneLayout(value) {
        return JSON.parse(JSON.stringify(value || {}));
    }

    function mergeLayout(defaults, value) {
        return Object.assign({}, defaults, value && typeof value === 'object' ? value : {});
    }

    function ensureTemplateLayout(template) {
        if (!layouts[template] || typeof layouts[template] !== 'object') {
            layouts[template] = {};
        }
        layouts[template].header = mergeLayout(defaultHeader, layouts[template].header);
        layouts[template].footer = mergeLayout(defaultFooter, layouts[template].footer);
        layouts[template].header.links = Array.isArray(layouts[template].header.links) ? layouts[template].header.links : [];
        layouts[template].footer.columns = Array.isArray(layouts[template].footer.columns) ? layouts[template].footer.columns : [];
    }

    function getMenuItem(path) {
        const parts = String(path || '').split('.').filter(Boolean).map(Number);
        let items = header.links || [];
        let item = null;
        parts.forEach(function (index) {
            item = items[index];
            items = item && Array.isArray(item.children) ? item.children : [];
        });
        return item;
    }

    function removeMenuItem(path) {
        const parts = String(path || '').split('.').filter(Boolean).map(Number);
        const index = parts.pop();
        let items = header.links || [];
        parts.forEach(function (part) {
            const item = items[part];
            item.children = Array.isArray(item.children) ? item.children : [];
            items = item.children;
        });
        if (Number.isInteger(index)) {
            items.splice(index, 1);
        }
    }

    function addMenuChild(path) {
        const parent = getMenuItem(path);
        if (!parent) {
            return;
        }
        parent.children = Array.isArray(parent.children) ? parent.children : [];
        parent.children.push(makeMenuItem('', ''));
    }

    function renderMenuLinks(items, parentPath, depth) {
        return (items || []).map(function (link, index) {
            const path = parentPath ? parentPath + '.' + index : String(index);
            const children = Array.isArray(link.children) ? link.children : [];
            return '<div class="template-menu-node template-menu-depth-' + depth + '" data-header-link="' + path + '">' +
                '<div class="template-editor-row template-menu-row">' +
                    '<label>Назва<input data-header-link-field="label" value="' + escapeHtml(link.label) + '"></label>' +
                    '<label>URL<input data-header-link-field="url" value="' + escapeHtml(link.url) + '"></label>' +
                    '<div class="template-menu-actions">' +
                        (depth < 2 ? '<button class="button secondary compact" type="button" data-header-add-child title="Додати підпункт"><span class="mdi mdi-subdirectory-arrow-right"></span></button>' : '') +
                        '<button class="button danger compact" type="button" data-header-remove-link title="Видалити"><span class="mdi mdi-delete-outline"></span></button>' +
                    '</div>' +
                '</div>' +
                (children.length ? '<div class="template-menu-children">' + renderMenuLinks(children, path, depth + 1) + '</div>' : '') +
            '</div>';
        }).join('');
    }

    function menuTargets(items, parentPath, depth) {
        const result = [];
        if (depth > 1) {
            return result;
        }
        (items || []).forEach(function (item, index) {
            const path = parentPath ? parentPath + '.' + index : String(index);
            if (item.label) {
                result.push({path: path, label: (depth ? '— ' : '') + item.label});
            }
            result.push.apply(result, menuTargets(item.children || [], path, depth + 1));
        });
        return result;
    }

    function fillMenuParentSelect() {
        const select = headerEditor.querySelector('[data-menu-parent]');
        if (!select) {
            return;
        }
        const current = select.value;
        const options = menuTargets(header.links || [], '', 0);
        select.innerHTML = '<option value="">Верхній рівень</option>' + options.map(function (item) {
            return '<option value="' + escapeHtml(item.path) + '">' + escapeHtml(item.label) + '</option>';
        }).join('');
        select.value = options.some(function (item) { return item.path === current; }) ? current : '';
    }

    function addPickedMenuItem(item) {
        const parentSelect = headerEditor.querySelector('[data-menu-parent]');
        const parentPath = parentSelect ? parentSelect.value : '';
        const menuItem = makeMenuItem(item.label, item.url);
        header.links = Array.isArray(header.links) ? header.links : [];
        if (parentPath === '') {
            header.links.push(menuItem);
            return;
        }
        const parent = getMenuItem(parentPath);
        if (!parent) {
            header.links.push(menuItem);
            return;
        }
        parent.children = Array.isArray(parent.children) ? parent.children : [];
        parent.children.push(menuItem);
    }

    function menuParentLabel() {
        const parentSelect = headerEditor.querySelector('[data-menu-parent]');
        if (!parentSelect || parentSelect.value === '') {
            return 'верхній рівень меню';
        }
        return 'пункт “' + parentSelect.options[parentSelect.selectedIndex].textContent.trim().replace(/^—\s*/, '') + '”';
    }

    function openMenuPicker() {
        if (!menuPickerModal) {
            return;
        }
        const target = menuPickerNode.querySelector('[data-menu-picker-target]');
        if (target) {
            target.textContent = 'Пункт буде додано у ' + menuParentLabel() + '.';
        }
        menuPickerModal.show();
        resetMenuPicker();
        const search = menuPickerNode.querySelector('[data-menu-picker-search]');
        if (search) {
            setTimeout(function () {
                search.focus();
            }, 180);
        }
    }

    function resetMenuPicker() {
        menuPickerState.offset = 0;
        menuPickerState.total = 0;
        menuPickerState.hasMore = false;
        loadMenuPickerItems(false);
    }

    function setMenuPickerType(type) {
        menuPickerState.type = type;
        menuPickerNode.querySelectorAll('[data-menu-picker-type]').forEach(function (button) {
            button.classList.toggle('secondary', button.dataset.menuPickerType !== type);
        });
        const statusFilter = menuPickerNode.querySelector('[data-menu-picker-filter="status"]');
        const scopeFilter = menuPickerNode.querySelector('[data-menu-picker-filter="scope"]');
        const categoryFilter = menuPickerNode.querySelector('[data-menu-picker-filter="category"]');
        if (statusFilter) {
            statusFilter.hidden = type === 'categories';
        }
        if (scopeFilter) {
            scopeFilter.hidden = type !== 'categories';
        }
        if (categoryFilter) {
            categoryFilter.hidden = type !== 'news';
        }
        resetMenuPicker();
    }

    function menuPickerStatus(message) {
        const status = menuPickerNode.querySelector('[data-menu-picker-status-text]');
        if (status) {
            status.textContent = message;
        }
    }

    async function loadMenuPickerItems(append) {
        if (!menuPickerNode || menuPickerState.loading) {
            return;
        }
        const list = menuPickerNode.querySelector('[data-menu-picker-list]');
        const more = menuPickerNode.querySelector('[data-menu-picker-more]');
        const search = menuPickerNode.querySelector('[data-menu-picker-search]');
        const status = menuPickerNode.querySelector('[data-menu-picker-status]');
        const scope = menuPickerNode.querySelector('[data-menu-picker-scope]');
        const category = menuPickerNode.querySelector('[data-menu-picker-category]');
        const url = new URL(menuPickerNode ? (menuPickerNode.dataset.linkPickerUrl || '/admin/templates/link-picker') : '/admin/templates/link-picker', window.location.origin);
        url.searchParams.set('type', menuPickerState.type);
        url.searchParams.set('limit', String(menuPickerState.limit));
        url.searchParams.set('offset', append ? String(menuPickerState.offset) : '0');
        if (search && search.value.trim() !== '') {
            url.searchParams.set('q', search.value.trim());
        }
        if (menuPickerState.type === 'categories') {
            if (scope && scope.value !== '') {
                url.searchParams.set('scope', scope.value);
            }
        } else if (status) {
            url.searchParams.set('status', status.value);
            if (menuPickerState.type === 'news' && category && category.value !== '') {
                url.searchParams.set('category_id', category.value);
            }
        }

        menuPickerState.loading = true;
        if (!append) {
            list.innerHTML = '';
            menuPickerState.offset = 0;
        }
        if (more) {
            more.hidden = true;
        }
        menuPickerStatus('Завантаження...');

        try {
            const response = await fetch(url.toString(), {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Не вдалося завантажити посилання.');
            }
            menuPickerState.offset = Number(data.next_offset || 0);
            menuPickerState.total = Number(data.total || 0);
            menuPickerState.hasMore = Boolean(data.has_more);
            renderMenuPickerItems(Array.isArray(data.items) ? data.items : [], append);
            menuPickerStatus(menuPickerState.total ? 'Знайдено: ' + menuPickerState.total + '.' : 'Нічого не знайдено.');
            if (more) {
                more.hidden = !menuPickerState.hasMore;
            }
        } catch (error) {
            menuPickerStatus(error.message || 'Помилка завантаження.');
        } finally {
            menuPickerState.loading = false;
        }
    }

    function renderMenuPickerItems(items, append) {
        const list = menuPickerNode.querySelector('[data-menu-picker-list]');
        if (!append) {
            list.innerHTML = '';
        }
        if (!items.length && !append) {
            list.innerHTML = '<div class="template-menu-picker-empty">За цими фільтрами немає результатів.</div>';
            return;
        }
        if (append) {
            const empty = list.querySelector('.template-menu-picker-empty');
            if (empty) {
                empty.remove();
            }
        }
        items.forEach(function (item) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'template-menu-picker-item';
            button.dataset.label = item.label || '';
            button.dataset.url = item.url || '';
            button.innerHTML =
                '<span class="template-menu-picker-item-icon mdi ' + menuPickerIcon(menuPickerState.type) + '" aria-hidden="true"></span>' +
                '<span class="template-menu-picker-item-body">' +
                    '<strong>' + escapeHtml(item.label || item.url || 'Посилання') + '</strong>' +
                    '<small>' + escapeHtml(item.meta || item.url || '') + '</small>' +
                    '<code class="w-100">' + escapeHtml(item.url || '#') + '</code>' +
                '</span>' +
                '<span class="mdi mdi-plus" aria-hidden="true"></span>';
            list.appendChild(button);
        });
    }

    function menuPickerIcon(type) {
        return {
            pages: 'mdi-file-document-outline',
            categories: 'mdi-shape-outline',
            news: 'mdi-newspaper-variant-outline'
        }[type] || 'mdi-link-variant';
    }

    function syncLayouts() {
        ensureTemplateLayout(selectedTemplate);
        layouts[selectedTemplate].header = cloneLayout(header);
        layouts[selectedTemplate].footer = cloneLayout(footer);
        layoutsInput.value = JSON.stringify(layouts);
        headerInput.value = JSON.stringify(header);
        footerInput.value = JSON.stringify(footer);
        renderHomePreview();
    }

    function renderPreviewHeader() {
        const links = [];
        if (header.show_home !== false) {
            links.push('<a href="#">Головна</a>');
        }
        (previewContext.menu || []).forEach(function (item) {
            if (item.slug && item.slug !== 'home') {
                links.push('<a href="#">' + escapeHtml(item.title || '') + '</a>');
            }
        });
        if (header.show_news !== false) {
            links.push('<a href="#">Новини</a>');
        }
        function renderPreviewLinks(items) {
            return (items || []).map(function (link) {
                const children = Array.isArray(link.children) ? link.children : [];
                if (!link.label && !children.length) {
                    return '';
                }
                return '<span class="site-menu-item">' +
                    '<a href="' + escapeHtml(previewUrl(link.url)) + '">' + escapeHtml(link.label || 'Пункт') + '</a>' +
                    (children.length ? '<span class="site-submenu">' + renderPreviewLinks(children) + '</span>' : '') +
                '</span>';
            }).join('');
        }
        (header.links || []).forEach(function (link) {
            if (link.label && link.url) {
                links.push(renderPreviewLinks([link]));
            }
        });

        return '<header class="topbar site-header site-header-' + escapeHtml(header.variant || 'default') + '">' +
            '<div class="container topbar-inner site-header-inner">' +
                (header.show_brand !== false ? '<a class="brand" href="#">' + escapeHtml(previewContext.institutionName || 'Заклад освіти') + '</a>' : '') +
                '<nav class="nav site-header-nav" aria-label="Головне меню">' + links.join('') + '</nav>' +
                (header.cta_label && header.cta_url ? '<a class="button site-header-cta" href="' + escapeHtml(previewUrl(header.cta_url)) + '">' + escapeHtml(header.cta_label) + '</a>' : '') +
            '</div>' +
        '</header>';
    }

    function renderPreviewFooter() {
        const columns = Array.isArray(footer.columns) ? footer.columns : [];
        let content = '';
        if (columns.length) {
            content = '<div class="row g-4 site-footer-grid">' + columns.map(function (column) {
                const items = Array.isArray(column.items) ? column.items : [];
                return '<div class="col-md"><section class="site-footer-card">' +
                    (column.title ? '<h2>' + escapeHtml(column.title) + '</h2>' : '') +
                    items.map(function (item) {
                        return '<p>' +
                            (item.url && item.label ? '<a href="' + escapeHtml(previewUrl(item.url)) + '">' + escapeHtml(item.label) + '</a>' : (item.label ? '<strong>' + escapeHtml(item.label) + '</strong>' : '')) +
                            (item.text ? '<span>' + escapeHtml(item.text) + '</span>' : '') +
                        '</p>';
                    }).join('') +
                '</section></div>';
            }).join('') + '</div>';
        } else {
            content = '<strong>' + escapeHtml(previewContext.institutionName || 'Заклад освіти') + '</strong><br>' +
                (previewContext.globalFields || []).map(function (field) {
                    return field.value ? '<span>' + escapeHtml(field.label || 'Поле') + ': ' + escapeHtml(field.value) + '</span><br>' : '';
                }).join('');
        }

        return '<footer class="footer site-footer site-footer-' + escapeHtml(footer.variant || 'default') + '">' +
            '<div class="container">' +
                content +
                (footer.bottom_text ? '<div class="site-footer-bottom">' + escapeHtml(footer.bottom_text) + '</div>' : '') +
            '</div>' +
        '</footer>';
    }

    function renderHomePreview() {
        if (!previewFrame) {
            return;
        }
        const template = (previewContext.templates || {})[selectedTemplate] || {};
        const title = previewContext.homeTitle || 'Головна';
        const excerpt = previewContext.homeExcerpt || 'Попередній перегляд головної сторінки сайту.';
        previewFrame.srcdoc = '<!doctype html><html lang="uk"><head>' +
            '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">' +
            '<link rel="stylesheet" href="' + escapeHtml(previewContext.bootstrapCss || '') + '">' +
            '<link rel="stylesheet" href="' + escapeHtml(previewContext.siteCss || '') + '">' +
            (template.css ? '<link rel="stylesheet" href="' + escapeHtml(template.css) + '">' : '') +
            '</head><body class="site-template-' + escapeHtml(selectedTemplate) + '">' +
            '<div class="template-preview-note">Перегляд: ' + escapeHtml(template.name || selectedTemplate) + ' <span>незбережені зміни</span></div>' +
            renderPreviewHeader() +
            '<main><section class="hero"><div class="container hero-inner"><h1>' + escapeHtml(title) + '</h1><p>' + escapeHtml(excerpt) + '</p></div></section>' +
            '<section class="section"><div class="container"><div class="grid grid-3"><article class="card"><p class="meta">Блок</p><h3>Про заклад</h3><p>Тут буде контент головної сторінки.</p></article><article class="card"><p class="meta">Блок</p><h3>Новини</h3><p>Приклад картки для оцінки відступів і кольорів.</p></article><article class="card"><p class="meta">Блок</p><h3>Контакти</h3><p>Додатковий блок для перевірки сітки.</p></article></div></div></section></main>' +
            renderPreviewFooter() +
            '</body></html>';
    }

    function renderHeader() {
        headerEditor.querySelector('[data-header-field="variant"]').value = header.variant || 'default';
        headerEditor.querySelector('[data-header-field="cta_label"]').value = header.cta_label || '';
        headerEditor.querySelector('[data-header-field="cta_url"]').value = header.cta_url || '';
        headerEditor.querySelector('[data-header-field="show_brand"]').checked = header.show_brand !== false;
        headerEditor.querySelector('[data-header-field="show_home"]').checked = header.show_home !== false;
        headerEditor.querySelector('[data-header-field="show_news"]').checked = header.show_news !== false;
        headerEditor.querySelector('[data-header-links]').innerHTML = renderMenuLinks(header.links || [], '', 0);
        fillMenuParentSelect();
        syncLayouts();
    }

    function renderFooter() {
        footerEditor.querySelector('[data-footer-field="variant"]').value = footer.variant || 'default';
        footerEditor.querySelector('[data-footer-field="bottom_text"]').value = footer.bottom_text || '';
        footerEditor.querySelector('[data-footer-columns]').innerHTML = (footer.columns || []).map(function (column, columnIndex) {
            return '<section class="template-footer-column" data-footer-column="' + columnIndex + '">' +
                '<div class="template-editor-list-head">' +
                    '<label>Назва колонки<input data-footer-column-field="title" value="' + escapeHtml(column.title) + '"></label>' +
                    '<button class="button danger compact" type="button" data-footer-remove-column><span class="mdi mdi-delete-outline"></span></button>' +
                '</div>' +
                '<div class="template-editor-list">' + (column.items || []).map(function (item, itemIndex) {
                    return '<div class="template-editor-row" data-footer-item="' + itemIndex + '">' +
                        '<label>Назва<input data-footer-item-field="label" value="' + escapeHtml(item.label) + '"></label>' +
                        '<label>Текст<input data-footer-item-field="text" value="' + escapeHtml(item.text) + '"></label>' +
                        '<label>URL<input data-footer-item-field="url" value="' + escapeHtml(item.url) + '"></label>' +
                        '<button class="button danger compact" type="button" data-footer-remove-item><span class="mdi mdi-delete-outline"></span></button>' +
                    '</div>';
                }).join('') + '</div>' +
                '<button class="button secondary compact" type="button" data-footer-add-item><span class="mdi mdi-plus"></span><span>Пункт</span></button>' +
            '</section>';
        }).join('');
        syncLayouts();
    }

    headerEditor.addEventListener('input', function (event) {
        const field = event.target.closest('[data-header-field]');
        const linkField = event.target.closest('[data-header-link-field]');
        if (field) {
            const key = field.dataset.headerField;
            header[key] = field.type === 'checkbox' ? field.checked : field.value;
        }
        if (linkField) {
            const row = linkField.closest('[data-header-link]');
            const item = getMenuItem(row.dataset.headerLink);
            if (item) {
                item[linkField.dataset.headerLinkField] = linkField.value;
            }
        }
        syncLayouts();
    });
    headerEditor.addEventListener('change', function (event) {
        const field = event.target.closest('[data-header-field]');
        if (field) {
            const key = field.dataset.headerField;
            header[key] = field.type === 'checkbox' ? field.checked : field.value;
            syncLayouts();
        }
    });
    headerEditor.addEventListener('click', function (event) {
        if (event.target.closest('[data-menu-picker-open]')) {
            openMenuPicker();
        }
        if (event.target.closest('[data-header-add-link]')) {
            header.links = header.links || [];
            header.links.push(makeMenuItem('', ''));
            renderHeader();
        }
        const addChild = event.target.closest('[data-header-add-child]');
        if (addChild) {
            addMenuChild(addChild.closest('[data-header-link]').dataset.headerLink);
            renderHeader();
        }
        const remove = event.target.closest('[data-header-remove-link]');
        if (remove) {
            removeMenuItem(remove.closest('[data-header-link]').dataset.headerLink);
            renderHeader();
        }
    });

    if (menuPickerNode) {
        menuPickerNode.addEventListener('click', function (event) {
            const typeButton = event.target.closest('[data-menu-picker-type]');
            if (typeButton) {
                setMenuPickerType(typeButton.dataset.menuPickerType);
                return;
            }
            if (event.target.closest('[data-menu-picker-more]')) {
                loadMenuPickerItems(true);
                return;
            }
            const itemButton = event.target.closest('.template-menu-picker-item');
            if (itemButton) {
                addPickedMenuItem({
                    label: itemButton.dataset.label || '',
                    url: itemButton.dataset.url || ''
                });
                renderHeader();
                if (menuPickerModal) {
                    menuPickerModal.hide();
                }
            }
        });
        menuPickerNode.addEventListener('input', function (event) {
            if (!event.target.closest('[data-menu-picker-search]')) {
                return;
            }
            window.clearTimeout(menuPickerState.searchTimer);
            menuPickerState.searchTimer = window.setTimeout(resetMenuPicker, 250);
        });
        menuPickerNode.addEventListener('change', function (event) {
            if (event.target.closest('[data-menu-picker-status], [data-menu-picker-scope], [data-menu-picker-category]')) {
                resetMenuPicker();
            }
        });
        const menuPickerBody = menuPickerNode.querySelector('.modal-body');
        if (menuPickerBody) {
            menuPickerBody.addEventListener('scroll', function () {
                const nearBottom = menuPickerBody.scrollTop + menuPickerBody.clientHeight >= menuPickerBody.scrollHeight - 80;
                if (nearBottom && menuPickerState.hasMore && !menuPickerState.loading) {
                    loadMenuPickerItems(true);
                }
            });
        }
    }

    footerEditor.addEventListener('input', function (event) {
        const field = event.target.closest('[data-footer-field]');
        const columnField = event.target.closest('[data-footer-column-field]');
        const itemField = event.target.closest('[data-footer-item-field]');
        if (field) {
            footer[field.dataset.footerField] = field.value;
        }
        if (columnField) {
            const column = columnField.closest('[data-footer-column]');
            footer.columns[Number(column.dataset.footerColumn)][columnField.dataset.footerColumnField] = columnField.value;
        }
        if (itemField) {
            const column = itemField.closest('[data-footer-column]');
            const item = itemField.closest('[data-footer-item]');
            footer.columns[Number(column.dataset.footerColumn)].items[Number(item.dataset.footerItem)][itemField.dataset.footerItemField] = itemField.value;
        }
        syncLayouts();
    });
    footerEditor.addEventListener('change', function (event) {
        const field = event.target.closest('[data-footer-field]');
        if (field) {
            footer[field.dataset.footerField] = field.value;
            syncLayouts();
        }
    });
    templateRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (!radio.checked) {
                return;
            }
            syncLayouts();
            selectedTemplate = radio.value;
            if (currentTemplateName) {
                currentTemplateName.textContent = radio.dataset.templateLabel || radio.value;
            }
            ensureTemplateLayout(selectedTemplate);
            header = cloneLayout(layouts[selectedTemplate].header);
            footer = cloneLayout(layouts[selectedTemplate].footer);
            renderHeader();
            renderFooter();
        });
    });

    document.querySelector('form[action$="/admin/templates/save"]')?.addEventListener('submit', function () {
        syncLayouts();
    });
    footerEditor.addEventListener('click', function (event) {
        if (event.target.closest('[data-footer-add-column]')) {
            footer.columns = footer.columns || [];
            footer.columns.push({title: '', items: [{label: '', text: '', url: ''}]});
            renderFooter();
        }
        const removeColumn = event.target.closest('[data-footer-remove-column]');
        if (removeColumn) {
            footer.columns.splice(Number(removeColumn.closest('[data-footer-column]').dataset.footerColumn), 1);
            renderFooter();
        }
        const addItem = event.target.closest('[data-footer-add-item]');
        if (addItem) {
            const column = addItem.closest('[data-footer-column]');
            footer.columns[Number(column.dataset.footerColumn)].items.push({label: '', text: '', url: ''});
            renderFooter();
        }
        const removeItem = event.target.closest('[data-footer-remove-item]');
        if (removeItem) {
            const column = removeItem.closest('[data-footer-column]');
            const item = removeItem.closest('[data-footer-item]');
            footer.columns[Number(column.dataset.footerColumn)].items.splice(Number(item.dataset.footerItem), 1);
            renderFooter();
        }
    });

    renderHeader();
    renderFooter();
});
