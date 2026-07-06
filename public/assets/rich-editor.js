(function () {
    const adminBody = document.body;
    const adminCsrfToken = adminBody ? (adminBody.dataset.adminCsrfToken || '') : '';

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

function initRichEditors() {
    document.querySelectorAll('textarea[data-rich-editor]').forEach(function (textarea) {
        if (textarea.dataset.richEditorReady === '1') {
            return;
        }
        textarea.dataset.richEditorReady = '1';
        textarea.classList.add('rich-editor-source');
        textarea.required = false;

        const editor = document.createElement('div');
        editor.className = 'rich-editor';
        editor.innerHTML =
            '<div class="rich-editor-toolbar" aria-label="Панель форматування">' +
                '<select data-rich-command="formatBlock" title="Стиль">' +
                    '<option value="p">Абзац</option>' +
                    '<option value="h2">Заголовок 2</option>' +
                    '<option value="h3">Заголовок 3</option>' +
                    '<option value="blockquote">Цитата</option>' +
                '</select>' +
                '<button type="button" data-rich-command="bold" title="Жирний"><span class="mdi mdi-format-bold" aria-hidden="true"></span></button>' +
                '<button type="button" data-rich-command="italic" title="Курсив"><span class="mdi mdi-format-italic" aria-hidden="true"></span></button>' +
                '<button type="button" data-rich-command="underline" title="Підкреслення"><span class="mdi mdi-format-underline" aria-hidden="true"></span></button>' +
                '<button type="button" data-rich-command="insertUnorderedList" title="Маркований список"><span class="mdi mdi-format-list-bulleted" aria-hidden="true"></span></button>' +
                '<button type="button" data-rich-command="insertOrderedList" title="Нумерований список"><span class="mdi mdi-format-list-numbered" aria-hidden="true"></span></button>' +
                '<button type="button" data-rich-command="justifyLeft" title="Ліворуч"><span class="mdi mdi-format-align-left" aria-hidden="true"></span></button>' +
                '<button type="button" data-rich-command="justifyCenter" title="По центру"><span class="mdi mdi-format-align-center" aria-hidden="true"></span></button>' +
                '<button type="button" data-rich-command="justifyRight" title="Праворуч"><span class="mdi mdi-format-align-right" aria-hidden="true"></span></button>' +
                '<span class="rich-editor-divider" aria-hidden="true"></span>' +
                '<button type="button" data-rich-media-open title="Медіафайл"><span class="mdi mdi-image-plus-outline" aria-hidden="true"></span></button>' +
                '<button type="button" data-rich-align="left" title="Медіа ліворуч"><span class="mdi mdi-format-float-left" aria-hidden="true"></span></button>' +
                '<button type="button" data-rich-align="center" title="Медіа по центру"><span class="mdi mdi-format-float-center" aria-hidden="true"></span></button>' +
                '<button type="button" data-rich-align="right" title="Медіа праворуч"><span class="mdi mdi-format-float-right" aria-hidden="true"></span></button>' +
                '<button type="button" data-rich-align="wide" title="Медіа на всю ширину"><span class="mdi mdi-arrow-expand-horizontal" aria-hidden="true"></span></button>' +
                '<span class="rich-editor-divider" aria-hidden="true"></span>' +
                '<button type="button" data-rich-link title="Посилання"><span class="mdi mdi-link-variant" aria-hidden="true"></span></button>' +
                '<button type="button" data-rich-command="removeFormat" title="Очистити формат"><span class="mdi mdi-format-clear" aria-hidden="true"></span></button>' +
                '<button type="button" data-rich-source-toggle title="Джерело HTML"><span class="mdi mdi-code-tags" aria-hidden="true"></span></button>' +
            '</div>' +
            '<div class="rich-editor-media-panel" data-rich-media-panel hidden>' +
                '<strong>Медіа</strong>' +
                '<div class="rich-editor-media-controls">' +
                    '<button type="button" data-rich-panel-align="left" title="Ліворуч"><span class="mdi mdi-format-float-left" aria-hidden="true"></span></button>' +
                    '<button type="button" data-rich-panel-align="center" title="По центру"><span class="mdi mdi-format-float-center" aria-hidden="true"></span></button>' +
                    '<button type="button" data-rich-panel-align="right" title="Праворуч"><span class="mdi mdi-format-float-right" aria-hidden="true"></span></button>' +
                    '<button type="button" data-rich-panel-align="wide" title="На всю ширину"><span class="mdi mdi-arrow-expand-horizontal" aria-hidden="true"></span></button>' +
                    '<label>Розмір<select data-rich-media-size title="Розмір медіа">' +
                        '<option value="auto">Авто</option>' +
                        '<option value="small">Малий</option>' +
                        '<option value="medium">Середній</option>' +
                        '<option value="large">Великий</option>' +
                        '<option value="full">100%</option>' +
                    '</select></label>' +
                '</div>' +
            '</div>' +
            '<div class="rich-editor-area" contenteditable="true"></div>' +
            '<textarea class="rich-editor-code" spellcheck="false" aria-label="HTML джерело"></textarea>';

        const area = editor.querySelector('.rich-editor-area');
        const code = editor.querySelector('.rich-editor-code');
        area.innerHTML = textarea.value.trim() === '' ? '' : textarea.value;
        code.value = textarea.value;
        textarea.insertAdjacentElement('afterend', editor);

        editor.querySelectorAll('[data-rich-command]').forEach(function (control) {
            const eventName = control.tagName === 'SELECT' ? 'change' : 'click';
            control.addEventListener(eventName, function () {
                runRichCommand(control.getAttribute('data-rich-command'), control.value || null, area, textarea);
            });
        });

        editor.querySelector('[data-rich-link]').addEventListener('click', function () {
            area.focus();
            const href = window.prompt('URL посилання');
            if (href) {
                document.execCommand('createLink', false, href);
                syncRichEditor(textarea);
            }
        });

        editor.querySelector('[data-rich-media-open]').addEventListener('click', function () {
            openRichMediaModal(area, textarea);
        });

        editor.querySelectorAll('[data-rich-align]').forEach(function (control) {
            control.addEventListener('click', function () {
                setSelectedMediaAlign(area, control.getAttribute('data-rich-align'));
                updateRichMediaPanel(editor, area);
                syncRichEditor(textarea);
            });
        });

        editor.querySelectorAll('[data-rich-panel-align]').forEach(function (control) {
            control.addEventListener('click', function () {
                setSelectedMediaAlign(area, control.getAttribute('data-rich-panel-align'));
                updateRichMediaPanel(editor, area);
                syncRichEditor(textarea);
            });
        });

        editor.querySelector('[data-rich-media-size]').addEventListener('change', function () {
            setSelectedMediaSize(area, this.value);
            updateRichMediaPanel(editor, area);
            syncRichEditor(textarea);
        });

        editor.querySelector('[data-rich-source-toggle]').addEventListener('click', function () {
            toggleRichSourceMode(editor, textarea);
        });

        area.addEventListener('input', function () {
            syncRichEditor(textarea);
        });
        area.addEventListener('click', function (event) {
            const media = event.target.closest('.rich-media-block, .rich-gallery, img, a');
            area.querySelectorAll('.rich-media-selected').forEach(function (node) {
                node.classList.remove('rich-media-selected');
            });
            const block = richMediaBlock(media);
            if (block) {
                block.classList.add('rich-media-selected');
            }
            updateRichMediaPanel(editor, area);
        });
        code.addEventListener('input', function () {
            textarea.value = code.value;
        });
        syncRichEditor(textarea);
    });
}

function runRichCommand(command, value, area, textarea) {
    area.focus();
    document.execCommand(command, false, value);
    syncRichEditor(textarea);
}

function toggleRichSourceMode(editor, textarea) {
    const area = editor.querySelector('.rich-editor-area');
    const code = editor.querySelector('.rich-editor-code');
    const button = editor.querySelector('[data-rich-source-toggle]');
    const sourceMode = !editor.classList.contains('is-source-mode');

    if (sourceMode) {
        code.value = area.innerHTML.trim();
    } else {
        area.innerHTML = code.value;
    }
    textarea.value = sourceMode ? code.value : area.innerHTML.trim();
    editor.classList.toggle('is-source-mode', sourceMode);
    button.classList.toggle('is-active', sourceMode);
    updateRichMediaPanel(editor, area);
    (sourceMode ? code : area).focus();
}

const richMediaState = {
    modal: null,
    area: null,
    textarea: null,
    savedRange: null,
    items: [],
    selected: new Set(),
    selectedItems: new Map(),
    loading: false,
    searchTimer: null
};

function openRichMediaModal(area, textarea) {
    const modalNode = document.getElementById('richMediaModal');
    if (!modalNode || !window.bootstrap) {
        return;
    }

    richMediaState.area = area;
    richMediaState.textarea = textarea;
    richMediaState.savedRange = currentEditorRange(area);
    richMediaState.selected = new Set();
    richMediaState.selectedItems = new Map();
    richMediaState.modal = richMediaState.modal || new window.bootstrap.Modal(modalNode);
    modalNode.querySelector('[data-rich-media-caption]').value = '';
    modalNode.querySelector('[data-rich-media-mode]').value = 'single';
    modalNode.querySelector('[data-rich-media-align]').value = 'center';
    bindRichMediaModal(modalNode);
    updateRichMediaSelectionPreview();
    richMediaState.modal.show();
    loadRichMediaItems();
}

function bindRichMediaModal(modalNode) {
    if (modalNode.dataset.richMediaBound === '1') {
        return;
    }
    modalNode.dataset.richMediaBound = '1';

    modalNode.querySelector('[data-rich-media-search]').addEventListener('input', function () {
        window.clearTimeout(richMediaState.searchTimer);
        richMediaState.searchTimer = window.setTimeout(loadRichMediaItems, 250);
    });
    modalNode.querySelector('[data-rich-media-mode]').addEventListener('change', function () {
        if (this.value === 'single' && richMediaState.selected.size > 1) {
            const first = Array.from(richMediaState.selected)[0];
            richMediaState.selected = new Set([first]);
            richMediaState.selectedItems = new Map(richMediaState.selectedItems.has(first) ? [[first, richMediaState.selectedItems.get(first)]] : []);
        }
        if (this.value === 'gallery') {
            Array.from(richMediaState.selected).forEach(function (path) {
                const item = richMediaState.selectedItems.get(path);
                if (!item || !item.is_image) {
                    richMediaState.selected.delete(path);
                    richMediaState.selectedItems.delete(path);
                }
            });
        }
        renderRichMediaItems();
    });
    modalNode.querySelector('[data-rich-media-align]').addEventListener('change', updateRichMediaSelectionPreview);
    modalNode.querySelector('[data-rich-media-columns]').addEventListener('change', updateRichMediaSelectionPreview);
    modalNode.querySelector('[data-rich-media-caption]').addEventListener('input', updateRichMediaSelectionPreview);
    modalNode.querySelector('[data-rich-media-clear]').addEventListener('click', function () {
        richMediaState.selected = new Set();
        richMediaState.selectedItems = new Map();
        renderRichMediaItems();
    });
    modalNode.querySelector('[data-rich-media-insert]').addEventListener('click', insertSelectedRichMedia);
    modalNode.querySelector('[data-rich-media-upload]').addEventListener('change', uploadRichMediaFile);
}

async function loadRichMediaItems() {
    const modalNode = document.getElementById('richMediaModal');
    const grid = modalNode.querySelector('[data-rich-media-grid]');
    const status = modalNode.querySelector('[data-rich-media-status]');
    const search = modalNode.querySelector('[data-rich-media-search]').value.trim();
    const url = new URL(adminBody ? (adminBody.dataset.richMediaPickerUrl || '/admin/media/picker') : '/admin/media/picker', window.location.origin);
    url.searchParams.set('limit', '80');
    if (search !== '') {
        url.searchParams.set('q', search);
    }

    richMediaState.loading = true;
    status.textContent = 'Завантаження...';
    grid.innerHTML = '';
    updateRichMediaSelectionPreview();

    try {
        const response = await fetch(url.toString(), {headers: {'X-Requested-With': 'XMLHttpRequest'}});
        const data = await response.json();
        if (!response.ok || !data.ok) {
            throw new Error(data.message || 'Не вдалося завантажити медіафайли.');
        }
        richMediaState.items = data.items || [];
        richMediaState.items.forEach(function (item) {
            if (richMediaState.selected.has(item.path)) {
                richMediaState.selectedItems.set(item.path, item);
            }
        });
        renderRichMediaItems();
        status.textContent = richMediaState.items.length ? 'Знайдено файлів: ' + data.total + '.' : 'Файлів не знайдено.';
    } catch (error) {
        status.textContent = error.message || 'Помилка завантаження.';
    } finally {
        richMediaState.loading = false;
    }
}

function renderRichMediaItems() {
    const modalNode = document.getElementById('richMediaModal');
    const grid = modalNode.querySelector('[data-rich-media-grid]');
    grid.innerHTML = '';

    richMediaState.items.forEach(function (item) {
        const selectedIndex = Array.from(richMediaState.selected).indexOf(item.path);
        const card = document.createElement('button');
        card.type = 'button';
        card.className = 'rich-media-card' + (richMediaState.selected.has(item.path) ? ' is-selected' : '');
        card.innerHTML = (item.is_image
            ? '<img src="' + escapeHtml(item.url) + '" alt="">'
            : '<span class="mdi mdi-file-outline rich-media-file-icon" aria-hidden="true"></span>') +
            (selectedIndex >= 0 ? '<span class="rich-media-card-check">' + (selectedIndex + 1) + '</span>' : '') +
            '<span class="rich-media-card-name">' + escapeHtml(item.name) + '</span>' +
            '<small>' + escapeHtml(item.type) + ' · ' + escapeHtml(item.size_label) + '</small>';
        card.addEventListener('click', function () {
            const currentMode = modalNode.querySelector('[data-rich-media-mode]').value;
            if (currentMode === 'single') {
                richMediaState.selected = new Set([item.path]);
                richMediaState.selectedItems = new Map([[item.path, item]]);
            } else {
                if (!item.is_image) {
                    return;
                }
                if (richMediaState.selected.has(item.path)) {
                    richMediaState.selected.delete(item.path);
                    richMediaState.selectedItems.delete(item.path);
                } else {
                    richMediaState.selected.add(item.path);
                    richMediaState.selectedItems.set(item.path, item);
                }
            }
            renderRichMediaItems();
        });
        grid.appendChild(card);
    });
    updateRichMediaSelectionPreview();
}

function selectedRichMediaItems() {
    return Array.from(richMediaState.selected).map(function (path) {
        return richMediaState.selectedItems.get(path);
    }).filter(Boolean);
}

function updateRichMediaSelectionPreview() {
    const modalNode = document.getElementById('richMediaModal');
    if (!modalNode) {
        return;
    }

    const selected = selectedRichMediaItems();
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

    count.textContent = selected.length
        ? 'Вибрано: ' + selected.length
        : 'Нічого не вибрано';
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
        if (!galleryItems.length) {
            preview.innerHTML = '<div class="rich-media-preview-empty">Для галереї потрібні зображення.</div>';
            return;
        }
        preview.innerHTML = '<div class="rich-media-preview-frame">' +
            buildRichGalleryHtml(galleryItems, align, columns, caption) +
        '</div>';
        return;
    }

    preview.innerHTML = '<div class="rich-media-preview-frame">' +
        buildRichMediaHtml(selected[0], align, caption) +
    '</div>';
}

async function uploadRichMediaFile(event) {
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
        richMediaState.selected = new Set(data.uploaded_path ? [data.uploaded_path] : []);
        input.value = '';
        await loadRichMediaItems();
        status.textContent = 'Файл завантажено.';
    } catch (error) {
        status.textContent = error.message || 'Помилка завантаження.';
    }
}

