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
    const summaryTemplateName = document.querySelector('[data-summary-template-name]');
    const menuStat = document.querySelector('[data-template-stat-menu]');
    const footerStat = document.querySelector('[data-template-stat-footer]');
    const saveState = document.querySelector('[data-template-save-state]');
    const saveTitle = document.querySelector('[data-template-save-title]');
    const saveCopy = document.querySelector('[data-template-save-copy]');
    const saveBar = document.querySelector('.template-save-bar');
    const revertButton = document.querySelector('[data-template-revert]');
    const templateForm = document.querySelector('[data-template-form]');
    const pickerToggle = document.querySelector('[data-template-picker-toggle]');
    const pickerOptions = document.querySelector('[data-template-picker-options]');
    const previewFrame = document.querySelector('[data-template-home-preview]');
    const previewShell = document.querySelector('[data-template-preview-shell]');
    const previewPanel = document.querySelector('.template-preview-panel');
    const builderLayout = document.querySelector('.template-builder-layout');
    const previewCollapseButton = document.querySelector('[data-template-preview-collapse]');
    const previewModeButtons = document.querySelectorAll('[data-template-preview-mode]');
    const editorTabButtons = document.querySelectorAll('[data-template-editor-tab]');
    const editorTabPanels = document.querySelectorAll('[data-template-tab-panel]');
    const previewContext = previewFrame ? parseJson(previewFrame.dataset.context, {}) : {};
    const linkPicker = previewContext.linkPicker || {pages: [], categories: [], news: [], media: []};
    const defaultHeader = {
        variant: 'default',
        show_brand: true,
        show_home: false,
        show_news: false,
        links: [],
        cta_label: '',
        cta_url: '',
        hero_enabled: false,
        hero_variant: 'default',
        hero_title: '',
        hero_text: '',
        hero_button_label: '',
        hero_button_url: '',
        hero_background_image: '',
        hero_background_position: 'center center',
        hero_background_size: 'cover',
        hero_background_repeat: 'no-repeat',
        hero_overlay_enabled: true,
        hero_overlay_opacity: '35',
        home_hero_enabled: false,
        home_hero_variant: 'fullscreen',
        home_hero_title: '',
        home_hero_text: '',
        home_hero_button_label: '',
        home_hero_button_url: '',
        home_hero_background_image: '',
        home_hero_background_position: 'center center',
        home_hero_background_size: 'cover',
        home_hero_background_repeat: 'no-repeat',
        home_hero_overlay_enabled: true,
        home_hero_overlay_opacity: '35',
        secondary_enabled: false,
        secondary_variant: 'pills',
        secondary_links: [],
        mobile_variant: 'drawer',
        mobile_source: 'main',
        mobile_label: 'Меню',
        mobile_show_brand: true,
        mobile_show_cta: true
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
    let baselineLayouts = '';
    let menuSearchQuery = '';
    let menuIssuesOnly = false;
    let menuIssueCursor = -1;
    let activeEditorTab = 'menu';
    const expandedMenuNodes = new Set();
    const menuPickerNode = document.getElementById('templateMenuPickerModal');
    const menuPickerModal = menuPickerNode && window.bootstrap ? new window.bootstrap.Modal(menuPickerNode) : null;
    const menuTemplateNode = document.getElementById('templateMenuBlueprintModal');
    const menuTemplateModal = menuTemplateNode && window.bootstrap ? new window.bootstrap.Modal(menuTemplateNode) : null;
    const heroBackgroundNode = document.getElementById('templateHeroBackgroundModal');
    let heroBackgroundModal = heroBackgroundNode && window.bootstrap ? new window.bootstrap.Modal(heroBackgroundNode) : null;
    const iconPickerNode = document.getElementById('templateIconPickerModal');
    const iconPickerModal = iconPickerNode && window.bootstrap ? new window.bootstrap.Modal(iconPickerNode) : null;
    const iconPickerState = {path: '', input: null};
    const heroBackgroundState = {target: 'hero', offset: 0, limit: 30, total: 0, hasMore: false, loading: false, token: 0, searchTimer: null, items: []};
    const menuTemplateState = {kind: 'menu', type: '', target: 'main', payload: null};
    const menuIconOptions = [
        'home-outline', 'home-city-outline', 'view-dashboard-outline', 'menu', 'menu-open', 'apps', 'view-grid-outline',
        'view-list-outline', 'format-list-group', 'sitemap-outline', 'subdirectory-arrow-right', 'link-variant',
        'open-in-new', 'arrow-right', 'chevron-right', 'login', 'logout', 'cog-outline', 'tune-variant',
        'file-document-outline', 'file-document-edit-outline', 'file-document-multiple-outline', 'file-tree-outline',
        'file-pdf-box', 'file-word-outline', 'file-excel-outline', 'folder-outline', 'folder-open-outline',
        'folder-image', 'archive-outline', 'download-outline', 'upload-outline', 'cloud-upload-outline',
        'cloud-download-outline', 'printer-outline', 'content-save-outline', 'clipboard-list-outline',
        'clipboard-text-outline', 'text-box-outline', 'note-text-outline', 'book-open-page-variant-outline',
        'book-education-outline', 'book-open-outline', 'library-outline', 'school-outline', 'google-classroom',
        'human-male-board', 'account-school-outline', 'account-group-outline', 'account-tie-outline',
        'account-child-outline', 'account-multiple-outline',  'desk', 'laptop', 
        'monitor', 'tablet', 'cellphone', 'web', 'web-box', 'earth', 'wifi', 'qrcode', 'barcode-scan',
        'newspaper-variant-outline', 'newspaper', 'rss', 'bullhorn-outline', 'bullhorn-variant-outline',
        'message-text-outline', 'comment-text-outline', 'comment-question-outline', 'forum-outline',
        'email-outline', 'phone-outline', 'phone-in-talk-outline', 'map-marker-outline', 'map-outline',
        'crosshairs-gps', 'clock-outline', 'calendar-month-outline', 'calendar-clock-outline',
        'calendar-check-outline', 'calendar-star', 'alarm', 'timer-outline', 'bell-outline', 'bell-ring-outline',
        'information-outline', 'help-circle-outline', 'alert-circle-outline', 'alert-outline',
        'check-circle-outline', 'checkbox-marked-circle-outline', 'close-circle-outline', 'minus-circle-outline',
        'plus-circle-outline', 'plus-box-outline', 'delete-outline', 'pencil-outline', 'square-edit-outline',
        'magnify', 'filter-outline', 'sort', 'refresh', 'sync', 'eye-outline', 'eye-off-outline',
        'lock-outline', 'lock-open-outline', 'shield-outline', 'shield-check-outline', 'key-outline',
        'certificate-outline', 'medal-outline', 'trophy-outline', 'star-outline', 'star-circle-outline',
        'heart-outline', 'thumb-up-outline', 'handshake-outline', 'briefcase-outline', 'briefcase-account-outline',
        'chart-box-outline', 'chart-line', 'poll', 'finance', 'cash-multiple', 'wallet-outline',
        'cart-outline', 'gift-outline', 'tag-outline', 'bookmark-outline', 'flag-outline',
        'image-outline', 'image-multiple-outline', 'camera-outline', 'video-outline', 'play-circle-outline',
        'music-note-outline', 'microphone-outline', 'palette-outline', 'brush-outline', 'draw',
        'lightbulb-outline', 'rocket-launch-outline', 'target', 'puzzle-outline', 'tools', 'wrench-outline',
        'hammer-wrench', 'flask-outline', 'test-tube', 'microscope', 'atom', 'calculator-variant-outline',
        'ruler-square', 'compass-outline', 'leaf', 'tree-outline', 'flower-outline', 'weather-sunny',
        'weather-cloudy', 'water-outline', 'fire', 'food-apple-outline', 'silverware-fork-knife',
        'medical-bag', 'hospital-box-outline', 'bus-school', 'bus', 'car-outline', 'bike', 'walk',
        'run', 'soccer', 'basketball', 'volleyball', 'swim', 'dumbbell', 'drama-masks',
        'theater', 'music', 'account-music-outline', 'translate', 'flag-variant-outline'
    ];
    const menuPickerState = {
        type: 'pages',
        offset: 0,
        limit: 20,
        total: 0,
        hasMore: false,
        loading: false,
        searchTimer: null,
        selected: new Map()
    };

    function setTemplateEditorTab(tab) {
        const activeTab = tab || 'menu';
        activeEditorTab = activeTab;
        editorTabButtons.forEach(function (button) {
            const active = button.dataset.templateEditorTab === activeTab;
            button.classList.toggle('secondary', !active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        editorTabPanels.forEach(function (panel) {
            panel.hidden = panel.dataset.templateTabPanel !== activeTab;
        });
        renderHomePreview();
    }

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

    function cssEscape(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(String(value));
        }
        return String(value).replace(/["\\]/g, '\\$&');
    }

    function previewUrl(value) {
        const url = String(value || '').trim();
        return /^(https?:\/\/|mailto:|tel:|\/|#)/i.test(url) ? url : '#';
    }

    function uploadUrl(path) {
        const cleanPath = String(path || '').trim().replace(/^\/+/, '');
        if (!cleanPath) {
            return '';
        }
        const media = (linkPicker.media || []).find(function (item) {
            return String(item.path || '') === cleanPath || String(item.url || '') === cleanPath;
        });
        if (media && media.url) {
            return media.url;
        }
        return '/uploads/' + cleanPath;
    }

    function mediaThumbUrl(value, width, height) {
        let cleanPath = String(value || '').trim();
        if (!cleanPath) {
            return '';
        }

        if (/^https?:\/\//i.test(cleanPath)) {
            try {
                const parsed = new URL(cleanPath);
                if (parsed.origin !== window.location.origin) {
                    return cleanPath;
                }
                cleanPath = parsed.pathname;
            } catch (error) {
                return cleanPath;
            }
        }

        cleanPath = cleanPath.replace(/^\/+/, '');
        if (cleanPath.indexOf('uploads/') === 0) {
            cleanPath = cleanPath.slice(8);
        }
        if (cleanPath.indexOf('thumb/') === 0) {
            cleanPath = cleanPath.slice(6);
        }
        cleanPath = cleanPath.split('?')[0];

        if (!cleanPath) {
            return '';
        }

        return '/thumb/' + encodeURI(cleanPath) + '?w=' + Number(width || 360) + '&h=' + Number(height || 225) + '&fit=cover';
    }

    function mediaItemPath(item) {
        return String((item && item.path) || '').trim();
    }

    function mediaItemLabel(item) {
        return String((item && (item.label || item.name || item.path)) || '').trim();
    }

    function mediaItemIsImage(item) {
        if (!item) {
            return false;
        }
        if (Object.prototype.hasOwnProperty.call(item, 'is_image')) {
            return Boolean(item.is_image);
        }
        return /\.(jpe?g|png|webp|gif)$/i.test(mediaItemPath(item) || String(item.url || ''));
    }

    function heroBackgroundFieldName(target) {
        return target === 'home' ? 'home_hero_background_image' : 'hero_background_image';
    }

    function heroBackgroundTargetLabel(target) {
        return target === 'home' ? 'Hero головної сторінки' : 'Hero під хедером';
    }

    function heroBackgroundMeta(template) {
        const image = String(template.hero_background_image || '').trim();
        if (!image) {
            return {className: '', style: ''};
        }
        const position = ['center center', 'center top', 'center bottom', 'left center', 'right center'].includes(template.hero_background_position) ? template.hero_background_position : 'center center';
        const size = ['cover', 'contain', 'auto'].includes(template.hero_background_size) ? template.hero_background_size : 'cover';
        const repeat = ['no-repeat', 'repeat', 'repeat-x', 'repeat-y'].includes(template.hero_background_repeat) ? template.hero_background_repeat : 'no-repeat';
        const opacity = Math.max(0, Math.min(80, Number(template.hero_overlay_opacity || 35))) / 100;
        const style = '--site-hero-bg-image: url(&quot;' + escapeHtml(uploadUrl(image)) + '&quot;); --site-hero-bg-position: ' + escapeHtml(position) + '; --site-hero-bg-size: ' + escapeHtml(size) + '; --site-hero-bg-repeat: ' + escapeHtml(repeat) + '; --site-hero-overlay-opacity: ' + escapeHtml(String(opacity)) + ';';
        const className = ' site-header-hero-has-background' + (template.hero_overlay_enabled === false ? '' : ' site-header-hero-has-overlay');
        return {className: className, style: style};
    }

    function makeMenuItem(label, url) {
        return {type: 'link', label: label || '', url: url || '', icon: '', children: []};
    }

    function cleanMenuLabel(label) {
        return String(label || '').replace(/^((—|–|-)\s*)+/, '').trim();
    }

    function makeMenuSection(label) {
        return {type: 'section', label: label || '', url: '#', icon: '', children: [], columns: []};
    }

    function makeMenuTemplateItem(label, url, icon) {
        const item = makeMenuItem(cleanMenuLabel(label), url);
        item.icon = icon || '';
        return item;
    }

    function makeMenuTemplateSection(label, icon, children, columns) {
        const section = makeMenuSection(label);
        section.icon = icon || '';
        section.children = Array.isArray(children) ? children : [];
        section.columns = Array.isArray(columns) ? columns : [];
        return section;
    }

    function mdiIconClass(value) {
        const icon = String(value || '').trim().replace(/^mdi\s+/, '').replace(/^mdi-/, '');
        return /^[a-z0-9-]+$/i.test(icon) ? 'mdi-' + icon : '';
    }

    function renderMenuLabel(label, icon, fallback) {
        const iconClass = mdiIconClass(icon);
        return (iconClass ? '<span class="mdi ' + escapeHtml(iconClass) + '" aria-hidden="true"></span>' : '') +
            '<span>' + escapeHtml(label || fallback) + '</span>';
    }

    function iconPickerLabel(icon) {
        return icon.replace(/-/g, ' ');
    }

    function renderIconPicker() {
        if (!iconPickerNode) {
            return;
        }
        const grid = iconPickerNode.querySelector('[data-icon-picker-grid]');
        const search = iconPickerNode.querySelector('[data-icon-picker-search]');
        const status = iconPickerNode.querySelector('[data-icon-picker-status]');
        const query = search ? search.value.trim().toLowerCase() : '';
        const selected = iconPickerState.input ? mdiIconClass(iconPickerState.input.value).replace(/^mdi-/, '') : '';
        let icons = menuIconOptions.filter(function (icon) {
            return query === '' || icon.includes(query) || iconPickerLabel(icon).includes(query);
        });
        const customIcon = mdiIconClass(query).replace(/^mdi-/, '');
        if (customIcon && !icons.includes(customIcon)) {
            icons = [customIcon].concat(icons);
        }
        if (status) {
            status.textContent = icons.length ? 'Знайдено: ' + icons.length : 'Нічого не знайдено.';
        }
        if (!grid) {
            return;
        }
        grid.innerHTML = icons.length ? icons.map(function (icon) {
            const isSelected = selected === icon;
            return '<button class="template-icon-option' + (isSelected ? ' is-selected' : '') + '" type="button" data-icon-picker-select="' + escapeHtml(icon) + '">' +
                '<span class="mdi mdi-' + escapeHtml(icon) + '" aria-hidden="true"></span>' +
                '<span>' + escapeHtml(icon) + '</span>' +
            '</button>';
        }).join('') : '<div class="template-icon-picker-empty">Немає іконок за цим пошуком.</div>';
    }

    function openIconPicker(button) {
        if (!iconPickerModal) {
            return;
        }
        const row = button.closest('[data-header-link]');
        iconPickerState.path = row ? row.dataset.headerLink : '';
        iconPickerState.input = row ? row.querySelector('[data-header-link-field="icon"]') : null;
        const search = iconPickerNode.querySelector('[data-icon-picker-search]');
        if (search) {
            search.value = '';
        }
        renderIconPicker();
        iconPickerModal.show();
        if (search) {
            setTimeout(function () { search.focus(); }, 180);
        }
    }

    function applyPickedIcon(icon) {
        const item = getMenuItem(iconPickerState.path);
        const value = mdiIconClass(icon);
        if (item) {
            item.icon = value;
        }
        if (iconPickerState.input) {
            iconPickerState.input.value = value;
        }
        renderHeader();
        if (iconPickerModal) {
            iconPickerModal.hide();
        }
    }

    function isDirty() {
        return baselineLayouts !== '' && JSON.stringify(layouts) !== baselineLayouts;
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
        layouts[template].header.show_home = false;
        layouts[template].header.show_news = false;
        layouts[template].header.links = Array.isArray(layouts[template].header.links) ? layouts[template].header.links : [];
        layouts[template].header.secondary_links = Array.isArray(layouts[template].header.secondary_links) ? layouts[template].header.secondary_links : [];
        layouts[template].footer.columns = Array.isArray(layouts[template].footer.columns) ? layouts[template].footer.columns : [];
    }

    function getMenuItem(path) {
        const parts = String(path || '').split('.').filter(Boolean);
        let items = header.links || [];
        let item = null;
        parts.forEach(function (index) {
            if (index === 'secondary') {
                header.secondary_links = Array.isArray(header.secondary_links) ? header.secondary_links : [];
                items = header.secondary_links;
                item = null;
                return;
            }
            if (/^c\d+$/.test(index)) {
                const columnIndex = Number(index.slice(1));
                const columns = item && Array.isArray(item.columns) ? item.columns : [];
                const column = columns[columnIndex];
                items = column && Array.isArray(column.children) ? column.children : [];
                item = null;
                return;
            }
            item = items[Number(index)];
            items = item && Array.isArray(item.children) ? item.children : [];
        });
        return item;
    }

    function duplicateMenuItem(path) {
        const parts = String(path || '').split('.').filter(Boolean);
        const index = Number(parts.pop());
        const items = menuListForPath(path);
        const item = Number.isInteger(index) ? items[index] : null;
        if (!item) {
            return '';
        }
        const copy = cloneLayout(item);
        if (copy.label) {
            copy.label += ' (копія)';
        }
        items.splice(index + 1, 0, copy);
        return (parts.length ? parts.join('.') + '.' : '') + String(index + 1);
    }

    function removeMenuItem(path) {
        const parts = String(path || '').split('.').filter(Boolean);
        const index = parts.pop();
        let items = header.links || [];
        let item = null;
        parts.forEach(function (part) {
            if (part === 'secondary') {
                header.secondary_links = Array.isArray(header.secondary_links) ? header.secondary_links : [];
                items = header.secondary_links;
                item = null;
                return;
            }
            if (/^c\d+$/.test(part)) {
                const column = item && Array.isArray(item.columns) ? item.columns[Number(part.slice(1))] : null;
                items = column && Array.isArray(column.children) ? column.children : [];
                item = null;
                return;
            }
            item = items[Number(part)];
            item.children = Array.isArray(item.children) ? item.children : [];
            items = item.children;
        });
        if (/^\d+$/.test(String(index))) {
            items.splice(Number(index), 1);
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

    function addMenuSection(path) {
        const section = makeMenuSection('');
        header.links = Array.isArray(header.links) ? header.links : [];
        if (!path) {
            header.links.push(section);
            return;
        }
        if (path === 'secondary') {
            header.secondary_links = Array.isArray(header.secondary_links) ? header.secondary_links : [];
            header.secondary_links.push(section);
            return;
        }
        const parent = getMenuItem(path);
        if (!parent) {
            header.links.push(section);
            return;
        }
        parent.children = Array.isArray(parent.children) ? parent.children : [];
        parent.children.push(section);
    }

    function addSectionColumn(path) {
        const section = getMenuItem(path);
        if (!section) {
            return;
        }
        section.type = 'section';
        section.columns = Array.isArray(section.columns) ? section.columns : [];
        section.columns.push({title: '', children: []});
    }

    function removeSectionColumn(path, columnIndex) {
        const section = getMenuItem(path);
        if (!section || !Array.isArray(section.columns)) {
            return;
        }
        section.columns.splice(columnIndex, 1);
    }

    function addColumnMenuItem(path, columnIndex) {
        const section = getMenuItem(path);
        if (!section) {
            return;
        }
        section.columns = Array.isArray(section.columns) ? section.columns : [];
        section.columns[columnIndex] = section.columns[columnIndex] || {title: '', children: []};
        section.columns[columnIndex].children = Array.isArray(section.columns[columnIndex].children) ? section.columns[columnIndex].children : [];
        section.columns[columnIndex].children.push(makeMenuItem('', ''));
    }

    function menuListForPath(path) {
        const parts = String(path || '').split('.').filter(Boolean);
        parts.pop();
        let items = header.links || [];
        let item = null;
        parts.forEach(function (part) {
            if (part === 'secondary') {
                header.secondary_links = Array.isArray(header.secondary_links) ? header.secondary_links : [];
                items = header.secondary_links;
                item = null;
                return;
            }
            if (/^c\d+$/.test(part)) {
                const column = item && Array.isArray(item.columns) ? item.columns[Number(part.slice(1))] : null;
                items = column && Array.isArray(column.children) ? column.children : [];
                item = null;
                return;
            }
            item = items[Number(part)];
            item.children = Array.isArray(item.children) ? item.children : [];
            items = item.children;
        });
        return items;
    }

    function menuContainerForPath(path) {
        const parts = String(path || '').split('.').filter(Boolean);
        if (!parts.length) {
            header.links = Array.isArray(header.links) ? header.links : [];
            return header.links;
        }
        if (parts[0] === 'secondary') {
            header.secondary_links = Array.isArray(header.secondary_links) ? header.secondary_links : [];
            if (parts.length === 1) {
                return header.secondary_links;
            }
        }
        let items = header.links || [];
        let item = null;
        for (let i = 0; i < parts.length; i += 1) {
            const part = parts[i];
            if (part === 'secondary') {
                items = header.secondary_links;
                item = null;
                continue;
            }
            if (/^c\d+$/.test(part)) {
                const column = item && Array.isArray(item.columns) ? item.columns[Number(part.slice(1))] : null;
                if (!column) {
                    return header.links;
                }
                column.children = Array.isArray(column.children) ? column.children : [];
                items = column.children;
                item = null;
                continue;
            }
            item = items[Number(part)];
            if (!item) {
                return header.links;
            }
            item.children = Array.isArray(item.children) ? item.children : [];
            items = item.children;
        }
        return items;
    }

    function moveMenuItem(path, direction) {
        const parts = String(path || '').split('.').filter(Boolean);
        const index = parts.pop();
        const items = menuListForPath(path);
        const numericIndex = Number(index);
        const target = numericIndex + direction;
        if (!Number.isInteger(numericIndex) || target < 0 || target >= items.length) {
            return;
        }
        const moved = items.splice(numericIndex, 1)[0];
        items.splice(target, 0, moved);
    }

    function normalizeMenuSearch(value) {
        return String(value || '').trim().toLowerCase();
    }

    function menuItemSearchText(item) {
        return [
            item.type === 'section' ? 'секція section' : 'посилання link',
            item.label || '',
            item.url || '',
            item.icon || ''
        ].join(' ').toLowerCase();
    }

    function menuItemMatchesSearch(item, query) {
        return !query || menuItemSearchText(item).indexOf(query) !== -1;
    }

    function menuBranchMatchesSearch(item, query) {
        if (!query || menuItemMatchesSearch(item, query)) {
            return true;
        }
        if ((Array.isArray(item.children) ? item.children : []).some(function (child) {
            return menuBranchMatchesSearch(child, query);
        })) {
            return true;
        }
        return (Array.isArray(item.columns) ? item.columns : []).some(function (column) {
            const titleMatches = String(column.title || '').toLowerCase().indexOf(query) !== -1;
            const childrenMatch = (Array.isArray(column.children) ? column.children : []).some(function (child) {
                return menuBranchMatchesSearch(child, query);
            });
            return titleMatches || childrenMatch;
        });
    }

    function collectMenuPaths(items, parentPath) {
        const paths = [];
        (items || []).forEach(function (item, index) {
            const path = parentPath ? parentPath + '.' + index : String(index);
            paths.push(path);
            paths.push.apply(paths, collectMenuPaths(Array.isArray(item.children) ? item.children : [], path));
            (Array.isArray(item.columns) ? item.columns : []).forEach(function (column, columnIndex) {
                paths.push.apply(paths, collectMenuPaths(Array.isArray(column.children) ? column.children : [], path + '.c' + columnIndex));
            });
        });
        return paths;
    }

    function renderSectionColumns(section, path, depth, query, issuesOnly, duplicateUrls) {
        const columns = Array.isArray(section.columns) ? section.columns : [];
        if (!columns.length) {
            return '';
        }
        const visibleColumns = columns.map(function (column, columnIndex) {
            return {column: column, index: columnIndex};
        }).filter(function (entry) {
            if (!query && !issuesOnly) {
                return true;
            }
            const titleMatches = String(entry.column.title || '').toLowerCase().indexOf(query) !== -1;
            const children = Array.isArray(entry.column.children) ? entry.column.children : [];
            const matchesSearch = !query || titleMatches || children.some(function (child) {
                return menuBranchMatchesSearch(child, query);
            });
            const matchesIssues = !issuesOnly || children.some(function (child) {
                return menuBranchHasIssues(child, duplicateUrls);
            });
            return matchesSearch && matchesIssues;
        });
        if (!visibleColumns.length) {
            return '';
        }
        return '<div class="template-menu-columns">' + visibleColumns.map(function (entry) {
            const column = entry.column;
            const columnIndex = entry.index;
            const children = Array.isArray(column.children) ? column.children : [];
            const hasVisibleChildren = (query || issuesOnly) ? children.some(function (child) {
                return (!query || menuBranchMatchesSearch(child, query)) && (!issuesOnly || menuBranchHasIssues(child, duplicateUrls));
            }) : children.length > 0;
            return '<section class="template-menu-column" data-menu-column="' + columnIndex + '">' +
                '<div class="template-menu-column-head">' +
                    '<label>Назва колонки<input data-header-column-field="title" value="' + escapeHtml(column.title || '') + '" placeholder="Необов’язково"></label>' +
                    '<div class="template-menu-actions">' +
                        '<button class="button secondary compact" type="button" data-header-column-add-link title="Додати пункт"><span class="mdi mdi-plus"></span></button>' +
                        '<button class="button danger compact" type="button" data-header-column-remove title="Видалити колонку"><span class="mdi mdi-delete-outline"></span></button>' +
                    '</div>' +
                '</div>' +
                (hasVisibleChildren ? '<div class="template-menu-column-items">' + renderMenuLinks(children, path + '.c' + columnIndex, depth + 1, query, issuesOnly, duplicateUrls) + '</div>' : '<div class="template-menu-column-empty">Пунктів ще немає.</div>') +
            '</section>';
        }).join('') + '</div>';
    }

    function normalizedMenuUrl(url) {
        const value = String(url || '').trim();
        if (!value) {
            return '';
        }
        return value === '/' ? '/' : value.replace(/\/+$/, '');
    }

    function collectMenuUrlCounts(items, counts) {
        (items || []).forEach(function (item) {
            const type = item.type === 'section' ? 'section' : 'link';
            const url = normalizedMenuUrl(item.url || '');
            if (type === 'link' && url && url !== '#') {
                counts[url] = (counts[url] || 0) + 1;
            }
            collectMenuUrlCounts(Array.isArray(item.children) ? item.children : [], counts);
            (Array.isArray(item.columns) ? item.columns : []).forEach(function (column) {
                collectMenuUrlCounts(Array.isArray(column.children) ? column.children : [], counts);
            });
        });
        return counts;
    }

    function duplicatedMenuUrls(items) {
        const counts = collectMenuUrlCounts(items, {});
        return Object.keys(counts).reduce(function (duplicates, url) {
            if (counts[url] > 1) {
                duplicates[url] = true;
            }
            return duplicates;
        }, {});
    }

    function menuItemSummaryMeta(link, type, duplicateUrls) {
        const pieces = [];
        const issues = menuItemIssues(link, type, duplicateUrls);
        const childCount = countMenuItems(Array.isArray(link.children) ? link.children : []);
        const columns = Array.isArray(link.columns) ? link.columns : [];
        const columnsItemCount = columns.reduce(function (total, column) {
            return total + countMenuItems(Array.isArray(column.children) ? column.children : []);
        }, 0);
        if (type === 'link') {
            pieces.push(link.url ? link.url : 'URL не вказано');
        } else {
            pieces.push('Секція меню');
        }
        if (childCount) {
            pieces.push(childCount + ' ' + pluralizeUk(childCount, 'підпункт', 'підпункти', 'підпунктів'));
        }
        if (columns.length) {
            pieces.push(columns.length + ' ' + pluralizeUk(columns.length, 'колонка', 'колонки', 'колонок'));
        }
        if (columnsItemCount) {
            pieces.push(columnsItemCount + ' ' + pluralizeUk(columnsItemCount, 'пункт у колонках', 'пункти у колонках', 'пунктів у колонках'));
        }
        if (issues.length) {
            pieces.push('Проблеми: ' + issues.join(', '));
        }
        return pieces.join(' · ');
    }

    function menuItemIssues(item, type, duplicateUrls) {
        const issues = [];
        const url = normalizedMenuUrl(item.url || '');
        if (!String(item.label || '').trim()) {
            issues.push('назву');
        }
        if (type === 'link' && !url) {
            issues.push('URL');
        }
        if (type === 'link' && url && duplicateUrls && duplicateUrls[url]) {
            issues.push('унікальний URL');
        }
        return issues;
    }

    function countMenuIssues(items, duplicateUrls) {
        return (items || []).reduce(function (total, item) {
            const type = item.type === 'section' ? 'section' : 'link';
            const ownIssues = menuItemIssues(item, type, duplicateUrls).length ? 1 : 0;
            const childIssues = countMenuIssues(Array.isArray(item.children) ? item.children : [], duplicateUrls);
            const columnIssues = (Array.isArray(item.columns) ? item.columns : []).reduce(function (columnTotal, column) {
                return columnTotal + countMenuIssues(Array.isArray(column.children) ? column.children : [], duplicateUrls);
            }, 0);
            return total + ownIssues + childIssues + columnIssues;
        }, 0);
    }

    function collectMenuIssuePaths(items, duplicateUrls, parentPath) {
        const paths = [];
        (items || []).forEach(function (item, index) {
            const type = item.type === 'section' ? 'section' : 'link';
            const path = parentPath ? parentPath + '.' + index : String(index);
            if (menuItemIssues(item, type, duplicateUrls).length) {
                paths.push(path);
            }
            paths.push.apply(paths, collectMenuIssuePaths(Array.isArray(item.children) ? item.children : [], duplicateUrls, path));
            (Array.isArray(item.columns) ? item.columns : []).forEach(function (column, columnIndex) {
                paths.push.apply(paths, collectMenuIssuePaths(Array.isArray(column.children) ? column.children : [], duplicateUrls, path + '.c' + columnIndex));
            });
        });
        return paths;
    }

    function expandMenuPath(path) {
        const parts = String(path || '').split('.').filter(Boolean);
        const stack = [];
        parts.forEach(function (part) {
            stack.push(part);
            if (!/^c\d+$/.test(part)) {
                expandedMenuNodes.add(stack.join('.'));
            }
        });
    }

    function focusMenuIssue(path, duplicateUrls) {
        expandMenuPath(path);
        renderHeader();
        window.requestAnimationFrame(function () {
            const row = headerEditor.querySelector('[data-header-link="' + cssEscape(path) + '"]');
            const item = getMenuItem(path);
            const type = item && item.type === 'section' ? 'section' : 'link';
            const issues = item ? menuItemIssues(item, type, duplicateUrls) : [];
            const targetField = issues.indexOf('назву') !== -1 ? 'label' : 'url';
            const input = row ? row.querySelector('[data-header-link-field="' + targetField + '"]') : null;
            if (row) {
                row.scrollIntoView({behavior: 'smooth', block: 'center'});
            }
            if (input) {
                input.focus({preventScroll: true});
                input.select?.();
            }
        });
    }

    function menuBranchHasIssues(item, duplicateUrls) {
        const type = item.type === 'section' ? 'section' : 'link';
        if (menuItemIssues(item, type, duplicateUrls).length) {
            return true;
        }
        if ((Array.isArray(item.children) ? item.children : []).some(function (child) {
            return menuBranchHasIssues(child, duplicateUrls);
        })) {
            return true;
        }
        return (Array.isArray(item.columns) ? item.columns : []).some(function (column) {
            return (Array.isArray(column.children) ? column.children : []).some(function (child) {
                return menuBranchHasIssues(child, duplicateUrls);
            });
        });
    }

    function renderMenuLinks(items, parentPath, depth, query, issuesOnly, duplicateUrls) {
        if (!items || !items.length) {
            return '';
        }
        return (items || []).map(function (link, index) {
            const path = parentPath ? parentPath + '.' + index : String(index);
            if (query && !menuBranchMatchesSearch(link, query)) {
                return '';
            }
            if (issuesOnly && !menuBranchHasIssues(link, duplicateUrls)) {
                return '';
            }
            const children = Array.isArray(link.children) ? link.children : [];
            const hasVisibleChildren = (query || issuesOnly) ? children.some(function (child) {
                return (!query || menuBranchMatchesSearch(child, query)) && (!issuesOnly || menuBranchHasIssues(child, duplicateUrls));
            }) : children.length > 0;
            const canMoveUp = index > 0;
            const canMoveDown = index < items.length - 1;
            const type = link.type === 'section' ? 'section' : 'link';
            const isSection = type === 'section';
            const isSearchMatch = Boolean(query && menuItemMatchesSearch(link, query));
            const isExpanded = expandedMenuNodes.has(path) || Boolean(query) || Boolean(issuesOnly);
            const hasIssues = menuItemIssues(link, type, duplicateUrls).length > 0;
            const title = String(link.label || '').trim() || (isSection ? 'Нова секція' : 'Нове посилання');
            return '<div class="template-menu-node template-menu-depth-' + depth + (isSearchMatch ? ' is-search-match' : '') + (hasIssues ? ' has-issues' : '') + '" data-header-link="' + path + '">' +
                '<div class="template-menu-card-head' + (isSection ? ' is-section' : '') + '">' +
                    '<button class="template-menu-toggle" type="button" data-header-toggle-link aria-expanded="' + (isExpanded ? 'true' : 'false') + '" title="' + (isExpanded ? 'Згорнути' : 'Редагувати') + '">' +
                        '<span class="mdi mdi-chevron-' + (isExpanded ? 'up' : 'down') + '"></span>' +
                    '</button>' +
                    '<div class="template-menu-summary">' +
                        '<div class="template-menu-summary-title">' +
                            (link.icon ? '<span class="' + escapeHtml(mdiIconClass(link.icon)) + '" aria-hidden="true"></span>' : '') +
                            '<strong>' + escapeHtml(title) + '</strong>' +
                            '<span class="template-menu-type-badge">' + (isSection ? 'Секція' : 'Посилання') + '</span>' +
                            (hasIssues ? '<span class="template-menu-issue-badge">Перевірити</span>' : '') +
                        '</div>' +
                        '<div class="template-menu-summary-meta">' + escapeHtml(menuItemSummaryMeta(link, type, duplicateUrls)) + '</div>' +
                    '</div>' +
                    '<div class="template-menu-actions">' +
                        '<details class="template-menu-action-menu">' +
                            '<summary title="Дії"><span class="mdi mdi-dots-vertical"></span><span>Дії</span></summary>' +
                            '<div class="template-menu-action-list">' +
                                '<button class="button secondary compact" type="button" data-header-move-link="-1" ' + (canMoveUp ? '' : 'disabled') + '><span class="mdi mdi-arrow-up"></span><span>Підняти</span></button>' +
                                '<button class="button secondary compact" type="button" data-header-move-link="1" ' + (canMoveDown ? '' : 'disabled') + '><span class="mdi mdi-arrow-down"></span><span>Опустити</span></button>' +
                                (depth < 2 ? '<button class="button secondary compact" type="button" data-header-add-child><span class="mdi mdi-subdirectory-arrow-right"></span><span>Підпункт</span></button>' : '') +
                                (depth < 2 ? '<button class="button secondary compact" type="button" data-header-add-section><span class="mdi mdi-format-list-group"></span><span>Секція</span></button>' : '') +
                                (isSection ? '<button class="button secondary compact" type="button" data-header-add-column><span class="mdi mdi-view-column-outline"></span><span>Колонка</span></button>' : '') +
                                '<button class="button secondary compact" type="button" data-header-duplicate-link><span class="mdi mdi-content-copy"></span><span>Дублювати</span></button>' +
                                '<button class="button danger compact" type="button" data-header-remove-link><span class="mdi mdi-delete-outline"></span><span>Видалити</span></button>' +
                            '</div>' +
                        '</details>' +
                    '</div>' +
                '</div>' +
                '<div class="template-menu-card-body"' + (isExpanded ? '' : ' hidden') + '>' +
                    '<div class="template-editor-row template-menu-row' + (isSection ? ' is-section' : '') + '">' +
                        '<label>Тип<select data-header-link-field="type">' +
                            '<option value="link"' + (type === 'link' ? ' selected' : '') + '>Посилання</option>' +
                            '<option value="section"' + (type === 'section' ? ' selected' : '') + '>Секція</option>' +
                        '</select></label>' +
                        '<label>Назва<input data-header-link-field="label" value="' + escapeHtml(link.label) + '"></label>' +
                        '<div class="template-menu-icon-field">' +
                            '<label>MDI іконка<input data-header-link-field="icon" value="' + escapeHtml(link.icon || '') + '" placeholder="home-outline"></label>' +
                            '<button class="button secondary compact" type="button" data-header-icon-picker title="Обрати іконку"><span class="mdi mdi-view-grid-plus-outline"></span></button>' +
                            '<button class="button secondary compact" type="button" data-header-icon-clear title="Очистити іконку"><span class="mdi mdi-close"></span></button>' +
                        '</div>' +
                        '<div class="field-with-action' + (isSection ? ' template-menu-url-muted' : '') + '">' +
                            '<label>URL<input data-header-link-field="url" value="' + escapeHtml(link.url) + '" ' + (isSection ? 'disabled placeholder="Не використовується"' : '') + '></label>' +
                            (isSection ? '' : '<button class="button secondary compact icon-button" type="button" data-header-link-url-picker title="Обрати посилання"><span class="mdi mdi-link-plus"></span></button>') +
                        '</div>' +
                    '</div>' +
                    (isSection ? renderSectionColumns(link, path, depth, query, issuesOnly, duplicateUrls) : '') +
                    (hasVisibleChildren ? '<div class="template-menu-children">' + renderMenuLinks(children, path, depth + 1, query, issuesOnly, duplicateUrls) + '</div>' : '') +
                '</div>' +
            '</div>';
        }).join('');
    }

    function countMenuItems(items) {
        return (items || []).reduce(function (total, item) {
            const columnItems = (Array.isArray(item.columns) ? item.columns : []).reduce(function (columnTotal, column) {
                return columnTotal + countMenuItems(Array.isArray(column.children) ? column.children : []);
            }, 0);
            return total + 1 + countMenuItems(Array.isArray(item.children) ? item.children : []) + columnItems;
        }, 0);
    }

    function pluralizeUk(count, one, few, many) {
        const mod10 = count % 10;
        const mod100 = count % 100;
        if (mod10 === 1 && mod100 !== 11) {
            return one;
        }
        if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
            return few;
        }
        return many;
    }

    function hasMeaningfulCta() {
        return Boolean(String(header.cta_label || '').trim() && String(header.cta_url || '').trim());
    }

    function updateWorkbenchSummary() {
        const menuCount = countMenuItems(header.links || []);
        const secondaryCount = Array.isArray(header.secondary_links) ? header.secondary_links.length : 0;
        const columnsCount = Array.isArray(footer.columns) ? footer.columns.length : 0;
        const footerItemsCount = (footer.columns || []).reduce(function (total, column) {
            return total + (Array.isArray(column.items) ? column.items.length : 0);
        }, 0);
        if (menuStat) {
            menuStat.textContent = menuCount + ' ' + pluralizeUk(menuCount, 'пункт', 'пункти', 'пунктів') + (secondaryCount ? ' + ' + secondaryCount + ' під hero' : '') + (hasMeaningfulCta() ? ' + CTA' : '');
        }
        if (footerStat) {
            footerStat.textContent = columnsCount + ' ' + pluralizeUk(columnsCount, 'колонка', 'колонки', 'колонок') + ', ' + footerItemsCount + ' ' + pluralizeUk(footerItemsCount, 'пункт', 'пункти', 'пунктів');
        }
    }

    function updateSaveStatus() {
        const changed = isDirty();
        if (saveState) {
            saveState.textContent = changed ? 'Є зміни' : 'Без змін';
        }
        if (saveTitle) {
            saveTitle.textContent = changed ? 'Є незбережені зміни' : 'Готово до збереження';
        }
        if (saveCopy) {
            saveCopy.textContent = changed ? 'Натисніть збереження, щоб застосувати їх до шаблону.' : 'Зміни застосуються до вибраного шаблону після збереження.';
        }
        if (saveBar) {
            saveBar.classList.toggle('is-dirty', changed);
        }
        if (revertButton) {
            revertButton.disabled = !changed;
        }
    }

    function markSaved() {
        syncLayouts();
        baselineLayouts = JSON.stringify(layouts);
        updateSaveStatus();
    }

    function revertTemplateChanges() {
        if (!baselineLayouts || !isDirty()) {
            return;
        }
        if (!window.confirm('Скасувати незбережені зміни в редакторі шаблону?')) {
            return;
        }
        layouts = parseJson(baselineLayouts, {});
        ensureTemplateLayout(selectedTemplate);
        header = cloneLayout(layouts[selectedTemplate].header);
        footer = cloneLayout(layouts[selectedTemplate].footer);
        menuSearchQuery = '';
        menuIssuesOnly = false;
        menuIssueCursor = -1;
        expandedMenuNodes.clear();
        renderHeader();
        renderFooter();
        renderHomePreview();
        updateWorkbenchSummary();
        updateSaveStatus();
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
            if (item.type === 'section' && Array.isArray(item.columns)) {
                item.columns.forEach(function (column, columnIndex) {
                    result.push({
                        path: path + '.c' + columnIndex,
                        label: (depth ? '— ' : '') + 'Колонка: ' + (column.title || 'Без назви')
                    });
                });
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
        const secondaryOptions = [{path: 'secondary', label: 'Меню під hero'}].concat(menuTargets(header.secondary_links || [], 'secondary', 0).map(function (item) {
            return {path: item.path, label: '— ' + item.label};
        }));
        const allOptions = options.concat(secondaryOptions);
        select.innerHTML = '<option value="">Верхній рівень</option>' + allOptions.map(function (item) {
            return '<option value="' + escapeHtml(item.path) + '">' + escapeHtml(item.label) + '</option>';
        }).join('');
        select.value = allOptions.some(function (item) { return item.path === current; }) ? current : '';
    }

    function addPickedMenuItem(item) {
        const parentSelect = headerEditor.querySelector('[data-menu-parent]');
        const parentPath = parentSelect ? parentSelect.value : '';
        const menuItem = makeMenuItem(item.cleanLabel || cleanMenuLabel(item.label), item.url);
        const target = menuContainerForPath(parentPath);
        const index = target.length;
        target.push(menuItem);
        if (parentPath) {
            expandedMenuNodes.add(parentPath);
        }
        expandedMenuNodes.add(parentPath ? parentPath + '.' + index : String(index));
    }

    function menuPickerItemKey(item) {
        return String(item.url || '') + '|' + String(item.label || '');
    }

    function clearMenuPickerSelection() {
        menuPickerState.selected.clear();
        updateMenuPickerSelection();
    }

    function updateMenuPickerSelection() {
        if (!menuPickerNode) {
            return;
        }
        const count = menuPickerState.selected.size;
        const addButton = menuPickerNode.querySelector('[data-menu-picker-add-selected]');
        const addLabel = menuPickerNode.querySelector('[data-menu-picker-add-label]');
        if (addButton) {
            addButton.disabled = count === 0;
        }
        if (addLabel) {
            addLabel.textContent = count ? 'Додати вибрані (' + count + ')' : 'Додати вибрані';
        }
        menuPickerNode.querySelectorAll('.template-menu-picker-item').forEach(function (button) {
            const selected = menuPickerState.selected.has(menuPickerItemKey(button.dataset));
            button.classList.toggle('is-selected', selected);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            const stateIcon = button.querySelector('[data-menu-picker-state-icon]');
            if (stateIcon) {
                stateIcon.className = 'template-menu-picker-state mdi ' + (selected ? 'mdi-check-circle' : 'mdi-plus-circle-outline');
            }
        });
    }

    function toggleMenuPickerItem(button) {
        const item = {
            label: button.dataset.label || '',
            cleanLabel: button.dataset.cleanLabel || button.dataset.label || '',
            url: button.dataset.url || ''
        };
        const key = menuPickerItemKey(item);
        if (menuPickerState.selected.has(key)) {
            menuPickerState.selected.delete(key);
        } else {
            menuPickerState.selected.set(key, item);
        }
        updateMenuPickerSelection();
    }

    function findTemplateLink(keywords, fallbackLabel, fallbackUrl) {
        const words = (Array.isArray(keywords) ? keywords : []).map(function (word) {
            return String(word || '').toLowerCase();
        });
        const pools = []
            .concat(Array.isArray(linkPicker.pages) ? linkPicker.pages : [])
            .concat(Array.isArray(linkPicker.categories) ? linkPicker.categories : [])
            .concat(Array.isArray(linkPicker.news) ? linkPicker.news : []);
        const found = pools.find(function (item) {
            const haystack = [item.label || '', item.url || '', item.slug || '', item.meta || ''].join(' ').toLowerCase();
            return words.some(function (word) {
                return word && haystack.indexOf(word) !== -1;
            });
        });
        return {
            label: found?.clean_label || found?.cleanLabel || cleanMenuLabel(found?.label || fallbackLabel),
            url: found?.url || fallbackUrl
        };
    }

    function menuTemplateLink(keywords, fallbackLabel, fallbackUrl, icon) {
        const link = findTemplateLink(keywords, fallbackLabel, fallbackUrl);
        return makeMenuTemplateItem(link.label, link.url, icon);
    }

    function menuTemplateMeta(type) {
        return {
            basic: {
                title: 'Базове',
                description: 'Головна, сторінки, новини та контакти для простого старту.'
            },
            education: {
                title: 'Заклад освіти',
                description: 'Типова структура для школи або освітнього закладу з розділами для учнів і батьків.'
            },
            mega: {
                title: 'Мега-меню',
                description: 'Секції з колонками для великої навігації та швидких дій.'
            }
        }[type] || {
            title: 'Готовий шаблон',
            description: 'Попередній перегляд структури перед застосуванням.'
        };
    }

    function templateCatalog(kind) {
        if (kind === 'hero') {
            return [
                {type: 'welcome', title: 'Вітання', description: 'Назва закладу, короткий вступ і кнопка', icon: 'mdi-hand-wave-outline'},
                {type: 'admission', title: 'Вступникам', description: 'Акцент на вступі, документах і дії', icon: 'mdi-account-school-outline'},
                {type: 'news', title: 'Оголошення', description: 'Компактний hero для новин і подій', icon: 'mdi-newspaper-variant-outline'}
            ];
        }
        if (kind === 'footer') {
            return [
                {type: 'basic', title: 'Базовий', description: 'Про заклад, швидкі посилання, контакти', icon: 'mdi-view-list-outline'},
                {type: 'education', title: 'Заклад освіти', description: 'Навчання, інформація для батьків, документи', icon: 'mdi-school-outline'},
                {type: 'contacts', title: 'Контакти', description: 'Адреса, телефон, пошта та корисні дії', icon: 'mdi-card-account-phone-outline'}
            ];
        }
        return [
            {type: 'basic', title: 'Базове', description: 'Головна, сторінки, новини, контакти', icon: 'mdi-view-list-outline'},
            {type: 'education', title: 'Заклад освіти', description: 'Про заклад, навчання, новини, контакти', icon: 'mdi-school-outline'},
            {type: 'mega', title: 'Мега-меню', description: 'Секції з колонками для великої навігації', icon: 'mdi-view-column-outline'}
        ];
    }

    function templateCatalogItem(kind, type) {
        return templateCatalog(kind).find(function (item) {
            return item.type === type;
        }) || templateCatalog(kind)[0];
    }

    function buildMenuTemplate(type) {
        if (type === 'education') {
            return [
                makeMenuTemplateItem('Головна', '/', 'home-outline'),
                makeMenuTemplateSection('Про заклад', 'school-outline', [
                    menuTemplateLink(['про', 'about'], 'Про нас', '/page/about', 'information-outline'),
                    menuTemplateLink(['адміністрація', 'керівництво'], 'Адміністрація', '/page/administration', 'account-tie-outline'),
                    menuTemplateLink(['документ', 'document'], 'Документи', '/page/documents', 'file-document-outline')
                ]),
                makeMenuTemplateSection('Навчання', 'book-open-page-variant-outline', [], [
                    {
                        title: 'Учням',
                        children: [
                            menuTemplateLink(['розклад'], 'Розклад занять', '/page/schedule', 'calendar-clock'),
                            menuTemplateLink(['гурт'], 'Гуртки', '/page/clubs', 'star-outline')
                        ]
                    },
                    {
                        title: 'Батькам',
                        children: [
                            menuTemplateLink(['батьк'], 'Батькам', '/page/parents', 'account-child-outline'),
                            menuTemplateLink(['харч'], 'Харчування', '/page/food', 'silverware-fork-knife')
                        ]
                    }
                ]),
                menuTemplateLink(['новин', 'news'], 'Новини', '/news', 'newspaper-variant-outline'),
                menuTemplateLink(['контакт'], 'Контакти', '/contacts', 'phone-outline')
            ];
        }

        if (type === 'mega') {
            return [
                makeMenuTemplateItem('Головна', '/', 'home-outline'),
                makeMenuTemplateSection('Навігація', 'view-column-outline', [], [
                    {
                        title: 'Основне',
                        children: [
                            menuTemplateLink(['про', 'about'], 'Про нас', '/page/about', 'information-outline'),
                            menuTemplateLink(['послуг'], 'Послуги', '/page/services', 'briefcase-outline'),
                            menuTemplateLink(['контакт'], 'Контакти', '/contacts', 'phone-outline')
                        ]
                    },
                    {
                        title: 'Матеріали',
                        children: [
                            menuTemplateLink(['новин', 'news'], 'Новини', '/news', 'newspaper-variant-outline'),
                            menuTemplateLink(['категор'], 'Категорії', '/news/categories', 'shape-outline'),
                            menuTemplateLink(['медіа', 'media'], 'Медіафайли', '/media', 'folder-image')
                        ]
                    },
                    {
                        title: 'Швидкі дії',
                        children: [
                            menuTemplateLink(['заява', 'вступ'], 'Подати заявку', '/page/apply', 'send-outline'),
                            menuTemplateLink(['кабінет', 'login'], 'Кабінет', '/login', 'login')
                        ]
                    }
                ])
            ];
        }

        return [
            makeMenuTemplateItem('Головна', '/', 'home-outline'),
            menuTemplateLink(['про', 'about'], 'Про нас', '/page/about', 'information-outline'),
            menuTemplateLink(['новин', 'news'], 'Новини', '/news', 'newspaper-variant-outline'),
            menuTemplateLink(['контакт'], 'Контакти', '/contacts', 'phone-outline')
        ];
    }

    function findHeroLink(keywords, fallbackUrl) {
        return findTemplateLink(keywords, '', fallbackUrl).url || fallbackUrl;
    }

    function buildHeroTemplate(type) {
        const institutionName = previewContext.institutionName || 'Заклад освіти';
        if (type === 'admission') {
            return {
                hero_enabled: true,
                hero_variant: 'fullscreen',
                hero_title: 'Вступникам',
                hero_text: institutionName + ' запрошує до навчання. Перегляньте умови вступу, необхідні документи та важливі дати.',
                hero_button_label: 'Детальніше про вступ',
                hero_button_url: findHeroLink(['вступ', 'заява', 'admission', 'apply'], '/page/admission')
            };
        }
        if (type === 'news') {
            return {
                hero_enabled: true,
                hero_variant: 'compact',
                hero_title: 'Новини та оголошення',
                hero_text: 'Актуальні події, важливі повідомлення та корисна інформація для спільноти закладу.',
                hero_button_label: 'Перейти до новин',
                hero_button_url: findHeroLink(['новин', 'news'], '/news')
            };
        }
        return {
            hero_enabled: true,
            hero_variant: 'fullscreen',
            hero_title: institutionName,
            hero_text: 'Сучасний освітній простір для навчання, розвитку та спільних досягнень.',
            hero_button_label: 'Дізнатися більше',
            hero_button_url: findHeroLink(['про', 'about'], '/page/about')
        };
    }

    function applyHeroTemplate(type, target, preparedTemplate) {
        const template = buildHeroTemplate(type);
        const payload = preparedTemplate || template;
        const normalizedTarget = target === 'home' ? 'home' : 'header';
        Object.keys(payload).forEach(function (key) {
            const targetKey = normalizedTarget === 'home' ? key.replace(/^hero_/, 'home_hero_') : key;
            header[targetKey] = payload[key];
        });
        renderHeader();
        setTemplateEditorTab(normalizedTarget === 'home' ? 'home-hero' : 'hero');
    }

    function expandTemplateMenuItems(items, parentPath) {
        collectMenuPaths(items || [], parentPath || '').forEach(function (path) {
            const item = getMenuItem(path);
            if (item && (item.type === 'section' || (Array.isArray(item.children) && item.children.length) || (Array.isArray(item.columns) && item.columns.length))) {
                expandedMenuNodes.add(path);
            }
        });
    }

    function renderHeroTemplatePreview(template) {
        return '<div class="template-blueprint-hero-preview site-header-hero site-header-hero-' + escapeHtml(template.hero_variant || 'default') + '">' +
            '<div class="site-header-hero-inner">' +
                '<div>' +
                    (template.hero_title ? '<h1>' + escapeHtml(template.hero_title) + '</h1>' : '') +
                    (template.hero_text ? '<p>' + escapeHtml(template.hero_text) + '</p>' : '') +
                '</div>' +
                (template.hero_button_label ? '<span class="button">' + escapeHtml(template.hero_button_label) + '</span>' : '') +
            '</div>' +
        '</div>';
    }

    function renderFooterTemplatePreview(template) {
        const previousFooter = footer;
        footer = cloneLayout(template);
        const html = renderPreviewFooter();
        footer = previousFooter;
        return html;
    }

    function selectTemplateLibraryItem(kind, type, target) {
        const normalizedKind = ['menu', 'hero', 'footer'].indexOf(kind) !== -1 ? kind : 'menu';
        const normalizedTarget = target === 'secondary' ? 'secondary' : (target === 'home' ? 'home' : 'main');
        const meta = templateCatalogItem(normalizedKind, type);
        let payload = null;
        let previewHtml = '';
        let countText = '';
        if (normalizedKind === 'hero') {
            payload = buildHeroTemplate(meta.type);
            previewHtml = renderHeroTemplatePreview(payload);
            countText = payload.hero_variant === 'fullscreen' ? 'На весь екран' : 'Hero блок';
        } else if (normalizedKind === 'footer') {
            payload = buildFooterTemplate(meta.type);
            previewHtml = renderFooterTemplatePreview(payload);
            const columnCount = Array.isArray(payload.columns) ? payload.columns.length : 0;
            countText = columnCount + ' ' + pluralizeUk(columnCount, 'колонка', 'колонки', 'колонок');
        } else {
            payload = buildMenuTemplate(meta.type);
            previewHtml = renderMenuVisualTree(payload, 'Шаблон порожній.');
            const itemsCount = countMenuItems(payload);
            countText = itemsCount + ' ' + pluralizeUk(itemsCount, 'пункт', 'пункти', 'пунктів');
        }
        menuTemplateState.kind = normalizedKind;
        menuTemplateState.type = meta.type;
        menuTemplateState.target = normalizedTarget;
        menuTemplateState.payload = cloneLayout(payload);
        const title = menuTemplateNode.querySelector('[data-menu-template-title]');
        const description = menuTemplateNode.querySelector('[data-menu-template-description]');
        const count = menuTemplateNode.querySelector('[data-menu-template-count]');
        const preview = menuTemplateNode.querySelector('[data-menu-template-preview]');
        const applyButton = menuTemplateNode.querySelector('[data-menu-template-apply] span:last-child');
        if (title) {
            title.textContent = meta.title;
        }
        if (description) {
            description.textContent = meta.description;
        }
        if (count) {
            count.textContent = countText;
        }
        if (preview) {
            preview.innerHTML = previewHtml;
        }
        if (applyButton) {
            applyButton.textContent = normalizedKind === 'hero' ? (normalizedTarget === 'home' ? 'Застосувати до головної' : 'Застосувати hero') : (normalizedKind === 'footer' ? 'Застосувати футер' : (normalizedTarget === 'secondary' ? 'Застосувати під hero' : 'Застосувати до меню'));
        }
        menuTemplateNode.querySelectorAll('[data-template-blueprint-select]').forEach(function (button) {
            button.classList.toggle('is-selected', button.dataset.templateBlueprintSelect === meta.type);
            button.setAttribute('aria-pressed', button.dataset.templateBlueprintSelect === meta.type ? 'true' : 'false');
        });
    }

    function openTemplateLibrary(kind, target) {
        const normalizedKind = ['menu', 'hero', 'footer'].indexOf(kind) !== -1 ? kind : 'menu';
        const normalizedTarget = target === 'secondary' ? 'secondary' : (target === 'home' ? 'home' : 'main');
        if (!menuTemplateNode || !menuTemplateModal) {
            if (normalizedKind === 'hero') {
                applyHeroTemplate('welcome', normalizedTarget);
            } else if (normalizedKind === 'footer') {
                applyFooterTemplate('basic');
            } else {
                applyMenuTemplate('basic', normalizedTarget, buildMenuTemplate('basic'));
            }
            return;
        }
        const targetLabel = menuTemplateNode.querySelector('[data-menu-template-target-label]');
        const previewLabel = menuTemplateNode.querySelector('[data-template-preview-label]');
        const list = menuTemplateNode.querySelector('[data-template-blueprint-list]');
        const labels = {
            menu: normalizedTarget === 'secondary' ? 'Шаблони меню під hero' : 'Шаблони основного меню',
            hero: normalizedTarget === 'home' ? 'Шаблони hero головної' : 'Шаблони hero',
            footer: 'Шаблони футера'
        };
        if (targetLabel) {
            targetLabel.textContent = labels[normalizedKind];
        }
        if (previewLabel) {
            previewLabel.textContent = normalizedKind === 'hero' ? 'Preview hero' : (normalizedKind === 'footer' ? 'Preview футера' : 'Структура шаблону');
        }
        if (list) {
            list.innerHTML = templateCatalog(normalizedKind).map(function (item) {
                return '<button class="template-blueprint-choice" type="button" data-template-blueprint-kind="' + escapeHtml(normalizedKind) + '" data-template-blueprint-target="' + escapeHtml(normalizedTarget) + '" data-template-blueprint-select="' + escapeHtml(item.type) + '" aria-pressed="false">' +
                    '<span class="mdi ' + escapeHtml(item.icon) + '" aria-hidden="true"></span>' +
                    '<strong>' + escapeHtml(item.title) + '</strong>' +
                    '<small>' + escapeHtml(item.description) + '</small>' +
                '</button>';
            }).join('');
        }
        selectTemplateLibraryItem(normalizedKind, templateCatalog(normalizedKind)[0].type, normalizedTarget);
        menuTemplateModal.show();
    }

    function openMenuTemplatePreview(type, target) {
        openTemplateLibrary('menu', target);
        selectTemplateLibraryItem('menu', type, target);
    }

    function applyMenuTemplate(type, target, preparedItems) {
        const normalizedTarget = target === 'secondary' ? 'secondary' : 'main';
        const items = cloneLayout(preparedItems || buildMenuTemplate(type));
        if (normalizedTarget === 'secondary') {
            header.secondary_links = items;
            header.secondary_enabled = true;
            expandedMenuNodes.clear();
            expandTemplateMenuItems(header.secondary_links || [], 'secondary');
            renderHeader();
            setTemplateEditorTab('secondary');
            return;
        }
        header.links = items;
        menuSearchQuery = '';
        menuIssuesOnly = false;
        menuIssueCursor = -1;
        expandedMenuNodes.clear();
        expandTemplateMenuItems(header.links || [], '');
        renderHeader();
        setTemplateEditorTab('menu');
    }

    function addSelectedMenuPickerItems() {
        if (!menuPickerState.selected.size) {
            return;
        }
        menuPickerState.selected.forEach(function (item) {
            addPickedMenuItem(item);
        });
        clearMenuPickerSelection();
        renderHeader();
        if (menuPickerModal) {
            menuPickerModal.hide();
        }
    }

    function addMenuPreset(type) {
        if (type === 'clear') {
            if ((header.links || []).length && !window.confirm('Очистити всі власні пункти меню цього шаблону?')) {
                return;
            }
            header.links = [];
            expandedMenuNodes.clear();
            renderHeader();
            return;
        }

        if (type === 'core') {
            const existing = new Set((header.links || []).map(function (item) {
                return String(item.url || '');
            }));
            header.links = Array.isArray(header.links) ? header.links : [];
            [
                makeMenuItem('Головна', '/'),
                makeMenuItem('Новини', '/news')
            ].forEach(function (item) {
                if (!existing.has(item.url)) {
                    expandedMenuNodes.add(String(header.links.length));
                    header.links.push(item);
                    existing.add(item.url);
                }
            });
            renderHeader();
            return;
        }

        if (type === 'pages') {
            const pages = Array.isArray(linkPicker.pages) ? linkPicker.pages.slice(0, 8) : [];
            if (!pages.length) {
                return;
            }
            const existing = new Set((header.links || []).map(function (item) {
                return String(item.url || '');
            }));
            header.links = Array.isArray(header.links) ? header.links : [];
            pages.forEach(function (page) {
                if (page.url && !existing.has(page.url)) {
                    expandedMenuNodes.add(String(header.links.length));
                    header.links.push(makeMenuItem(page.label || page.url, page.url));
                    existing.add(page.url);
                }
            });
            renderHeader();
        }
    }

    function setPreviewMode(mode) {
        if (!previewShell) {
            return;
        }
        previewShell.dataset.previewMode = mode;
        previewModeButtons.forEach(function (button) {
            button.classList.toggle('secondary', button.dataset.templatePreviewMode !== mode);
        });
    }

    function setPreviewCollapsed(collapsed, persist) {
        if (!previewShell || !previewCollapseButton || !previewPanel) {
            return;
        }
        previewShell.hidden = collapsed;
        previewPanel.classList.toggle('is-collapsed', collapsed);
        if (builderLayout) {
            builderLayout.classList.toggle('is-preview-collapsed', collapsed);
        }
        previewCollapseButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        previewCollapseButton.innerHTML = collapsed
            ? '<span class="mdi mdi-chevron-down" aria-hidden="true"></span><span>Розгорнути</span>'
            : '<span class="mdi mdi-chevron-up" aria-hidden="true"></span><span>Згорнути</span>';
        if (persist) {
            try {
                window.localStorage.setItem('adminTemplatesPreviewCollapsed', collapsed ? '1' : '0');
            } catch (error) {
                // Ignore storage restrictions.
            }
        }
    }

    function setTemplatePickerCollapsed(collapsed) {
        if (!pickerToggle || !pickerOptions) {
            return;
        }
        pickerOptions.hidden = collapsed;
        pickerToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        pickerToggle.innerHTML = collapsed
            ? '<span class="mdi mdi-chevron-down" aria-hidden="true"></span><span>Розгорнути</span>'
            : '<span class="mdi mdi-chevron-up" aria-hidden="true"></span><span>Згорнути</span>';
    }

    function menuParentLabel() {
        const parentSelect = headerEditor.querySelector('[data-menu-parent]');
        if (!parentSelect || parentSelect.value === '') {
            return 'верхній рівень меню';
        }
        return 'пункт “' + parentSelect.options[parentSelect.selectedIndex].textContent.trim().replace(/^—\s*/, '') + '”';
    }

    function openMenuPicker() {
        if (window.AdminLinkPicker) {
            window.AdminLinkPicker.open({
                multiple: true,
                title: 'Обрати посилання',
                hint: 'Пункт буде додано у ' + menuParentLabel() + '.',
                onSelect: function (items) {
                    (Array.isArray(items) ? items : [items]).forEach(addPickedMenuItem);
                    renderHeader();
                }
            });
            return;
        }
        if (!menuPickerModal) {
            return;
        }
        const target = menuPickerNode.querySelector('[data-menu-picker-target]');
        if (target) {
            target.textContent = 'Пункт буде додано у ' + menuParentLabel() + '.';
        }
        menuPickerModal.show();
        clearMenuPickerSelection();
        resetMenuPicker();
        const search = menuPickerNode.querySelector('[data-menu-picker-search]');
        if (search) {
            setTimeout(function () {
                search.focus();
            }, 180);
        }
    }

    function openHeaderUrlPicker(field) {
        if (!window.AdminLinkPicker || !field) {
            return;
        }
        window.AdminLinkPicker.open({
            title: 'Обрати посилання',
            hint: 'Вибране посилання буде вставлено в URL-поле.',
            onSelect: function (item) {
                field.value = item.url || '';
                field.dispatchEvent(new Event('input', {bubbles: true}));
            }
        });
    }

    function resetMenuPicker() {
        menuPickerState.offset = 0;
        menuPickerState.total = 0;
        menuPickerState.hasMore = false;
        loadMenuPickerItems(false);
    }

    function setMenuPickerType(type) {
        menuPickerState.type = type;
        clearMenuPickerSelection();
        menuPickerNode.querySelectorAll('[data-menu-picker-type]').forEach(function (button) {
            button.classList.toggle('secondary', button.dataset.menuPickerType !== type);
        });
        const statusFilter = menuPickerNode.querySelector('[data-menu-picker-filter="status"]');
        const scopeFilter = menuPickerNode.querySelector('[data-menu-picker-filter="scope"]');
        const categoryFilter = menuPickerNode.querySelector('[data-menu-picker-filter="category"]');
        if (statusFilter) {
            statusFilter.hidden = type === 'categories' || type === 'media';
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
        } else if ((menuPickerState.type === 'pages' || menuPickerState.type === 'news') && status) {
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
            const selected = menuPickerState.selected.has(menuPickerItemKey(item));
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'template-menu-picker-item' + (selected ? ' is-selected' : '');
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            button.dataset.label = item.label || '';
            button.dataset.cleanLabel = item.clean_label || item.cleanLabel || cleanMenuLabel(item.label || '');
            button.dataset.url = item.url || '';
            const displayLabel = item.display_label || item.displayLabel || item.label || item.url || 'Посилання';
            button.innerHTML =
                '<span class="template-menu-picker-item-icon mdi ' + menuPickerIcon(menuPickerState.type) + '" aria-hidden="true"></span>' +
                '<span class="template-menu-picker-item-body">' +
                    '<strong>' + escapeHtml(displayLabel) + '</strong>' +
                    '<small>' + escapeHtml(item.meta || item.url || '') + '</small>' +
                    '<code class="w-100">' + escapeHtml(item.url || '#') + '</code>' +
                '</span>' +
                '<span class="template-menu-picker-state mdi ' + (selected ? 'mdi-check-circle' : 'mdi-plus-circle-outline') + '" data-menu-picker-state-icon aria-hidden="true"></span>';
            list.appendChild(button);
        });
        updateMenuPickerSelection();
    }

    function menuPickerIcon(type) {
        return {
            pages: 'mdi-file-document-outline',
            categories: 'mdi-shape-outline',
            news: 'mdi-newspaper-variant-outline',
            media: 'mdi-file-image-outline'
        }[type] || 'mdi-link-variant';
    }

    function mobileMenuLinks() {
        const source = header.mobile_source || 'main';
        const mainLinks = Array.isArray(header.links) ? header.links : [];
        const secondaryLinks = Array.isArray(header.secondary_links) ? header.secondary_links : [];
        if (source === 'secondary') {
            return secondaryLinks;
        }
        if (source === 'both') {
            return mainLinks.concat(secondaryLinks);
        }
        return mainLinks;
    }

    function syncLayouts() {
        ensureTemplateLayout(selectedTemplate);
        header.show_home = false;
        header.show_news = false;
        layouts[selectedTemplate].header = cloneLayout(header);
        layouts[selectedTemplate].footer = cloneLayout(footer);
        layoutsInput.value = JSON.stringify(layouts);
        headerInput.value = JSON.stringify(header);
        footerInput.value = JSON.stringify(footer);
        updateWorkbenchSummary();
        updateSaveStatus();
        renderHomePreview();
    }

    function renderPreviewHeader() {
        const links = [];
        function renderPreviewLinks(items) {
            return (items || []).map(function (link) {
                const children = Array.isArray(link.children) ? link.children : [];
                const columns = Array.isArray(link.columns) ? link.columns : [];
                const type = link.type === 'section' ? 'section' : 'link';
                if (!link.label && !children.length && !columns.length) {
                    return '';
                }
                const columnsHtml = columns.length ? '<span class="site-menu-columns">' + columns.map(function (column) {
                    const columnChildren = Array.isArray(column.children) ? column.children : [];
                    if (!column.title && !columnChildren.length) {
                        return '';
                    }
                    return '<span class="site-menu-column">' +
                        (column.title ? '<span class="site-menu-column-title">' + escapeHtml(column.title) + '</span>' : '') +
                        renderPreviewLinks(columnChildren) +
                    '</span>';
                }).join('') + '</span>' : '';
                return '<span class="site-menu-item">' +
                    (type === 'section'
                        ? '<span class="site-menu-section"' + ((children.length || columns.length) ? ' tabindex="0"' : '') + '>' + renderMenuLabel(link.label, link.icon, 'Секція') + '</span>'
                        : '<a href="' + escapeHtml(previewUrl(link.url)) + '">' + renderMenuLabel(link.label, link.icon, 'Пункт') + '</a>') +
                    ((children.length || columns.length) ? '<span class="site-submenu">' + columnsHtml + renderPreviewLinks(children) + '</span>' : '') +
                '</span>';
            }).join('');
        }
        (header.links || []).forEach(function (link) {
            if (link.label || (Array.isArray(link.children) && link.children.length) || (Array.isArray(link.columns) && link.columns.length)) {
                links.push(renderPreviewLinks([link]));
            }
        });
        const mobileLinks = [];
        mobileMenuLinks().forEach(function (link) {
            if (link.label || (Array.isArray(link.children) && link.children.length) || (Array.isArray(link.columns) && link.columns.length)) {
                mobileLinks.push(renderPreviewLinks([link]));
            }
        });

        const mobileClass = ' site-mobile-menu-' + escapeHtml(header.mobile_variant || 'drawer');
        const hasHomeHero = Boolean(header.home_hero_enabled && (header.home_hero_title || header.home_hero_text));
        const previewingHeaderHero = activeEditorTab === 'hero';
        const previewingHomeHero = activeEditorTab === 'home-hero';
        const headerHeroBg = heroBackgroundMeta({
            hero_background_image: header.hero_background_image || '',
            hero_background_position: header.hero_background_position || 'center center',
            hero_background_size: header.hero_background_size || 'cover',
            hero_background_repeat: header.hero_background_repeat || 'no-repeat',
            hero_overlay_enabled: header.hero_overlay_enabled !== false,
            hero_overlay_opacity: header.hero_overlay_opacity || '35'
        });
        const showHeaderHero = Boolean(header.hero_enabled && (previewingHeaderHero || (!hasHomeHero && !previewingHomeHero)));
        const heroHtml = showHeaderHero ? '<section class="site-header-hero site-header-hero-' + escapeHtml(header.hero_variant || 'default') + headerHeroBg.className + '"' + (headerHeroBg.style ? ' style="' + headerHeroBg.style + '"' : '') + '>' +
            '<div class="container site-header-hero-inner">' +
                '<div>' +
                    (header.hero_title ? '<h1>' + escapeHtml(header.hero_title) + '</h1>' : '') +
                    (header.hero_text ? '<p>' + escapeHtml(header.hero_text) + '</p>' : '') +
                '</div>' +
                (header.hero_button_label && header.hero_button_url ? '<a class="button" href="' + escapeHtml(previewUrl(header.hero_button_url)) + '">' + escapeHtml(header.hero_button_label) + '</a>' : '') +
            '</div>' +
        '</section>' : '';
        const secondaryHtml = header.secondary_enabled && Array.isArray(header.secondary_links) && header.secondary_links.length
            ? '<nav class="site-secondary-menu site-secondary-menu-' + escapeHtml(header.secondary_variant || 'pills') + '" aria-label="Додаткове меню"><div class="container site-secondary-menu-inner">' + renderSecondaryPreviewLinks(header.secondary_links) + '</div></nav>'
            : '';

        return '<header class="topbar site-header site-header-' + escapeHtml(header.variant || 'default') + mobileClass + '" data-site-header>' +
            '<div class="container topbar-inner site-header-inner">' +
                (header.show_brand !== false ? '<a class="brand" href="#">' + escapeHtml(previewContext.institutionName || 'Заклад освіти') + '</a>' : '') +
                '<button class="site-menu-toggle" type="button" data-site-menu-toggle aria-expanded="false" aria-controls="templatePreviewMenuPanel">' +
                    '<span class="site-menu-toggle-bars" aria-hidden="true"></span><span>' + escapeHtml(header.mobile_label || 'Меню') + '</span>' +
                '</button>' +
                '<div class="site-header-menu-panel" id="templatePreviewMenuPanel" data-site-menu-panel>' +
                    (header.mobile_show_brand === false ? '' : '<span class="site-mobile-menu-brand">' + escapeHtml(previewContext.institutionName || 'Заклад освіти') + '</span>') +
                    '<nav class="nav site-header-nav" aria-label="Головне меню">' + links.join('') + '</nav>' +
                    '<nav class="nav site-mobile-source-nav" aria-label="Мобільне меню">' + mobileLinks.join('') + '</nav>' +
                    (header.cta_label && header.cta_url ? '<a class="button site-header-cta' + (header.mobile_show_cta === false ? ' site-header-cta-mobile-hidden' : '') + '" href="' + escapeHtml(previewUrl(header.cta_url)) + '">' + escapeHtml(header.cta_label) + '</a>' : '') +
                '</div>' +
            '</div>' +
        '</header>' + heroHtml + secondaryHtml;
    }

    function homeHeroTemplate() {
        return {
            hero_enabled: Boolean(header.home_hero_enabled),
            hero_variant: header.home_hero_variant || 'fullscreen',
            hero_title: header.home_hero_title || '',
            hero_text: header.home_hero_text || '',
            hero_button_label: header.home_hero_button_label || '',
            hero_button_url: header.home_hero_button_url || '',
            hero_background_image: header.home_hero_background_image || '',
            hero_background_position: header.home_hero_background_position || 'center center',
            hero_background_size: header.home_hero_background_size || 'cover',
            hero_background_repeat: header.home_hero_background_repeat || 'no-repeat',
            hero_overlay_enabled: header.home_hero_overlay_enabled !== false,
            hero_overlay_opacity: header.home_hero_overlay_opacity || '35'
        };
    }

    function renderPreviewHomeHero() {
        if (activeEditorTab === 'hero') {
            return '';
        }
        const template = homeHeroTemplate();
        if (!template.hero_enabled || (!template.hero_title && !template.hero_text)) {
            return '';
        }
        const homeHeroBg = heroBackgroundMeta(template);
        return '<section class="site-home-hero site-header-hero site-header-hero-' + escapeHtml(template.hero_variant || 'fullscreen') + homeHeroBg.className + '"' + (homeHeroBg.style ? ' style="' + homeHeroBg.style + '"' : '') + '>' +
            '<div class="container site-header-hero-inner">' +
                '<div>' +
                    (template.hero_title ? '<h1>' + escapeHtml(template.hero_title) + '</h1>' : '') +
                    (template.hero_text ? '<p>' + escapeHtml(template.hero_text) + '</p>' : '') +
                '</div>' +
                (template.hero_button_label && template.hero_button_url ? '<a class="button" href="' + escapeHtml(previewUrl(template.hero_button_url)) + '">' + escapeHtml(template.hero_button_label) + '</a>' : '') +
            '</div>' +
        '</section>';
    }

    function renderMenuVisualTree(links, emptyText) {
        function renderVisualItems(items, depth) {
            const html = (items || []).map(function (item) {
                const type = item.type === 'section' ? 'section' : 'link';
                const children = Array.isArray(item.children) ? item.children : [];
                const columns = Array.isArray(item.columns) ? item.columns : [];
                if (!item.label && !children.length && !columns.length) {
                    return '';
                }
                const columnsHtml = columns.length ? '<div class="template-menu-visual-columns">' + columns.map(function (column) {
                    const columnChildren = Array.isArray(column.children) ? column.children : [];
                    if (!column.title && !columnChildren.length) {
                        return '';
                    }
                    return '<div class="template-menu-visual-column">' +
                        (column.title ? '<strong>' + escapeHtml(column.title) + '</strong>' : '') +
                        renderVisualItems(columnChildren, depth + 1) +
                    '</div>';
                }).join('') + '</div>' : '';
                return '<div class="template-menu-visual-item template-menu-visual-depth-' + depth + '">' +
                    '<div class="template-menu-visual-link">' +
                        renderMenuLabel(item.label, item.icon, type === 'section' ? 'Секція' : 'Пункт') +
                        (type === 'link' ? '<code>' + escapeHtml(item.url || '#') + '</code>' : '<small>секція</small>') +
                    '</div>' +
                    (columnsHtml || children.length ? '<div class="template-menu-visual-nested">' + columnsHtml + renderVisualItems(children, depth + 1) + '</div>' : '') +
                '</div>';
            }).join('');
            return html;
        }
        if (!links.length) {
            return '<div class="template-menu-visual-empty"><span class="mdi mdi-menu-open" aria-hidden="true"></span><span>' + escapeHtml(emptyText || 'Меню ще порожнє.') + '</span></div>';
        }
        return '<div class="template-menu-visual-tree">' + renderVisualItems(links, 0) + '</div>';
    }

    function renderSecondaryLinks() {
        const links = Array.isArray(header.secondary_links) ? header.secondary_links : [];
        const list = headerEditor.querySelector('[data-secondary-links]');
        if (!list) {
            return;
        }
        const duplicateUrls = duplicatedMenuUrls(links);
        list.innerHTML = links.length
            ? renderMenuLinks(links, 'secondary', 0, '', false, duplicateUrls)
            : '<div class="template-empty-state"><span class="mdi mdi-link-variant" aria-hidden="true"></span><strong>Меню під hero порожнє</strong><p>Додайте пункт, секцію або колонки так само, як в основному меню.</p></div>';
    }

    function renderSecondaryPreviewLinks(items) {
        return (items || []).map(function (item) {
            const children = Array.isArray(item.children) ? item.children : [];
            const columns = Array.isArray(item.columns) ? item.columns : [];
            const type = item.type === 'section' ? 'section' : 'link';
            if (!item.label && !item.url && !children.length && !columns.length) {
                return '';
            }
            const columnsHtml = columns.length ? '<span class="site-menu-columns">' + columns.map(function (column) {
                const columnChildren = Array.isArray(column.children) ? column.children : [];
                if (!column.title && !columnChildren.length) {
                    return '';
                }
                return '<span class="site-menu-column">' +
                    (column.title ? '<span class="site-menu-column-title">' + escapeHtml(column.title) + '</span>' : '') +
                    renderSecondaryPreviewLinks(columnChildren) +
                '</span>';
            }).join('') + '</span>' : '';
            return '<span class="site-menu-item">' +
                (type === 'section'
                    ? '<span class="site-menu-section"' + ((children.length || columns.length) ? ' tabindex="0"' : '') + '>' + renderMenuLabel(item.label, item.icon, 'Секція') + '</span>'
                    : '<a href="' + escapeHtml(previewUrl(item.url || '#')) + '">' + renderMenuLabel(item.label, item.icon, 'Пункт') + '</a>') +
                ((children.length || columns.length) ? '<span class="site-submenu">' + columnsHtml + renderSecondaryPreviewLinks(children) + '</span>' : '') +
            '</span>';
        }).join('');
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

    function globalFieldValue(keywords) {
        const words = (Array.isArray(keywords) ? keywords : []).map(function (word) {
            return String(word || '').toLowerCase();
        });
        const fields = Array.isArray(previewContext.globalFields) ? previewContext.globalFields : [];
        const field = fields.find(function (item) {
            const haystack = [item.label || '', item.key || '', item.value || ''].join(' ').toLowerCase();
            return words.some(function (word) {
                return word && haystack.indexOf(word) !== -1;
            });
        });
        return field && field.value ? String(field.value) : '';
    }

    function makeFooterColumn(title, items) {
        return {
            title: title || '',
            items: Array.isArray(items) ? items.map(function (item) {
                return {
                    label: item.label || '',
                    text: item.text || '',
                    url: item.url || ''
                };
            }) : []
        };
    }

    function buildFooterTemplate(type) {
        const institutionName = previewContext.institutionName || 'Заклад освіти';
        const phone = globalFieldValue(['тел', 'phone']);
        const email = globalFieldValue(['email', 'пошта', 'mail']);
        const address = globalFieldValue(['адрес', 'address']);
        if (type === 'education') {
            return {
                variant: 'default',
                bottom_text: '© ' + institutionName,
                columns: [
                    makeFooterColumn('Про заклад', [
                        {label: institutionName, text: 'Освітній простір для навчання, розвитку та підтримки спільноти.', url: ''},
                        {label: 'Про нас', text: '', url: '/page/about'}
                    ]),
                    makeFooterColumn('Навчання', [
                        {label: 'Розклад занять', text: '', url: '/page/schedule'},
                        {label: 'Гуртки', text: '', url: '/page/clubs'},
                        {label: 'Новини', text: '', url: '/news'}
                    ]),
                    makeFooterColumn('Батькам', [
                        {label: 'Інформація для батьків', text: '', url: '/page/parents'},
                        {label: 'Документи', text: '', url: '/page/documents'},
                        {label: 'Харчування', text: '', url: '/page/food'}
                    ]),
                    makeFooterColumn('Контакти', [
                        {label: phone ? 'Телефон' : '', text: phone, url: phone ? 'tel:' + phone.replace(/[^+\d]/g, '') : ''},
                        {label: email ? 'Email' : '', text: email, url: email ? 'mailto:' + email : ''},
                        {label: address ? 'Адреса' : '', text: address, url: ''}
                    ])
                ]
            };
        }
        if (type === 'contacts') {
            return {
                variant: 'light',
                bottom_text: '© ' + institutionName,
                columns: [
                    makeFooterColumn('Контакти', [
                        {label: phone ? 'Телефон' : 'Телефон', text: phone || 'Вкажіть номер телефону', url: phone ? 'tel:' + phone.replace(/[^+\d]/g, '') : ''},
                        {label: email ? 'Email' : 'Email', text: email || 'Вкажіть електронну пошту', url: email ? 'mailto:' + email : ''},
                        {label: address ? 'Адреса' : 'Адреса', text: address || 'Вкажіть адресу закладу', url: ''}
                    ]),
                    makeFooterColumn('Швидкі дії', [
                        {label: 'Написати нам', text: '', url: email ? 'mailto:' + email : '/contacts'},
                        {label: 'Як нас знайти', text: '', url: '/contacts'},
                        {label: 'Новини', text: '', url: '/news'}
                    ])
                ]
            };
        }
        return {
            variant: 'default',
            bottom_text: '© ' + institutionName,
            columns: [
                makeFooterColumn('Про заклад', [
                    {label: institutionName, text: 'Коротка інформація про заклад та його освітню спільноту.', url: ''},
                    {label: 'Детальніше', text: '', url: '/page/about'}
                ]),
                makeFooterColumn('Швидкі посилання', [
                    {label: 'Головна', text: '', url: '/'},
                    {label: 'Новини', text: '', url: '/news'},
                    {label: 'Контакти', text: '', url: '/contacts'}
                ]),
                makeFooterColumn('Контакти', [
                    {label: phone ? 'Телефон' : '', text: phone, url: phone ? 'tel:' + phone.replace(/[^+\d]/g, '') : ''},
                    {label: email ? 'Email' : '', text: email, url: email ? 'mailto:' + email : ''},
                    {label: address ? 'Адреса' : '', text: address, url: ''}
                ])
            ]
        };
    }

    function applyFooterTemplate(type) {
        const template = buildFooterTemplate(type);
        footer.variant = template.variant || 'default';
        footer.bottom_text = template.bottom_text || '';
        footer.columns = Array.isArray(template.columns) ? template.columns : [];
        renderFooter();
        setTemplateEditorTab('footer');
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
            '<link rel="stylesheet" href="' + escapeHtml(previewContext.mdiCss || '') + '">' +
            '<link rel="stylesheet" href="' + escapeHtml(previewContext.siteCss || '') + '">' +
            (template.css ? '<link rel="stylesheet" href="' + escapeHtml(template.css) + '">' : '') +
            '</head><body class="site-template-' + escapeHtml(selectedTemplate) + '">' +
            '<div class="template-preview-note">Перегляд: ' + escapeHtml(template.name || selectedTemplate) + ' <span>незбережені зміни</span></div>' +
            renderPreviewHeader() +
            '<main>' + (renderPreviewHomeHero() || '<section class="hero"><div class="container hero-inner"><h1>' + escapeHtml(title) + '</h1><p>' + escapeHtml(excerpt) + '</p></div></section>') +
            '<section class="section"><div class="container"><div class="grid grid-3"><article class="card"><p class="meta">Блок</p><h3>Про заклад</h3><p>Тут буде контент головної сторінки.</p></article><article class="card"><p class="meta">Блок</p><h3>Новини</h3><p>Приклад картки для оцінки відступів і кольорів.</p></article><article class="card"><p class="meta">Блок</p><h3>Контакти</h3><p>Додатковий блок для перевірки сітки.</p></article></div></div></section></main>' +
            renderPreviewFooter() +
            '<script src="' + escapeHtml(previewContext.siteJs || '') + '"></script>' +
            '</body></html>';
    }

    function renderHeader() {
        const searchInput = headerEditor.querySelector('[data-menu-search]');
        const searchClear = headerEditor.querySelector('[data-menu-search-clear]');
        const issuesOnlyButton = headerEditor.querySelector('[data-menu-issues-only]');
        const nextIssueButton = headerEditor.querySelector('[data-menu-next-issue]');
        const expandAll = headerEditor.querySelector('[data-menu-expand-all]');
        const collapseAll = headerEditor.querySelector('[data-menu-collapse-all]');
        const issuesNode = headerEditor.querySelector('[data-menu-issues]');
        const query = normalizeMenuSearch(menuSearchQuery);
        const hasMenuItems = (header.links || []).length > 0;
        const duplicateUrls = duplicatedMenuUrls(header.links || []);
        const issuePaths = collectMenuIssuePaths(header.links || [], duplicateUrls, '');
        const issuesCount = countMenuIssues(header.links || [], duplicateUrls);
        if (!issuesCount) {
            menuIssuesOnly = false;
            menuIssueCursor = -1;
        } else if (menuIssueCursor >= issuePaths.length) {
            menuIssueCursor = issuePaths.length - 1;
        }
        const hasSearchResults = !query || (header.links || []).some(function (item) {
            return menuBranchMatchesSearch(item, query) && (!menuIssuesOnly || menuBranchHasIssues(item, duplicateUrls));
        });
        headerEditor.querySelector('[data-header-field="variant"]').value = header.variant || 'default';
        headerEditor.querySelector('[data-header-field="cta_label"]').value = header.cta_label || '';
        headerEditor.querySelector('[data-header-field="cta_url"]').value = header.cta_url || '';
        headerEditor.querySelector('[data-header-field="show_brand"]').checked = header.show_brand !== false;
        headerEditor.querySelector('[data-header-field="hero_enabled"]').checked = Boolean(header.hero_enabled);
        headerEditor.querySelector('[data-header-field="hero_variant"]').value = header.hero_variant || 'default';
        headerEditor.querySelector('[data-header-field="hero_title"]').value = header.hero_title || '';
        headerEditor.querySelector('[data-header-field="hero_text"]').value = header.hero_text || '';
        headerEditor.querySelector('[data-header-field="hero_button_label"]').value = header.hero_button_label || '';
        headerEditor.querySelector('[data-header-field="hero_button_url"]').value = header.hero_button_url || '';
        headerEditor.querySelector('[data-header-field="hero_background_image"]').value = header.hero_background_image || '';
        headerEditor.querySelector('[data-header-field="hero_background_position"]').value = header.hero_background_position || 'center center';
        headerEditor.querySelector('[data-header-field="hero_background_size"]').value = header.hero_background_size || 'cover';
        headerEditor.querySelector('[data-header-field="hero_background_repeat"]').value = header.hero_background_repeat || 'no-repeat';
        headerEditor.querySelector('[data-header-field="hero_overlay_enabled"]').checked = header.hero_overlay_enabled !== false;
        headerEditor.querySelector('[data-header-field="hero_overlay_opacity"]').value = header.hero_overlay_opacity || '35';
        headerEditor.querySelector('[data-header-field="home_hero_enabled"]').checked = Boolean(header.home_hero_enabled);
        headerEditor.querySelector('[data-header-field="home_hero_variant"]').value = header.home_hero_variant || 'fullscreen';
        headerEditor.querySelector('[data-header-field="home_hero_title"]').value = header.home_hero_title || '';
        headerEditor.querySelector('[data-header-field="home_hero_text"]').value = header.home_hero_text || '';
        headerEditor.querySelector('[data-header-field="home_hero_button_label"]').value = header.home_hero_button_label || '';
        headerEditor.querySelector('[data-header-field="home_hero_button_url"]').value = header.home_hero_button_url || '';
        headerEditor.querySelector('[data-header-field="home_hero_background_image"]').value = header.home_hero_background_image || '';
        headerEditor.querySelector('[data-header-field="home_hero_background_position"]').value = header.home_hero_background_position || 'center center';
        headerEditor.querySelector('[data-header-field="home_hero_background_size"]').value = header.home_hero_background_size || 'cover';
        headerEditor.querySelector('[data-header-field="home_hero_background_repeat"]').value = header.home_hero_background_repeat || 'no-repeat';
        headerEditor.querySelector('[data-header-field="home_hero_overlay_enabled"]').checked = header.home_hero_overlay_enabled !== false;
        headerEditor.querySelector('[data-header-field="home_hero_overlay_opacity"]').value = header.home_hero_overlay_opacity || '35';
        headerEditor.querySelector('[data-header-field="secondary_enabled"]').checked = Boolean(header.secondary_enabled);
        headerEditor.querySelector('[data-header-field="secondary_variant"]').value = header.secondary_variant || 'pills';
        headerEditor.querySelector('[data-header-field="mobile_variant"]').value = header.mobile_variant || 'drawer';
        headerEditor.querySelector('[data-header-field="mobile_source"]').value = header.mobile_source || 'main';
        headerEditor.querySelector('[data-header-field="mobile_label"]').value = header.mobile_label || 'Меню';
        headerEditor.querySelector('[data-header-field="mobile_show_brand"]').checked = header.mobile_show_brand !== false;
        headerEditor.querySelector('[data-header-field="mobile_show_cta"]').checked = header.mobile_show_cta !== false;
        if (searchInput && searchInput.value !== menuSearchQuery) {
            searchInput.value = menuSearchQuery;
        }
        if (searchClear) {
            searchClear.hidden = !query;
        }
        if (issuesOnlyButton) {
            issuesOnlyButton.hidden = !issuesCount;
            issuesOnlyButton.classList.toggle('is-active', menuIssuesOnly);
            issuesOnlyButton.setAttribute('aria-pressed', menuIssuesOnly ? 'true' : 'false');
        }
        if (nextIssueButton) {
            nextIssueButton.hidden = !issuesCount;
            nextIssueButton.querySelector('span:last-child').textContent = issuesCount ? 'Наступна ' + (menuIssueCursor + 1 > 0 ? menuIssueCursor + 1 : 1) + '/' + issuesCount : 'Наступна';
        }
        if (expandAll) {
            expandAll.disabled = !hasMenuItems;
        }
        if (collapseAll) {
            collapseAll.disabled = !hasMenuItems;
        }
        if (issuesNode) {
            issuesNode.hidden = !issuesCount;
            issuesNode.textContent = issuesCount ? issuesCount + ' ' + pluralizeUk(issuesCount, 'проблема', 'проблеми', 'проблем') : '';
        }
        headerEditor.querySelector('[data-header-links]').innerHTML = hasMenuItems
            ? (hasSearchResults ? renderMenuLinks(header.links || [], '', 0, query, menuIssuesOnly, duplicateUrls) : '<div class="template-empty-state"><span class="mdi mdi-magnify-close" aria-hidden="true"></span><strong>Нічого не знайдено</strong><p>Спробуйте іншу назву, URL або вимкніть фільтр проблем.</p></div>')
            : '<div class="template-empty-state"><span class="mdi mdi-menu-open" aria-hidden="true"></span><strong>Меню ще порожнє</strong><p>Додайте власний пункт або оберіть готове посилання зі сторінок, категорій чи новин.</p></div>';
        updateHeroBackgroundPreview('hero');
        updateHeroBackgroundPreview('home');
        renderSecondaryLinks();
        fillMenuParentSelect();
        syncLayouts();
    }

    function renderFooter() {
        footerEditor.querySelector('[data-footer-field="variant"]').value = footer.variant || 'default';
        footerEditor.querySelector('[data-footer-field="bottom_text"]').value = footer.bottom_text || '';
        footerEditor.querySelector('[data-footer-columns]').innerHTML = (footer.columns || []).length ? (footer.columns || []).map(function (column, columnIndex) {
            column.items = Array.isArray(column.items) ? column.items : [];
            const canMoveUp = columnIndex > 0;
            const canMoveDown = columnIndex < footer.columns.length - 1;
            return '<section class="template-footer-column" data-footer-column="' + columnIndex + '">' +
                '<div class="template-editor-list-head">' +
                    '<label>Назва колонки<input data-footer-column-field="title" value="' + escapeHtml(column.title) + '"></label>' +
                    '<div class="template-menu-actions">' +
                        '<button class="button secondary compact" type="button" data-footer-move-column="-1" title="Підняти" ' + (canMoveUp ? '' : 'disabled') + '><span class="mdi mdi-arrow-left"></span></button>' +
                        '<button class="button secondary compact" type="button" data-footer-move-column="1" title="Опустити" ' + (canMoveDown ? '' : 'disabled') + '><span class="mdi mdi-arrow-right"></span></button>' +
                        '<button class="button danger compact" type="button" data-footer-remove-column title="Видалити"><span class="mdi mdi-delete-outline"></span></button>' +
                    '</div>' +
                '</div>' +
                '<div class="template-editor-list">' + (column.items || []).map(function (item, itemIndex) {
                    const canMoveItemUp = itemIndex > 0;
                    const canMoveItemDown = itemIndex < column.items.length - 1;
                    return '<div class="template-editor-row" data-footer-item="' + itemIndex + '">' +
                        '<label>Назва<input data-footer-item-field="label" value="' + escapeHtml(item.label) + '"></label>' +
                        '<label>Текст<input data-footer-item-field="text" value="' + escapeHtml(item.text) + '"></label>' +
                        '<label>URL<input data-footer-item-field="url" value="' + escapeHtml(item.url) + '"></label>' +
                        '<div class="template-menu-actions">' +
                            '<button class="button secondary compact" type="button" data-footer-move-item="-1" title="Підняти" ' + (canMoveItemUp ? '' : 'disabled') + '><span class="mdi mdi-arrow-up"></span></button>' +
                            '<button class="button secondary compact" type="button" data-footer-move-item="1" title="Опустити" ' + (canMoveItemDown ? '' : 'disabled') + '><span class="mdi mdi-arrow-down"></span></button>' +
                            '<button class="button danger compact" type="button" data-footer-remove-item title="Видалити"><span class="mdi mdi-delete-outline"></span></button>' +
                        '</div>' +
                    '</div>';
                }).join('') + '</div>' +
                '<button class="button secondary compact" type="button" data-footer-add-item><span class="mdi mdi-plus"></span><span>Пункт</span></button>' +
            '</section>';
        }).join('') : '<div class="template-empty-state"><span class="mdi mdi-page-layout-footer" aria-hidden="true"></span><strong>Колонок ще немає</strong><p>Додайте колонку для контактів, швидких посилань або службової інформації.</p></div>';
        syncLayouts();
    }

    function findHeroBackgroundMedia(path) {
        const value = String(path || '').trim();
        if (!value) {
            return null;
        }
        return (heroBackgroundState.items || []).concat(linkPicker.media || []).find(function (item) {
            return mediaItemPath(item) === value || String(item.url || '') === value;
        }) || null;
    }

    async function loadHeroBackgroundItems(append) {
        if (!heroBackgroundNode) {
            return;
        }
        if (heroBackgroundState.loading && append) {
            return;
        }
        const grid = heroBackgroundNode.querySelector('[data-hero-background-grid]');
        const search = heroBackgroundNode.querySelector('[data-hero-background-search]');
        const status = heroBackgroundNode.querySelector('[data-hero-background-status]');
        const url = new URL(menuPickerNode ? (menuPickerNode.dataset.linkPickerUrl || '/admin/templates/link-picker') : '/admin/templates/link-picker', window.location.origin);
        const token = append ? heroBackgroundState.token : ++heroBackgroundState.token;
        url.searchParams.set('type', 'media');
        url.searchParams.set('limit', String(heroBackgroundState.limit));
        url.searchParams.set('offset', append ? String(heroBackgroundState.offset) : '0');
        if (search && search.value.trim() !== '') {
            url.searchParams.set('q', search.value.trim());
        }

        heroBackgroundState.loading = true;
        if (status) {
            status.textContent = append ? 'Підвантаження...' : 'Завантаження медіафайлів...';
        }
        if (grid) {
            grid.classList.add('is-loading');
        }
        if (grid && !append) {
            grid.innerHTML = '<div class="template-hero-media-loading"><span class="mdi mdi-loading mdi-spin" aria-hidden="true"></span><span>Завантаження зображень...</span></div>';
            heroBackgroundState.items = [];
        }

        try {
            const response = await fetch(url.toString(), {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Не вдалося завантажити медіафайли.');
            }
            if (token !== heroBackgroundState.token) {
                return;
            }

            const images = (Array.isArray(data.items) ? data.items : []).filter(function (item) {
                return mediaItemPath(item) && mediaItemIsImage(item);
            });
            heroBackgroundState.offset = Number(data.next_offset || 0);
            heroBackgroundState.total = Number(data.total || 0);
            heroBackgroundState.hasMore = Boolean(data.has_more);

            if (!images.length && heroBackgroundState.hasMore) {
                heroBackgroundState.loading = false;
                await loadHeroBackgroundItems(true);
                return;
            }

            appendHeroBackgroundItems(images, heroBackgroundState.items.length, append);
        } catch (error) {
            if (status) {
                status.textContent = error.message || 'Помилка завантаження.';
            }
            if (grid) {
                grid.classList.remove('is-loading');
                grid.innerHTML = '<div class="template-menu-picker-empty">Не вдалося завантажити зображення.</div>';
            }
        } finally {
            if (token === heroBackgroundState.token) {
                heroBackgroundState.loading = false;
                queueHeroBackgroundMoreCheck();
            }
        }
    }

    function maybeLoadMoreHeroBackgroundItems() {
        if (!heroBackgroundNode || !heroBackgroundState.hasMore || heroBackgroundState.loading) {
            return;
        }
        const heroBackgroundBody = heroBackgroundNode.querySelector('.modal-body');
        if (!heroBackgroundBody) {
            return;
        }
        const nearBottom = heroBackgroundBody.scrollTop + heroBackgroundBody.clientHeight >= heroBackgroundBody.scrollHeight - 160;
        if (nearBottom) {
            loadHeroBackgroundItems(true);
        }
    }

    function queueHeroBackgroundMoreCheck() {
        window.setTimeout(maybeLoadMoreHeroBackgroundItems, 80);
    }

    function appendHeroBackgroundItems(items, startIndex, append) {
        if (!heroBackgroundNode) {
            return;
        }
        const grid = heroBackgroundNode.querySelector('[data-hero-background-grid]');
        const status = heroBackgroundNode.querySelector('[data-hero-background-status]');
        const selected = String(header[heroBackgroundFieldName(heroBackgroundState.target)] || '');
        if (!grid) {
            return;
        }
        if (!append || !heroBackgroundState.items.length) {
            grid.innerHTML = '';
        }
        if (!items.length && !heroBackgroundState.items.length) {
            grid.classList.remove('is-loading');
            grid.innerHTML = '<div class="template-menu-picker-empty">За цим пошуком немає зображень.</div>';
            if (status) {
                status.textContent = 'Нічого не знайдено.';
            }
            return;
        }

        heroBackgroundState.items = heroBackgroundState.items.concat(items);
        grid.insertAdjacentHTML('beforeend', items.map(function (item, itemIndex) {
            return renderHeroBackgroundOption(item, selected, startIndex + itemIndex);
        }).join(''));
        grid.classList.remove('is-loading');
        if (status) {
            status.textContent = heroBackgroundState.hasMore
                ? 'Показано зображень: ' + heroBackgroundState.items.length + '. Прокрутіть нижче, щоб підвантажити ще.'
                : 'Знайдено: ' + heroBackgroundState.items.length + '.';
        }
        queueHeroBackgroundMoreCheck();
    }

    function renderHeroBackgroundOption(item, selected, index) {
        const path = mediaItemPath(item);
        const label = mediaItemLabel(item) || path;
        const active = path === selected;
        return '<button class="template-hero-media-option' + (active ? ' is-selected' : '') + '" type="button" data-hero-background-pick="' + escapeHtml(path) + '" data-hero-background-index="' + index + '" aria-pressed="' + (active ? 'true' : 'false') + '">' +
            '<span class="template-hero-media-thumb" style="background-image: url(&quot;' + escapeHtml(mediaThumbUrl(path, 360, 225)) + '&quot;)" aria-hidden="true"></span>' +
            '<strong>' + escapeHtml(label) + '</strong>' +
            '<small>' + escapeHtml(item.meta || path) + '</small>' +
        '</button>';
    }

    function openHeroBackgroundPicker(target) {
        if (!heroBackgroundNode) {
            return;
        }
        if (!heroBackgroundModal && window.bootstrap && window.bootstrap.Modal) {
            heroBackgroundModal = window.bootstrap.Modal.getOrCreateInstance(heroBackgroundNode);
        }
        if (!heroBackgroundModal) {
            return;
        }
        heroBackgroundState.target = target === 'home' ? 'home' : 'hero';
        const title = heroBackgroundNode.querySelector('[data-hero-background-title]');
        if (title) {
            title.textContent = 'Обрати фон: ' + heroBackgroundTargetLabel(heroBackgroundState.target);
        }
        const search = heroBackgroundNode.querySelector('[data-hero-background-search]');
        if (search) {
            search.value = '';
        }
        const heroBackgroundBody = heroBackgroundNode.querySelector('.modal-body');
        if (heroBackgroundBody) {
            heroBackgroundBody.scrollTop = 0;
        }
        loadHeroBackgroundItems(false);
        queueHeroBackgroundMoreCheck();
        heroBackgroundModal.show();
        if (search) {
            setTimeout(function () {
                search.focus();
            }, 180);
        }
    }

    function setHeroBackground(target, path) {
        const key = heroBackgroundFieldName(target);
        header[key] = String(path || '').trim();
        renderHeader();
    }

    function updateHeroBackgroundPreview(kind) {
        const prefix = kind === 'home' ? 'home_hero' : 'hero';
        const preview = headerEditor.querySelector('[data-hero-background-preview="' + kind + '"]');
        const summary = headerEditor.querySelector('[data-hero-background-summary="' + kind + '"]');
        if (!preview) {
            return;
        }
        const image = String(header[prefix + '_background_image'] || '').trim();
        if (!image) {
            preview.classList.add('is-empty');
            preview.textContent = 'Фонове зображення не вибрано';
            preview.style.backgroundImage = '';
            if (summary) {
                summary.classList.remove('has-image');
                summary.textContent = 'Фонове зображення не вибрано';
            }
            return;
        }
        const media = findHeroBackgroundMedia(image);
        preview.classList.remove('is-empty');
        preview.textContent = '';
        preview.style.backgroundImage = 'url("' + uploadUrl(image).replace(/"/g, '\\"') + '")';
        preview.style.backgroundPosition = header[prefix + '_background_position'] || 'center center';
        preview.style.backgroundSize = header[prefix + '_background_size'] || 'cover';
        preview.style.backgroundRepeat = header[prefix + '_background_repeat'] || 'no-repeat';
        if (summary) {
            summary.classList.add('has-image');
            summary.textContent = mediaItemLabel(media) || image;
        }
    }

    headerEditor.addEventListener('input', function (event) {
        const searchField = event.target.closest('[data-menu-search]');
        if (searchField) {
            menuSearchQuery = searchField.value;
            renderHeader();
            return;
        }
        const field = event.target.closest('[data-header-field]');
        const linkField = event.target.closest('[data-header-link-field]');
        const columnField = event.target.closest('[data-header-column-field]');
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
        if (columnField) {
            const row = columnField.closest('[data-header-link]');
            const columnNode = columnField.closest('[data-menu-column]');
            const item = getMenuItem(row.dataset.headerLink);
            const column = item && Array.isArray(item.columns) ? item.columns[Number(columnNode.dataset.menuColumn)] : null;
            if (column) {
                column[columnField.dataset.headerColumnField] = columnField.value;
            }
        }
        syncLayouts();
    });
    headerEditor.addEventListener('change', function (event) {
        const field = event.target.closest('[data-header-field]');
        const linkField = event.target.closest('[data-header-link-field]');
        if (field) {
            const key = field.dataset.headerField;
            header[key] = field.type === 'checkbox' ? field.checked : field.value;
            syncLayouts();
        }
        if (linkField) {
            const row = linkField.closest('[data-header-link]');
            const item = getMenuItem(row.dataset.headerLink);
            if (item) {
                item[linkField.dataset.headerLinkField] = linkField.value;
                if (linkField.dataset.headerLinkField === 'type') {
                    item.type = item.type === 'section' ? 'section' : 'link';
                    if (item.type === 'section') {
                        item.url = '#';
                    }
                    renderHeader();
                    return;
                }
                if (['label', 'url', 'icon'].indexOf(linkField.dataset.headerLinkField) !== -1) {
                    renderHeader();
                    return;
                }
                syncLayouts();
            }
        }
    });
    headerEditor.addEventListener('click', function (event) {
        if (event.target.closest('[data-menu-picker-open]')) {
            event.preventDefault();
            openMenuPicker();
            return;
        }
        const headerUrlPicker = event.target.closest('[data-header-url-picker]');
        if (headerUrlPicker) {
            event.preventDefault();
            openHeaderUrlPicker(headerEditor.querySelector('[data-header-field="' + cssEscape(headerUrlPicker.dataset.headerUrlPicker || '') + '"]'));
            return;
        }
        const linkUrlPicker = event.target.closest('[data-header-link-url-picker]');
        if (linkUrlPicker) {
            event.preventDefault();
            const row = linkUrlPicker.closest('[data-header-link]');
            openHeaderUrlPicker(row ? row.querySelector('[data-header-link-field="url"]') : null);
            return;
        }
        const heroBackgroundOpen = event.target.closest('[data-hero-background-open]');
        if (heroBackgroundOpen) {
            event.preventDefault();
            openHeroBackgroundPicker(heroBackgroundOpen.dataset.heroBackgroundOpen || 'hero');
            return;
        }
        const heroBackgroundClear = event.target.closest('[data-hero-background-clear]');
        if (heroBackgroundClear) {
            event.preventDefault();
            setHeroBackground(heroBackgroundClear.dataset.heroBackgroundClear || 'hero', '');
            return;
        }
        const libraryOpen = event.target.closest('[data-template-library-open]');
        if (libraryOpen) {
            openTemplateLibrary(libraryOpen.dataset.templateLibraryOpen || 'menu', libraryOpen.dataset.templateLibraryTarget || 'main');
            return;
        }
        if (event.target.closest('[data-secondary-add-link]')) {
            header.secondary_links = Array.isArray(header.secondary_links) ? header.secondary_links : [];
            header.secondary_links.push({label: '', url: '', icon: ''});
            expandedMenuNodes.add('secondary.' + (header.secondary_links.length - 1));
            renderHeader();
            return;
        }
        if (event.target.closest('[data-secondary-add-section]')) {
            addMenuSection('secondary');
            expandedMenuNodes.add('secondary.' + ((header.secondary_links || []).length - 1));
            renderHeader();
            return;
        }
        if (event.target.closest('[data-menu-search-clear]')) {
            menuSearchQuery = '';
            renderHeader();
            headerEditor.querySelector('[data-menu-search]')?.focus();
            return;
        }
        if (event.target.closest('[data-menu-issues-only]')) {
            menuIssuesOnly = !menuIssuesOnly;
            renderHeader();
            return;
        }
        if (event.target.closest('[data-menu-next-issue]')) {
            const duplicateUrls = duplicatedMenuUrls(header.links || []);
            const issuePaths = collectMenuIssuePaths(header.links || [], duplicateUrls, '');
            if (issuePaths.length) {
                menuSearchQuery = '';
                menuIssueCursor = (menuIssueCursor + 1) % issuePaths.length;
                focusMenuIssue(issuePaths[menuIssueCursor], duplicateUrls);
            }
            return;
        }
        if (event.target.closest('[data-menu-expand-all]')) {
            collectMenuPaths(header.links || [], '').forEach(function (path) {
                expandedMenuNodes.add(path);
            });
            renderHeader();
            return;
        }
        if (event.target.closest('[data-menu-collapse-all]')) {
            expandedMenuNodes.clear();
            renderHeader();
            return;
        }
        const toggleLink = event.target.closest('[data-header-toggle-link]');
        if (toggleLink) {
            const row = toggleLink.closest('[data-header-link]');
            if (row && expandedMenuNodes.has(row.dataset.headerLink)) {
                expandedMenuNodes.delete(row.dataset.headerLink);
            } else if (row) {
                expandedMenuNodes.add(row.dataset.headerLink);
            }
            renderHeader();
            return;
        }
        const preset = event.target.closest('[data-menu-preset]');
        if (preset) {
            addMenuPreset(preset.dataset.menuPreset);
        }
        const menuTemplate = event.target.closest('[data-menu-template]');
        if (menuTemplate) {
            openMenuTemplatePreview(menuTemplate.dataset.menuTemplate, menuTemplate.dataset.menuTemplateTarget || 'main');
            return;
        }
        if (event.target.closest('[data-header-add-link]')) {
            header.links = header.links || [];
            header.links.push(makeMenuItem('', ''));
            expandedMenuNodes.add(String(header.links.length - 1));
            renderHeader();
        }
        const iconPickerButton = event.target.closest('[data-header-icon-picker]');
        if (iconPickerButton) {
            openIconPicker(iconPickerButton);
            return;
        }
        const iconClear = event.target.closest('[data-header-icon-clear]');
        if (iconClear) {
            const row = iconClear.closest('[data-header-link]');
            const item = getMenuItem(row.dataset.headerLink);
            if (item) {
                item.icon = '';
            }
            renderHeader();
            return;
        }
        const addSection = event.target.closest('[data-header-add-section]');
        if (addSection) {
            const parentNode = addSection.closest('[data-header-link]');
            addMenuSection(parentNode ? parentNode.dataset.headerLink : '');
            if (parentNode) {
                expandedMenuNodes.add(parentNode.dataset.headerLink);
            } else {
                expandedMenuNodes.add(String((header.links || []).length - 1));
            }
            renderHeader();
        }
        const addColumn = event.target.closest('[data-header-add-column]');
        if (addColumn) {
            const row = addColumn.closest('[data-header-link]');
            addSectionColumn(row.dataset.headerLink);
            expandedMenuNodes.add(row.dataset.headerLink);
            renderHeader();
        }
        const addColumnLink = event.target.closest('[data-header-column-add-link]');
        if (addColumnLink) {
            const row = addColumnLink.closest('[data-header-link]');
            const column = addColumnLink.closest('[data-menu-column]');
            addColumnMenuItem(row.dataset.headerLink, Number(column.dataset.menuColumn));
            expandedMenuNodes.add(row.dataset.headerLink);
            renderHeader();
        }
        const removeColumn = event.target.closest('[data-header-column-remove]');
        if (removeColumn) {
            const row = removeColumn.closest('[data-header-link]');
            const column = removeColumn.closest('[data-menu-column]');
            removeSectionColumn(row.dataset.headerLink, Number(column.dataset.menuColumn));
            renderHeader();
        }
        const addChild = event.target.closest('[data-header-add-child]');
        if (addChild) {
            const row = addChild.closest('[data-header-link]');
            addMenuChild(row.dataset.headerLink);
            expandedMenuNodes.add(row.dataset.headerLink);
            renderHeader();
        }
        const move = event.target.closest('[data-header-move-link]');
        if (move) {
            moveMenuItem(move.closest('[data-header-link]').dataset.headerLink, Number(move.dataset.headerMoveLink));
            renderHeader();
        }
        const duplicate = event.target.closest('[data-header-duplicate-link]');
        if (duplicate) {
            const newPath = duplicateMenuItem(duplicate.closest('[data-header-link]').dataset.headerLink);
            if (newPath) {
                expandedMenuNodes.add(newPath);
            }
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
            if (event.target.closest('[data-menu-picker-add-selected]')) {
                addSelectedMenuPickerItems();
                return;
            }
            if (event.target.closest('[data-menu-picker-more]')) {
                loadMenuPickerItems(true);
                return;
            }
            const itemButton = event.target.closest('.template-menu-picker-item');
            if (itemButton) {
                toggleMenuPickerItem(itemButton);
            }
        });
        menuPickerNode.addEventListener('input', function (event) {
            if (!event.target.closest('[data-menu-picker-search]')) {
                return;
            }
            window.clearTimeout(menuPickerState.searchTimer);
            clearMenuPickerSelection();
            menuPickerState.searchTimer = window.setTimeout(resetMenuPicker, 250);
        });
        menuPickerNode.addEventListener('change', function (event) {
            if (event.target.closest('[data-menu-picker-status], [data-menu-picker-scope], [data-menu-picker-category]')) {
                clearMenuPickerSelection();
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

    if (menuTemplateNode) {
        menuTemplateNode.addEventListener('click', function (event) {
            const choice = event.target.closest('[data-template-blueprint-select]');
            if (choice) {
                selectTemplateLibraryItem(choice.dataset.templateBlueprintKind || 'menu', choice.dataset.templateBlueprintSelect || 'basic', choice.dataset.templateBlueprintTarget || 'main');
                return;
            }
            if (!event.target.closest('[data-menu-template-apply]')) {
                return;
            }
            if (menuTemplateState.kind === 'hero') {
                applyHeroTemplate(menuTemplateState.type, menuTemplateState.target, menuTemplateState.payload);
            } else if (menuTemplateState.kind === 'footer') {
                footer = cloneLayout(menuTemplateState.payload || {});
                renderFooter();
                setTemplateEditorTab('footer');
            } else {
                applyMenuTemplate(menuTemplateState.type, menuTemplateState.target, menuTemplateState.payload);
            }
            if (menuTemplateModal) {
                menuTemplateModal.hide();
            }
        });
    }

    if (heroBackgroundNode) {
        heroBackgroundNode.addEventListener('input', function (event) {
            if (event.target.closest('[data-hero-background-search]')) {
                window.clearTimeout(heroBackgroundState.searchTimer);
                heroBackgroundState.searchTimer = window.setTimeout(function () {
                    loadHeroBackgroundItems(false);
                }, 220);
            }
        });
        heroBackgroundNode.addEventListener('click', function (event) {
            const picked = event.target.closest('[data-hero-background-pick]');
            if (picked) {
                setHeroBackground(heroBackgroundState.target, picked.dataset.heroBackgroundPick || '');
                if (heroBackgroundModal) {
                    heroBackgroundModal.hide();
                }
                return;
            }
            if (event.target.closest('[data-hero-background-modal-clear]')) {
                setHeroBackground(heroBackgroundState.target, '');
                if (heroBackgroundModal) {
                    heroBackgroundModal.hide();
                }
            }
        });
        const heroBackgroundBody = heroBackgroundNode.querySelector('.modal-body');
        if (heroBackgroundBody) {
            heroBackgroundBody.addEventListener('scroll', maybeLoadMoreHeroBackgroundItems, {passive: true});
        }
        heroBackgroundNode.addEventListener('scroll', maybeLoadMoreHeroBackgroundItems, true);
    }

    if (iconPickerNode) {
        iconPickerNode.addEventListener('input', function (event) {
            if (event.target.closest('[data-icon-picker-search]')) {
                renderIconPicker();
            }
        });
        iconPickerNode.addEventListener('click', function (event) {
            const picked = event.target.closest('[data-icon-picker-select]');
            if (picked) {
                applyPickedIcon(picked.dataset.iconPickerSelect || '');
                return;
            }
            if (event.target.closest('[data-icon-picker-clear]')) {
                applyPickedIcon('');
            }
        });
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
            if (summaryTemplateName) {
                summaryTemplateName.textContent = radio.dataset.templateLabel || radio.value;
            }
            ensureTemplateLayout(selectedTemplate);
            header = cloneLayout(layouts[selectedTemplate].header);
            footer = cloneLayout(layouts[selectedTemplate].footer);
            renderHeader();
            renderFooter();
        });
    });

    templateForm?.addEventListener('submit', function () {
        syncLayouts();
    });

    revertButton?.addEventListener('click', revertTemplateChanges);

    document.addEventListener('admin:form-saved', function (event) {
        if (event.detail && event.detail.form === templateForm) {
            markSaved();
        }
    });

    if (pickerToggle) {
        pickerToggle.addEventListener('click', function () {
            setTemplatePickerCollapsed(pickerToggle.getAttribute('aria-expanded') === 'true');
        });
    }

    editorTabButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            setTemplateEditorTab(button.dataset.templateEditorTab || 'menu');
        });
    });

    previewModeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            setPreviewMode(button.dataset.templatePreviewMode || 'desktop');
        });
    });

    if (previewCollapseButton) {
        previewCollapseButton.addEventListener('click', function () {
            setPreviewCollapsed(previewCollapseButton.getAttribute('aria-expanded') === 'true', true);
        });
    }

    window.addEventListener('beforeunload', function (event) {
        if (!isDirty()) {
            return;
        }
        event.preventDefault();
        event.returnValue = '';
    });
    footerEditor.addEventListener('click', function (event) {
        const libraryOpen = event.target.closest('[data-template-library-open]');
        if (libraryOpen) {
            openTemplateLibrary(libraryOpen.dataset.templateLibraryOpen || 'footer', libraryOpen.dataset.templateLibraryTarget || 'main');
            return;
        }
        const footerTemplate = event.target.closest('[data-footer-template]');
        if (footerTemplate) {
            applyFooterTemplate(footerTemplate.dataset.footerTemplate || 'basic');
            return;
        }
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
        const moveColumn = event.target.closest('[data-footer-move-column]');
        if (moveColumn) {
            const index = Number(moveColumn.closest('[data-footer-column]').dataset.footerColumn);
            const target = index + Number(moveColumn.dataset.footerMoveColumn);
            if (target >= 0 && target < footer.columns.length) {
                const moved = footer.columns.splice(index, 1)[0];
                footer.columns.splice(target, 0, moved);
                renderFooter();
            }
        }
        const addItem = event.target.closest('[data-footer-add-item]');
        if (addItem) {
            const column = addItem.closest('[data-footer-column]');
            footer.columns[Number(column.dataset.footerColumn)].items.push({label: '', text: '', url: ''});
            renderFooter();
        }
        const moveItem = event.target.closest('[data-footer-move-item]');
        if (moveItem) {
            const column = moveItem.closest('[data-footer-column]');
            const item = moveItem.closest('[data-footer-item]');
            const items = footer.columns[Number(column.dataset.footerColumn)].items;
            const index = Number(item.dataset.footerItem);
            const target = index + Number(moveItem.dataset.footerMoveItem);
            if (target >= 0 && target < items.length) {
                const moved = items.splice(index, 1)[0];
                items.splice(target, 0, moved);
                renderFooter();
            }
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
    setTemplateEditorTab('menu');
    baselineLayouts = JSON.stringify(layouts);
    updateSaveStatus();
    if (window.matchMedia('(max-width: 800px)').matches) {
        setTemplatePickerCollapsed(true);
    }
    setPreviewMode(
        window.matchMedia('(max-width: 560px)').matches
            ? 'mobile'
            : (window.matchMedia('(max-width: 980px)').matches ? 'tablet' : 'desktop')
    );
    try {
        setPreviewCollapsed(window.localStorage.getItem('adminTemplatesPreviewCollapsed') === '1', false);
    } catch (error) {
        setPreviewCollapsed(false, false);
    }
});
