(function () {
    'use strict';

    const scriptUrl = document.currentScript ? document.currentScript.src : '';
    const assetsBase = scriptUrl ? scriptUrl.replace(/\/tinymce-editor\.js(?:\?.*)?$/, '') : '/assets';
    const tinymceBase = assetsBase + '/tinymce';
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
        bookmark: null
    };

    function init(root) {
        if (!window.tinymce) {
            return;
        }

        (root || document).querySelectorAll('textarea[data-tinymce-editor]').forEach(function (textarea) {
            if (textarea.dataset.tinymceReady === '1') {
                return;
            }
            if (textarea.closest('.modal:not(.show)')) {
                return;
            }
            textarea.dataset.tinymceReady = '1';
            if (!textarea.id) {
                textarea.id = 'tinymce-editor-' + Math.random().toString(36).slice(2);
            }

            window.tinymce.init({
                target: textarea,
                base_url: tinymceBase,
                suffix: '.min',
                license_key: 'gpl',
                language: 'uk',
                language_url: tinymceBase + '/langs/uk.js',
                plugins: 'advlist autolink lists link image media table code preview fullscreen searchreplace wordcount quickbars autoresize',
                toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link table mediaLibrary | code fullscreen preview',
                toolbar_mode: 'wrap',
                quickbars_selection_toolbar: 'bold italic | quicklink h2 h3 blockquote',
                menubar: false,
                branding: false,
                promotion: false,
                min_height: 360,
                autoresize_bottom_margin: 24,
                content_css: [assetsBase + '/site.css'],
                body_class: 'rich-content',
                convert_urls: false,
                relative_urls: false,
                remove_script_host: false,
                extended_valid_elements: 'figure[class],figcaption[class],div[class],p[class],a[href|target|rel|class],img[src|alt|class|width|height|style]',
                setup: function (editor) {
                    const notifyTextarea = function () {
                        editor.save();
                        textarea.dispatchEvent(new Event('input', {bubbles: true}));
                    };
                    editor.on('input change keyup undo redo SetContent', notifyTextarea);
                    editor.ui.registry.addButton('mediaLibrary', {
                        icon: 'image',
                        tooltip: 'Медіафайли',
                        onAction: function () {
                            openMediaPicker(editor);
                        }
                    });
                }
            });
        });
    }

    function syncAll(root) {
        const scope = root || document;
        scope.querySelectorAll('textarea[data-tinymce-editor]').forEach(syncOne);
    }

    function syncOne(textarea) {
        if (!textarea || !window.tinymce) {
            return;
        }
        const editor = textarea.id ? window.tinymce.get(textarea.id) : null;
        if (editor) {
            editor.save();
        }
    }

    function setContent(textarea, html) {
        if (!textarea) {
            return;
        }
        textarea.value = html;
        const editor = textarea.id && window.tinymce ? window.tinymce.get(textarea.id) : null;
        if (editor) {
            editor.setContent(html || '');
            editor.save();
        }
    }

    function openMediaPicker(editor) {
        const modalNode = document.getElementById('richMediaModal');
        if (!modalNode || !window.bootstrap) {
            return;
        }

        bindMediaPicker(modalNode);
        mediaState.editor = editor;
        mediaState.bookmark = editor.selection ? editor.selection.getBookmark(2, true) : null;
        mediaState.selected = new Set();
        mediaState.selectedItems = new Map();
        modalNode.querySelector('[data-rich-media-caption]').value = '';
        modalNode.querySelector('[data-rich-media-mode]').value = 'single';
        modalNode.querySelector('[data-rich-media-align]').value = 'center';
        modalNode.querySelector('[data-rich-media-search]').value = '';

        mediaState.modal = window.bootstrap.Modal.getOrCreateInstance(modalNode);
        mediaState.modal.show();
        loadMediaItems();
    }

    function bindMediaPicker(modalNode) {
        if (mediaState.bound) {
            return;
        }
        mediaState.bound = true;

        modalNode.querySelector('[data-rich-media-search]').addEventListener('input', function () {
            window.clearTimeout(mediaState.searchTimer);
            mediaState.searchTimer = window.setTimeout(loadMediaItems, 250);
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
        modalNode.querySelector('[data-rich-media-insert]').addEventListener('click', insertSelectedMedia);
        modalNode.querySelector('[data-rich-media-upload]').addEventListener('change', uploadMediaFile);
    }

    async function loadMediaItems() {
        const modalNode = document.getElementById('richMediaModal');
        const grid = modalNode.querySelector('[data-rich-media-grid]');
        const status = modalNode.querySelector('[data-rich-media-status]');
        const search = modalNode.querySelector('[data-rich-media-search]').value.trim();
        const url = new URL(adminBody ? (adminBody.dataset.richMediaPickerUrl || '/admin/media/picker') : '/admin/media/picker', window.location.origin);
        url.searchParams.set('limit', '80');
        if (search !== '') {
            url.searchParams.set('q', search);
        }

        mediaState.loading = true;
        status.textContent = 'Завантаження...';
        grid.innerHTML = '';
        updateSelectionPreview();

        try {
            const response = await fetch(url.toString(), {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Не вдалося завантажити медіафайли.');
            }
            mediaState.items = data.items || [];
            mediaState.items.forEach(function (item) {
                if (mediaState.selected.has(item.path)) {
                    mediaState.selectedItems.set(item.path, item);
                }
            });
            renderMediaItems();
            status.textContent = mediaState.items.length ? 'Знайдено файлів: ' + data.total + '.' : 'Файлів не знайдено.';
        } catch (error) {
            status.textContent = error.message || 'Помилка завантаження.';
        } finally {
            mediaState.loading = false;
        }
    }

    function renderMediaItems() {
        const modalNode = document.getElementById('richMediaModal');
        const grid = modalNode.querySelector('[data-rich-media-grid]');
        grid.innerHTML = '';

        mediaState.items.forEach(function (item) {
            const selectedIndex = Array.from(mediaState.selected).indexOf(item.path);
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'rich-media-card' + (mediaState.selected.has(item.path) ? ' is-selected' : '');
            card.innerHTML = (item.is_image
                ? '<img src="' + escapeHtml(item.url) + '" alt="">'
                : '<span class="mdi mdi-file-outline rich-media-file-icon" aria-hidden="true"></span>') +
                (selectedIndex >= 0 ? '<span class="rich-media-card-check">' + (selectedIndex + 1) + '</span>' : '') +
                '<span class="rich-media-card-name">' + escapeHtml(item.name) + '</span>' +
                '<small>' + escapeHtml(item.type) + ' · ' + escapeHtml(item.size_label) + '</small>';
            card.addEventListener('click', function () {
                const currentMode = modalNode.querySelector('[data-rich-media-mode]').value;
                if (currentMode === 'single') {
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
        help.textContent = selected.length
            ? (mode === 'gallery' ? 'Галерея буде сформована з вибраних зображень.' : 'Буде вставлено перший вибраний файл.')
            : 'Оберіть файл у списку.';
        clear.hidden = selected.length === 0;

        list.innerHTML = selected.map(function (item, index) {
            return '<div class="rich-media-selected-item">' +
                '<span class="rich-media-selected-order">' + (index + 1) + '</span>' +
                (item.is_image
                    ? '<img src="' + escapeHtml(item.url) + '" alt="">'
                    : '<span class="mdi mdi-file-outline rich-media-selected-file" aria-hidden="true"></span>') +
                '<span><strong>' + escapeHtml(item.name) + '</strong><small>' + escapeHtml(item.size_label || item.type || '') + '</small></span>' +
            '</div>';
        }).join('');

        if (!selected.length) {
            preview.innerHTML = '<div class="rich-media-preview-empty">Попередній перегляд зʼявиться після вибору медіафайлів.</div>';
            return;
        }

        if (mode === 'gallery') {
            preview.innerHTML = galleryItems.length
                ? '<div class="rich-media-preview-frame">' + buildGalleryHtml(galleryItems, align, columns, caption) + '</div>'
                : '<div class="rich-media-preview-empty">Для галереї потрібні зображення.</div>';
            return;
        }

        preview.innerHTML = '<div class="rich-media-preview-frame">' + buildMediaHtml(selected[0], align, caption) + '</div>';
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
            await loadMediaItems();
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
        const html = mode === 'gallery'
            ? buildGalleryHtml(selected.filter(function (item) { return item.is_image; }), align, columns, caption)
            : buildMediaHtml(selected[0], align, caption);
        if (html === '') {
            modalNode.querySelector('[data-rich-media-status]').textContent = 'Для галереї оберіть зображення.';
            return;
        }

        mediaState.editor.focus();
        if (mediaState.bookmark && mediaState.editor.selection) {
            mediaState.editor.selection.moveToBookmark(mediaState.bookmark);
        }
        mediaState.editor.insertContent(html);
        mediaState.editor.save();
        mediaState.modal.hide();
    }

    function buildMediaHtml(item, align, caption) {
        if (item.is_image) {
            return '<figure class="rich-media-block media-align-' + escapeHtml(align) + '"><img src="' + escapeHtml(item.url) + '" alt="' + escapeHtml(caption || item.name) + '">' +
                (caption ? '<figcaption>' + escapeHtml(caption) + '</figcaption>' : '') + '</figure>';
        }

        return '<p class="rich-file-link media-align-' + escapeHtml(align) + '"><a href="' + escapeHtml(item.url) + '" target="_blank" rel="noopener">' +
            escapeHtml(caption || item.name) + '</a></p>';
    }

    function buildGalleryHtml(items, align, columns, caption) {
        if (!items.length) {
            return '';
        }

        const images = items.map(function (item) {
            return '<figure><img src="' + escapeHtml(item.url) + '" alt="' + escapeHtml(item.name) + '"></figure>';
        }).join('');

        return '<div class="rich-gallery rich-gallery-cols-' + escapeHtml(columns) + ' media-align-' + escapeHtml(align) + '">' + images + '</div>' +
            (caption ? '<p class="rich-gallery-caption media-align-' + escapeHtml(align) + '">' + escapeHtml(caption) + '</p>' : '');
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[character];
        });
    }

    window.TinyMceEditor = {
        init: init,
        syncAll: syncAll,
        syncOne: syncOne,
        setContent: setContent
    };
})();