function insertSelectedRichMedia() {
    const modalNode = document.getElementById('richMediaModal');
    const selected = selectedRichMediaItems();
    if (!selected.length || !richMediaState.area || !richMediaState.textarea) {
        modalNode.querySelector('[data-rich-media-status]').textContent = 'Оберіть файл для вставки.';
        return;
    }

    const mode = modalNode.querySelector('[data-rich-media-mode]').value;
    const align = modalNode.querySelector('[data-rich-media-align]').value;
    const columns = modalNode.querySelector('[data-rich-media-columns]').value;
    const caption = modalNode.querySelector('[data-rich-media-caption]').value.trim();
    const html = mode === 'gallery'
        ? buildRichGalleryHtml(selected.filter(function (item) { return item.is_image; }), align, columns, caption)
        : buildRichMediaHtml(selected[0], align, caption);
    if (html === '') {
        modalNode.querySelector('[data-rich-media-status]').textContent = 'Для галереї оберіть зображення.';
        return;
    }

    insertHtmlIntoRichEditor(richMediaState.area, html);
    syncRichEditor(richMediaState.textarea);
    richMediaState.modal.hide();
}

function currentEditorRange(area) {
    const selection = window.getSelection();
    if (!selection || !selection.rangeCount) {
        return null;
    }

    const range = selection.getRangeAt(0);
    return rangeBelongsToArea(range, area) ? range.cloneRange() : null;
}

