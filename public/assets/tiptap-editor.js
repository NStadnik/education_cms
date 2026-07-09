(function () {
    'use strict';

    let bundle = getBundle();
    let bundleLoading = false;
    const bundleFile = 'tiptap.bundle.20260709-7.js';
    const scriptUrl = document.currentScript ? document.currentScript.src : '';
    const assetsBase = scriptUrl ? scriptUrl.replace(/\/tiptap-editor\.js(?:\?.*)?$/, '') : '/assets';
    const editors = new WeakMap();
    const adminBody = document.body;
    const adminCsrfToken = adminBody ? (adminBody.dataset.adminCsrfToken || '') : '';
    const mediaState = {
        editor: null,
        modal: null,
        items: [],
        selected: new Set(),
        selectedItems: new Map(),
        loading: false,
        searchTimer: null,
        bound: false,
        view: 'compact',
        offset: 0,
        limit: 80,
        total: 0,
        hasMore: false,
        token: 0
    };

    function init(root) {
        bundle = getBundle() || bundle || {};
        if (!bundle.Editor) {
            markPendingEditors(root || document, 'Завантаження Tiptap...');
            loadBundle(function () {
                init(root || document);
            });
            return;
        }

        (root || document).querySelectorAll('textarea[data-tinymce-editor], textarea[data-tiptap-editor]').forEach(function (textarea) {
            if (textarea.dataset.tiptapReady === '1') {
                return;
            }
            if (textarea.closest('.modal:not(.show)')) {
                return;
            }
            textarea.dataset.tiptapReady = '1';

            const shell = document.createElement('div');
            shell.className = 'tiptap-editor';
            const toolbar = document.createElement('div');
            toolbar.className = 'tiptap-editor-toolbar';
            const content = document.createElement('div');
            content.className = 'tiptap-editor-content rich-content';
            const source = document.createElement('textarea');
            source.className = 'tiptap-editor-source';
            source.hidden = true;
            source.setAttribute('aria-label', 'HTML');
            shell.append(toolbar, content, source);
            insertAfterField(textarea, shell);
            textarea._tiptapShell = shell;

            try {
                let editor;
                try {
                    editor = createEditor(textarea, content, toolbar, fullExtensions(textarea));
                } catch (fullError) {
                    window.console && window.console.warn && window.console.warn('Full Tiptap setup failed, retrying with StarterKit only.', fullError);
                    editor = createEditor(textarea, content, toolbar, [bundle.StarterKit].filter(Boolean));
                }
                editors.set(textarea, editor);
                toolbar.append.apply(toolbar, toolbarButtons(textarea, editor, content, source));
                updateToolbar(toolbar, editor);
                syncOne(textarea);
                textarea.hidden = true;
                clearStatus(textarea);
            } catch (error) {
                textarea.dataset.tiptapReady = '0';
                shell.remove();
                textarea.hidden = false;
                setStatus(textarea, 'Tiptap не запустився: ' + (error && error.message ? error.message : String(error)));
                window.console && window.console.error && window.console.error('Tiptap editor initialization failed.', error);
            }
        });
    }

    function getBundle() {
        if (window.TiptapBundle && window.TiptapBundle.Editor) {
            return window.TiptapBundle;
        }
        if (globalThis.TiptapBundle && globalThis.TiptapBundle.Editor) {
            return globalThis.TiptapBundle;
        }
        try {
            const globalBundle = (0, eval)('typeof TiptapBundle !== "undefined" ? TiptapBundle : null');
            if (globalBundle && globalBundle.Editor) {
                window.TiptapBundle = globalBundle;
                return globalBundle;
            }
        } catch (error) {
            return null;
        }
        return null;
    }

    function insertAfterField(textarea, node) {
        const label = textarea.closest('label');
        if (label && label.contains(textarea)) {
            label.insertAdjacentElement('afterend', node);
            return;
        }
        textarea.insertAdjacentElement('afterend', node);
    }

    function markPendingEditors(root, message) {
        (root || document).querySelectorAll('textarea[data-tinymce-editor], textarea[data-tiptap-editor]').forEach(function (textarea) {
            if (textarea.dataset.tiptapReady === '1') {
                return;
            }
            setStatus(textarea, message);
        });
    }

    function fullExtensions(textarea) {
        return [
            bundle.StarterKit,
            bundle.Underline,
            bundle.Link && bundle.Link.configure({
                openOnClick: false,
                autolink: true,
                HTMLAttributes: {rel: 'noopener', target: null}
            }),
            bundle.Image && bundle.Image.configure({inline: false, allowBase64: false}),
            bundle.TextAlign && bundle.TextAlign.configure({types: ['heading', 'paragraph']}),
            bundle.Placeholder && bundle.Placeholder.configure({placeholder: textarea.getAttribute('placeholder') || ''})
        ].filter(Boolean);
    }

    function createEditor(textarea, content, toolbar, extensions) {
        let editor;
        editor = new bundle.Editor({
            element: content,
            content: textarea.value || '',
            extensions: extensions,
            onUpdate: function () {
                syncOne(textarea);
                textarea.dispatchEvent(new Event('input', {bubbles: true}));
            },
            onSelectionUpdate: function () {
                updateToolbar(toolbar, editor);
            }
        });
        return editor;
    }

    function setStatus(textarea, message) {
        clearStatus(textarea);
        const node = document.createElement('div');
        node.className = message.indexOf('...') === message.length - 3 ? 'tiptap-editor-status' : 'tiptap-editor-status is-error';
        node.textContent = message;
        insertAfterField(textarea, node);
    }

    function clearStatus(textarea) {
        const label = textarea.closest('label');
        const candidates = [
            textarea.nextElementSibling,
            label ? label.nextElementSibling : null
        ];
        candidates.forEach(function (node) {
            if (node && node.classList.contains('tiptap-editor-status')) {
                node.remove();
            }
        });
    }

    function setBundleError(message) {
        document.querySelectorAll('textarea[data-tinymce-editor], textarea[data-tiptap-editor]').forEach(function (textarea) {
            if (textarea.dataset.tiptapReady !== '1') {
                setStatus(textarea, message);
            }
        });
    }

    function clearBundleStatus(root) {
        (root || document).querySelectorAll('textarea[data-tinymce-editor], textarea[data-tiptap-editor]').forEach(function (textarea) {
            if (textarea.dataset.tiptapReady !== '1') {
                clearStatus(textarea);
            }
        });
    }

    function loadBundle(callback) {
        const readyBundle = getBundle();
        if (readyBundle && readyBundle.Editor) {
            bundle = readyBundle;
            callback();
            return;
        }
        if (bundleLoading) {
            document.addEventListener('tiptap-bundle-ready', callback, {once: true});
            return;
        }

        bundleLoading = true;
        document.addEventListener('tiptap-bundle-ready', callback, {once: true});

        const script = document.createElement('script');
        script.src = assetsBase + '/' + bundleFile;
        script.async = false;
        script.onload = function () {
            bundle = getBundle() || {};
            bundleLoading = false;
            if (bundle.Editor) {
                clearBundleStatus(document);
                document.dispatchEvent(new Event('tiptap-bundle-ready'));
            } else {
                setBundleError('Tiptap bundle завантажився, але window.TiptapBundle.Editor недоступний. URL: ' + script.src);
            }
        };
        script.onerror = function () {
            bundleLoading = false;
            setBundleError('Не вдалося завантажити Tiptap bundle: ' + script.src);
            window.console && window.console.error && window.console.error('Tiptap bundle failed to load: ' + script.src);
        };
        document.head.appendChild(script);
    }

    function toolbarButtons(textarea, editor, content, source) {
        const block = document.createElement('select');
        block.title = 'Формат';
        [
            ['paragraph', 'Абзац'],
            ['h2', 'Заголовок 2'],
            ['h3', 'Заголовок 3'],
            ['blockquote', 'Цитата']
        ].forEach(function (item) {
            const option = document.createElement('option');
            option.value = item[0];
            option.textContent = item[1];
            block.appendChild(option);
        });
        block.addEventListener('change', function () {
            if (block.value === 'h2') {
                editor.chain().focus().toggleHeading({level: 2}).run();
            } else if (block.value === 'h3') {
                editor.chain().focus().toggleHeading({level: 3}).run();
            } else if (block.value === 'blockquote') {
                editor.chain().focus().toggleBlockquote().run();
            } else {
                editor.chain().focus().setParagraph().run();
            }
        });

        return [
            block,
            button('mdi-format-bold', 'Жирний', function () { editor.chain().focus().toggleBold().run(); }, 'bold'),
            button('mdi-format-italic', 'Курсив', function () { editor.chain().focus().toggleItalic().run(); }, 'italic'),
            button('mdi-format-underline', 'Підкреслення', function () { editor.chain().focus().toggleUnderline().run(); }, 'underline'),
            button('mdi-format-list-bulleted', 'Маркований список', function () { editor.chain().focus().toggleBulletList().run(); }, 'bulletList'),
            button('mdi-format-list-numbered', 'Нумерований список', function () { editor.chain().focus().toggleOrderedList().run(); }, 'orderedList'),
            button('mdi-format-align-left', 'Ліворуч', function () { editor.chain().focus().setTextAlign('left').run(); }, 'align-left'),
            button('mdi-format-align-center', 'По центру', function () { editor.chain().focus().setTextAlign('center').run(); }, 'align-center'),
            button('mdi-format-align-right', 'Праворуч', function () { editor.chain().focus().setTextAlign('right').run(); }, 'align-right'),
            button('mdi-format-align-justify', 'По ширині', function () { editor.chain().focus().setTextAlign('justify').run(); }, 'align-justify'),
            button('mdi-link-variant', 'Покликання', function () { setLink(editor); }, 'link'),
            button('mdi-link-off', 'Прибрати покликання', function () { editor.chain().focus().unsetLink().run(); }),
            button('mdi-image', 'Медіафайли', function () { openMediaPicker(textarea); }),
            button('mdi-code-tags', 'HTML', function (event) { toggleHtmlMode(textarea, editor, content, source, event.currentTarget); }, 'source')
        ];
    }

    function button(icon, title, action, activeName) {
        const node = document.createElement('button');
        node.type = 'button';
        node.title = title;
        node.dataset.tiptapActive = activeName || '';
        node.innerHTML = '<span class="mdi ' + icon + '" aria-hidden="true"></span>';
        node.addEventListener('click', action);
        return node;
    }

    function updateToolbar(toolbar, editor) {
        toolbar.querySelectorAll('[data-tiptap-active]').forEach(function (node) {
            const name = node.dataset.tiptapActive;
            let active = false;
            if (name === 'align-left') {
                active = editor.isActive({textAlign: 'left'});
            } else if (name === 'align-center') {
                active = editor.isActive({textAlign: 'center'});
            } else if (name === 'align-right') {
                active = editor.isActive({textAlign: 'right'});
            } else if (name === 'align-justify') {
                active = editor.isActive({textAlign: 'justify'});
            } else if (name === 'source') {
                active = node.getAttribute('aria-pressed') === 'true';
            } else if (name) {
                active = editor.isActive(name);
            }
            node.classList.toggle('is-active', active);
        });

        const block = toolbar.querySelector('select');
        if (block) {
            block.value = editor.isActive('heading', {level: 2}) ? 'h2'
                : editor.isActive('heading', {level: 3}) ? 'h3'
                : editor.isActive('blockquote') ? 'blockquote'
                : 'paragraph';
        }
    }

    function setLink(editor) {
        const previous = editor.getAttributes('link').href || '';
        const href = window.prompt('URL', previous);
        if (href === null) {
            return;
        }
        if (href.trim() === '') {
            editor.chain().focus().unsetLink().run();
            return;
        }
        editor.chain().focus().extendMarkRange('link').setLink({href: href.trim()}).run();
    }

    function toggleHtmlMode(textarea, editor, content, source, buttonNode) {
        const sourceVisible = !source.hidden;
        if (sourceVisible) {
            setContent(textarea, source.value);
            source.hidden = true;
            content.hidden = false;
            buttonNode.setAttribute('aria-pressed', 'false');
            editor.commands.focus();
            updateToolbar(buttonNode.closest('.tiptap-editor-toolbar'), editor);
            return;
        }

        syncOne(textarea);
        source.value = formatHtml(textarea.value || '');
        content.hidden = true;
        source.hidden = false;
        buttonNode.setAttribute('aria-pressed', 'true');
        source.focus();
        updateToolbar(buttonNode.closest('.tiptap-editor-toolbar'), editor);
    }

    function formatHtml(html) {
        return String(html || '')
            .replace(/></g, '>\n<')
            .replace(/(<\/(?:p|h[1-6]|ul|ol|li|blockquote|figure|figcaption|div)>)/gi, '$1\n')
            .replace(/\n{3,}/g, '\n\n')
            .trim();
    }

    function syncAll(root) {
        (root || document).querySelectorAll('textarea[data-tinymce-editor], textarea[data-tiptap-editor]').forEach(syncOne);
    }

    function syncOne(textarea) {
        const editor = textarea ? editors.get(textarea) : null;
        if (editor) {
            const shell = textarea._tiptapShell;
            const source = shell ? shell.querySelector('.tiptap-editor-source') : null;
            textarea.value = source && !source.hidden ? source.value : editor.getHTML();
        }
    }

    function setContent(textarea, html) {
        if (!textarea) {
            return;
        }
        textarea.value = html || '';
        const editor = editors.get(textarea);
        if (editor) {
            editor.commands.setContent(html || '', false);
            const shell = textarea._tiptapShell;
            const source = shell ? shell.querySelector('.tiptap-editor-source') : null;
            if (source && !source.hidden) {
                source.value = formatHtml(html || '');
            }
        }
    }

    function insertContent(textarea, html) {
        if (!textarea) {
            return;
        }
        const editor = editors.get(textarea);
        if (editor) {
            editor.chain().focus().insertContent(html || '').run();
            syncOne(textarea);
        } else {
            const separator = String(textarea.value || '').trim() ? '\n\n' : '';
            textarea.value = String(textarea.value || '') + separator + (html || '');
        }
        textarea.dispatchEvent(new Event('input', {bubbles: true}));
    }

    function openMediaPicker(textarea) {
        const modalNode = document.getElementById('richMediaModal');
        if (!modalNode || !window.bootstrap) {
            return;
        }
        bindMediaPicker(modalNode);
        mediaState.editor = textarea;
        mediaState.selected = new Set();
        mediaState.selectedItems = new Map();
        modalNode.querySelector('[data-rich-media-caption]').value = '';
        modalNode.querySelector('[data-rich-media-mode]').value = 'single';
        modalNode.querySelector('[data-rich-media-align]').value = 'center';
        modalNode.querySelector('[data-rich-media-search]').value = '';
        mediaState.offset = 0;
        mediaState.total = 0;
        mediaState.hasMore = false;
        setRichMediaView(localStorage.getItem('richMediaViewMode') || 'compact', false);

        mediaState.modal = window.bootstrap.Modal.getOrCreateInstance(modalNode);
        mediaState.modal.show();
        const modalBody = modalNode.querySelector('.modal-body');
        if (modalBody) {
            modalBody.scrollTop = 0;
        }
        loadMediaItems(false);
    }

    function bindMediaPicker(modalNode) {
        if (mediaState.bound) {
            return;
        }
        mediaState.bound = true;

        modalNode.querySelector('[data-rich-media-search]').addEventListener('input', function () {
            window.clearTimeout(mediaState.searchTimer);
            mediaState.searchTimer = window.setTimeout(function () { loadMediaItems(false); }, 250);
        });
        modalNode.querySelector('[data-rich-media-mode]').addEventListener('change', function () {
            if (this.value === 'single' && mediaState.selected.size > 1) {
                const first = Array.from(mediaState.selected)[0];
                mediaState.selected = new Set(first ? [first] : []);
                mediaState.selectedItems = new Map(first && mediaState.selectedItems.has(first) ? [[first, mediaState.selectedItems.get(first)]] : []);
            }
            renderMediaItems();
        });
        modalNode.querySelector('[data-rich-media-align]').addEventListener('change', updateSelectionPreview);
        modalNode.querySelector('[data-rich-media-columns]').addEventListener('change', updateSelectionPreview);
        modalNode.querySelector('[data-rich-media-caption]').addEventListener('input', updateSelectionPreview);
        modalNode.querySelector('[data-rich-media-clear]').addEventListener('click', function () {
            mediaState.selected = new Set();
            mediaState.selectedItems = new Map();
            renderMediaItems();
        });
        modalNode.querySelectorAll('[data-rich-media-view]').forEach(function (viewButton) {
            viewButton.addEventListener('click', function () {
                setRichMediaView(viewButton.dataset.richMediaView || 'compact', true);
            });
        });
        modalNode.querySelector('[data-rich-media-insert]').addEventListener('click', insertSelectedMedia);
        modalNode.querySelector('[data-rich-media-upload]').addEventListener('change', uploadMediaFile);
        const modalBody = modalNode.querySelector('.modal-body');
        if (modalBody) {
            modalBody.addEventListener('scroll', maybeLoadMoreMediaItems, {passive: true});
        }
    }

    async function loadMediaItems(append) {
        if (mediaState.loading && append) {
            return;
        }
        const modalNode = document.getElementById('richMediaModal');
        const grid = modalNode.querySelector('[data-rich-media-grid]');
        const status = modalNode.querySelector('[data-rich-media-status]');
        const search = modalNode.querySelector('[data-rich-media-search]').value.trim();
        const url = new URL(adminBody ? (adminBody.dataset.richMediaPickerUrl || '/admin/media/picker') : '/admin/media/picker', window.location.origin);
        const token = append ? mediaState.token : ++mediaState.token;
        url.searchParams.set('limit', String(mediaState.limit));
        url.searchParams.set('offset', append ? String(mediaState.offset) : '0');
        if (search !== '') {
            url.searchParams.set('q', search);
        }

        mediaState.loading = true;
        status.textContent = append ? 'Підвантаження...' : 'Завантаження...';
        if (!append) {
            mediaState.items = [];
            mediaState.offset = 0;
            mediaState.total = 0;
            mediaState.hasMore = false;
            grid.innerHTML = '';
            updateSelectionPreview();
        }

        try {
            const response = await fetch(url.toString(), {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Не вдалося завантажити медіафайли.');
            }
            if (token !== mediaState.token) {
                return;
            }
            const items = data.items || [];
            mediaState.items = append ? mediaState.items.concat(items) : items;
            mediaState.offset = Number(data.next_offset || 0);
            mediaState.total = Number(data.total || 0);
            mediaState.hasMore = Boolean(data.has_more);
            mediaState.items.forEach(function (item) {
                if (mediaState.selected.has(item.path)) {
                    mediaState.selectedItems.set(item.path, item);
                }
            });
            renderMediaItems();
            status.textContent = mediaState.items.length
                ? (mediaState.hasMore ? 'Показано файлів: ' + mediaState.items.length + ' з ' + mediaState.total + '.' : 'Знайдено файлів: ' + mediaState.items.length + '.')
                : 'Файлів не знайдено.';
        } catch (error) {
            if (token === mediaState.token) {
                status.textContent = error.message || 'Помилка завантаження.';
            }
        } finally {
            if (token === mediaState.token) {
                mediaState.loading = false;
                window.setTimeout(maybeLoadMoreMediaItems, 80);
            }
        }
    }

    function maybeLoadMoreMediaItems() {
        if (!mediaState.hasMore || mediaState.loading) {
            return;
        }
        const modalNode = document.getElementById('richMediaModal');
        const modalBody = modalNode ? modalNode.querySelector('.modal-body') : null;
        if (modalBody && modalBody.scrollTop + modalBody.clientHeight >= modalBody.scrollHeight - 160) {
            loadMediaItems(true);
        }
    }

    function renderMediaItems() {
        const modalNode = document.getElementById('richMediaModal');
        const grid = modalNode.querySelector('[data-rich-media-grid]');
        grid.innerHTML = '';
        grid.classList.toggle('is-large', mediaState.view === 'large');
        mediaState.items.forEach(function (item) {
            const selectedIndex = Array.from(mediaState.selected).indexOf(item.path);
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'rich-media-card' + (mediaState.selected.has(item.path) ? ' is-selected' : '');
            card.innerHTML = (item.is_image ? '<img src="' + escapeHtml(item.thumb_url || item.url) + '" alt="">' : '<span class="mdi mdi-file-outline rich-media-file-icon" aria-hidden="true"></span>') +
                (selectedIndex >= 0 ? '<span class="rich-media-card-check">' + (selectedIndex + 1) + '</span>' : '') +
                '<span class="rich-media-card-name">' + escapeHtml(item.name) + '</span>' +
                '<small>' + escapeHtml(item.type) + ' · ' + escapeHtml(item.size_label) + '</small>';
            card.addEventListener('click', function () {
                const mode = modalNode.querySelector('[data-rich-media-mode]').value;
                if (mode === 'single') {
                    mediaState.selected = new Set([item.path]);
                    mediaState.selectedItems = new Map([[item.path, item]]);
                } else {
                    if (!item.is_image) {
                        return;
                    }
                    if (mediaState.selected.has(item.path)) {
                        mediaState.selected.delete(item.path);
                        mediaState.selectedItems.delete(item.path);
                    } else {
                        mediaState.selected.add(item.path);
                        mediaState.selectedItems.set(item.path, item);
                    }
                }
                renderMediaItems();
            });
            grid.appendChild(card);
        });
        updateSelectionPreview();
    }

    function setRichMediaView(mode, persist) {
        const modalNode = document.getElementById('richMediaModal');
        const normalized = mode === 'large' ? 'large' : 'compact';
        mediaState.view = normalized;
        if (modalNode) {
            modalNode.querySelectorAll('[data-rich-media-view]').forEach(function (viewButton) {
                const active = viewButton.dataset.richMediaView === normalized;
                viewButton.classList.toggle('secondary', !active);
                viewButton.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        }
        if (persist) {
            localStorage.setItem('richMediaViewMode', normalized);
        }
    }

    function selectedMediaItems() {
        return Array.from(mediaState.selected).map(function (path) {
            return mediaState.selectedItems.get(path);
        }).filter(Boolean);
    }

    function updateSelectionPreview() {
        const modalNode = document.getElementById('richMediaModal');
        if (!modalNode) {
            return;
        }
        const selected = selectedMediaItems();
        const mode = modalNode.querySelector('[data-rich-media-mode]').value;
        const align = modalNode.querySelector('[data-rich-media-align]').value;
        const columns = modalNode.querySelector('[data-rich-media-columns]').value;
        const caption = modalNode.querySelector('[data-rich-media-caption]').value.trim();
        const count = modalNode.querySelector('[data-rich-media-selection-count]');
        const help = modalNode.querySelector('[data-rich-media-selection-help]');
        const clear = modalNode.querySelector('[data-rich-media-clear]');
        const list = modalNode.querySelector('[data-rich-media-selected-list]');
        const preview = modalNode.querySelector('[data-rich-media-preview]');
        const galleryItems = selected.filter(function (item) { return item.is_image; });

        count.textContent = selected.length ? 'Вибрано: ' + selected.length : 'Нічого не вибрано';
        help.textContent = selected.length ? (mode === 'gallery' ? 'Галерея буде сформована з вибраних зображень.' : 'Буде вставлено перший вибраний файл.') : 'Оберіть файл у списку.';
        clear.hidden = selected.length === 0;
        list.innerHTML = selected.map(function (item, index) {
            return '<div class="rich-media-selected-item"><span class="rich-media-selected-order">' + (index + 1) + '</span>' +
                (item.is_image ? '<img src="' + escapeHtml(item.url) + '" alt="">' : '<span class="mdi mdi-file-outline rich-media-selected-file" aria-hidden="true"></span>') +
                '<span><strong>' + escapeHtml(item.name) + '</strong><small>' + escapeHtml(item.size_label || item.type || '') + '</small></span></div>';
        }).join('');

        if (!selected.length) {
            preview.innerHTML = '<div class="rich-media-preview-empty">Попередній перегляд зʼявиться після вибору медіафайлів.</div>';
        } else if (mode === 'gallery') {
            preview.innerHTML = galleryItems.length ? '<div class="rich-media-preview-frame">' + buildGalleryHtml(galleryItems, align, columns, caption) + '</div>' : '<div class="rich-media-preview-empty">Для галереї потрібні зображення.</div>';
        } else {
            preview.innerHTML = '<div class="rich-media-preview-frame">' + buildMediaHtml(selected[0], align, caption) + '</div>';
        }
    }

    async function uploadMediaFile(event) {
        const input = event.target;
        const file = input.files && input.files[0] ? input.files[0] : null;
        if (!file) {
            return;
        }
        const modalNode = document.getElementById('richMediaModal');
        const status = modalNode.querySelector('[data-rich-media-status]');
        const formData = new FormData();
        formData.append('_csrf', adminCsrfToken);
        formData.append('file', file);
        status.textContent = 'Завантаження файлу...';
        try {
            const response = await fetch(adminBody ? (adminBody.dataset.richMediaUploadUrl || '/admin/media/upload') : '/admin/media/upload', {
                method: 'POST',
                body: formData,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Не вдалося завантажити файл.');
            }
            mediaState.selected = new Set(data.uploaded_path ? [data.uploaded_path] : []);
            input.value = '';
            await loadMediaItems(false);
            status.textContent = 'Файл завантажено.';
        } catch (error) {
            status.textContent = error.message || 'Помилка завантаження.';
        }
    }

    function insertSelectedMedia() {
        const modalNode = document.getElementById('richMediaModal');
        const selected = selectedMediaItems();
        if (!selected.length || !mediaState.editor) {
            modalNode.querySelector('[data-rich-media-status]').textContent = 'Оберіть файл для вставки.';
            return;
        }
        const mode = modalNode.querySelector('[data-rich-media-mode]').value;
        const align = modalNode.querySelector('[data-rich-media-align]').value;
        const columns = modalNode.querySelector('[data-rich-media-columns]').value;
        const caption = modalNode.querySelector('[data-rich-media-caption]').value.trim();
        const html = mode === 'gallery' ? buildGalleryHtml(selected.filter(function (item) { return item.is_image; }), align, columns, caption) : buildMediaHtml(selected[0], align, caption);
        if (html === '') {
            modalNode.querySelector('[data-rich-media-status]').textContent = 'Для галереї оберіть зображення.';
            return;
        }
        insertContent(mediaState.editor, html);
        mediaState.modal.hide();
    }

    function buildMediaHtml(item, align, caption) {
        const mediaCaption = caption || item.caption || '';
        const mediaAlt = item.alt_text || mediaCaption || item.title || item.name;
        if (item.is_image) {
            return '<p><img src="' + escapeHtml(item.url) + '" alt="' + escapeHtml(mediaAlt) + '"></p>' +
                (mediaCaption ? '<p class="rich-gallery-caption media-align-' + escapeHtml(align) + '">' + escapeHtml(mediaCaption) + '</p>' : '');
        }
        return '<p class="rich-file-link media-align-' + escapeHtml(align) + '"><a href="' + escapeHtml(item.url) + '" target="_blank" rel="noopener">' + escapeHtml(mediaCaption || item.title || item.name) + '</a></p>';
    }

    function buildGalleryHtml(items, align, columns, caption) {
        if (!items.length) {
            return '';
        }
        const images = items.map(function (item) {
            return '<p><img src="' + escapeHtml(item.url) + '" alt="' + escapeHtml(item.alt_text || item.title || item.name) + '"></p>';
        }).join('');
        return images + (caption ? '<p class="rich-gallery-caption media-align-' + escapeHtml(align) + '">' + escapeHtml(caption) + '</p>' : '');
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (character) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character];
        });
    }

    const api = {
        init: init,
        syncAll: syncAll,
        syncOne: syncOne,
        setContent: setContent,
        insertContent: insertContent
    };

    window.TiptapEditor = api;
    window.TinyMceEditor = api;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            init(document);
        }, {once: true});
    } else {
        init(document);
    }
})();