function rangeBelongsToArea(range, area) {
    if (!range || !area) {
        return false;
    }

    const container = range.commonAncestorContainer.nodeType === 1
        ? range.commonAncestorContainer
        : range.commonAncestorContainer.parentElement;
    return container === area || area.contains(container);
}

function insertHtmlIntoRichEditor(area, html) {
    area.focus();

    const selection = window.getSelection();
    let range = richMediaState.savedRange && rangeBelongsToArea(richMediaState.savedRange, area)
        ? richMediaState.savedRange
        : currentEditorRange(area);

    if (!range) {
        range = document.createRange();
        range.selectNodeContents(area);
        range.collapse(false);
    }

    const template = document.createElement('template');
    template.innerHTML = html;
    const fragment = template.content.cloneNode(true);
    const lastNode = fragment.lastChild;

    range.deleteContents();
    range.insertNode(fragment);

    if (lastNode && selection) {
        const nextRange = document.createRange();
        nextRange.setStartAfter(lastNode);
        nextRange.collapse(true);
        selection.removeAllRanges();
        selection.addRange(nextRange);
        richMediaState.savedRange = nextRange.cloneRange();
    }
}

function buildRichMediaHtml(item, align, caption) {
    if (item.is_image) {
        return '<figure class="rich-media-block media-align-' + escapeHtml(align) + '"><img src="' + escapeHtml(item.url) + '" alt="' + escapeHtml(caption || item.name) + '">' +
            (caption ? '<figcaption>' + escapeHtml(caption) + '</figcaption>' : '') + '</figure>';
    }

    return '<p class="rich-file-link media-align-' + escapeHtml(align) + '"><a href="' + escapeHtml(item.url) + '" target="_blank" rel="noopener">' +
        escapeHtml(caption || item.name) + '</a></p>';
}

function buildRichGalleryHtml(items, align, columns, caption) {
    if (!items.length) {
        return '';
    }

    const images = items.map(function (item) {
        return '<figure><img src="' + escapeHtml(item.url) + '" alt="' + escapeHtml(item.name) + '"></figure>';
    }).join('');

    return '<div class="rich-gallery rich-gallery-cols-' + escapeHtml(columns) + ' media-align-' + escapeHtml(align) + '">' + images + '</div>' +
        (caption ? '<p class="rich-gallery-caption media-align-' + escapeHtml(align) + '">' + escapeHtml(caption) + '</p>' : '');
}

function setSelectedMediaAlign(area, align) {
    const selection = window.getSelection();
    let node = area.querySelector('.rich-media-selected');
    if (!node && selection && selection.rangeCount) {
        const anchor = selection.anchorNode;
        node = anchor ? richMediaBlock(anchor.nodeType === 1 ? anchor : anchor.parentElement) : null;
    }
    node = richMediaBlock(node);
    if (!node) {
        return;
    }

    ['media-align-left', 'media-align-center', 'media-align-right', 'media-align-wide'].forEach(function (className) {
        node.classList.remove(className);
    });
    node.classList.add('media-align-' + align);
}

function setSelectedMediaSize(area, size) {
    const node = area.querySelector('.rich-media-selected');
    if (!node) {
        return;
    }

    ['media-size-small', 'media-size-medium', 'media-size-large', 'media-size-full'].forEach(function (className) {
        node.classList.remove(className);
    });
    if (['small', 'medium', 'large', 'full'].includes(size)) {
        node.classList.add('media-size-' + size);
    }
}

function selectedMediaAlign(node) {
    if (!node) {
        return '';
    }
    if (node.classList.contains('media-align-left')) return 'left';
    if (node.classList.contains('media-align-right')) return 'right';
    if (node.classList.contains('media-align-wide')) return 'wide';
    if (node.classList.contains('media-align-center')) return 'center';
    return '';
}

function selectedMediaSize(node) {
    if (!node) {
        return 'auto';
    }
    if (node.classList.contains('media-size-small')) return 'small';
    if (node.classList.contains('media-size-medium')) return 'medium';
    if (node.classList.contains('media-size-large')) return 'large';
    if (node.classList.contains('media-size-full')) return 'full';
    return 'auto';
}

function updateRichMediaPanel(editor, area) {
    const panel = editor.querySelector('[data-rich-media-panel]');
    if (!panel) {
        return;
    }

    const selected = area.querySelector('.rich-media-selected');
    panel.hidden = !selected || editor.classList.contains('is-source-mode');
    if (panel.hidden) {
        return;
    }

    const align = selectedMediaAlign(selected);
    panel.querySelectorAll('[data-rich-panel-align]').forEach(function (button) {
        button.classList.toggle('is-active', button.getAttribute('data-rich-panel-align') === align);
    });

    const size = panel.querySelector('[data-rich-media-size]');
    if (size) {
        size.value = selectedMediaSize(selected);
    }
}

function richMediaBlock(node) {
    if (!node) {
        return null;
    }
    const element = node.nodeType === 1 ? node : node.parentElement;
    if (!element) {
        return null;
    }
    const galleryCaption = element.closest('.rich-gallery-caption');
    if (galleryCaption && galleryCaption.previousElementSibling && galleryCaption.previousElementSibling.classList.contains('rich-gallery')) {
        return galleryCaption.previousElementSibling;
    }
    return element.closest('.rich-media-block, .rich-gallery, .rich-gallery-caption, .rich-file-link') || (element.tagName === 'IMG' ? element.closest('figure') : null);
}

function syncRichEditors(root) {
    (root || document).querySelectorAll('textarea[data-rich-editor]').forEach(syncRichEditor);
}

function syncRichEditor(textarea) {
    const editor = textarea.nextElementSibling;
    const area = editor ? editor.querySelector('.rich-editor-area') : null;
    const code = editor ? editor.querySelector('.rich-editor-code') : null;
    if (editor && editor.classList.contains('is-source-mode') && code) {
        textarea.value = code.value;
        return;
    }
    if (area) {
        textarea.value = area.innerHTML.trim();
        if (code) {
            code.value = textarea.value;
        }
    }
}


    window.RichEditor = {
        init: initRichEditors,
        syncAll: syncRichEditors,
        syncOne: syncRichEditor
    };
})();
