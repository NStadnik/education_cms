const mediaUploadLimitLabel = document.querySelector('[data-media-upload]')?.dataset.uploadLimitLabel || '';
const mediaPreviewModalNode = document.getElementById('mediaPreviewModal');
const mediaMetadataModalNode = document.getElementById('mediaMetadataModal');
let mediaPreviewModal = null;
let mediaMetadataModal = null;

initMediaViewMode();
initMediaTypeViewMode();
initMediaUploadField();
initMediaRowsObserver();
updateMediaSelectedCount();

document.addEventListener('click', function (event) {
    const viewButton = event.target.closest('[data-media-view]');
    if (viewButton) {
        setMediaViewMode(viewButton.dataset.mediaView || 'list', true);
        return;
    }

    const metadataButton = event.target.closest('[data-media-metadata]');
    if (metadataButton && mediaMetadataModalNode) {
        if (!window.bootstrap || !window.bootstrap.Modal) {
            setMediaMessage('Компонент редагування ще завантажується. Спробуйте ще раз.', true);
            return;
        }
        openMediaMetadataModal(metadataButton);
        return;
    }

    const button = event.target.closest('[data-media-preview]');
    if (!button || !mediaPreviewModalNode) {
        return;
    }
    if (!window.bootstrap || !window.bootstrap.Modal) {
        setMediaMessage('Компонент попереднього перегляду ще завантажується. Спробуйте ще раз.', true);
        return;
    }
    mediaPreviewModal = mediaPreviewModal || new window.bootstrap.Modal(mediaPreviewModalNode);

    const url = button.getAttribute('data-url') || '';
    const name = button.getAttribute('data-name') || 'Файл';
    const mediaTitle = button.getAttribute('data-title') || '';
    const path = button.getAttribute('data-path') || '';
    const type = button.getAttribute('data-type') || '';
    const size = button.getAttribute('data-size') || '';
    const modified = button.getAttribute('data-modified') || '';
    const folder = button.getAttribute('data-folder') || '';
    const extension = (button.getAttribute('data-extension') || '').toLowerCase();
    const isImage = button.getAttribute('data-is-image') === '1';
    const isUsed = button.getAttribute('data-is-used') === '1';
    const referenceLabel = button.getAttribute('data-reference-label') || '';
    const referenceUrl = button.getAttribute('data-reference-url') || '';
    const altText = button.getAttribute('data-alt-text') || '';
    const caption = button.getAttribute('data-caption') || '';
    const description = button.getAttribute('data-description') || '';
    const title = mediaPreviewModalNode.querySelector('#mediaPreviewTitle');
    const pathNode = mediaPreviewModalNode.querySelector('[data-media-preview-path]');
    const body = mediaPreviewModalNode.querySelector('[data-media-preview-body]');
    const details = mediaPreviewModalNode.querySelector('[data-media-preview-details]');
    const openLink = mediaPreviewModalNode.querySelector('[data-media-preview-open]');

    title.textContent = mediaTitle || name;
    pathNode.textContent = path;
    openLink.href = url;

    if (isImage) {
        body.innerHTML = '<img class="media-preview-image" src="' + escapeHtml(url) + '" alt="' + escapeHtml(altText) + '">';
    } else if (extension === 'pdf') {
        body.innerHTML = '<iframe class="media-preview-pdf" src="' + escapeHtml(url) + '" title="' + escapeHtml(name) + '"></iframe>';
    } else {
        body.innerHTML = '<div class="empty-state media-preview-empty"><span class="mdi mdi-file-eye-outline" aria-hidden="true"></span><p>Попередній перегляд для цього типу файлу недоступний у браузері.</p></div>';
    }
    if (details) {
        details.innerHTML = mediaPreviewDetails({
            name: name,
            type: type,
            size: size,
            folder: folder,
            modified: modified,
            isUsed: isUsed,
            referenceLabel: referenceLabel,
            referenceUrl: referenceUrl,
            altText: altText,
            caption: caption,
            description: description
        });
    }

    mediaPreviewModal.show();
});

document.addEventListener('change', function (event) {
    const fileInput = event.target.closest('[data-media-file-input]');
    if (fileInput) {
        updateMediaFileLabel(fileInput);
        return;
    }

    if (event.target.closest('[data-bulk-check], [data-bulk-check-all]')) {
        window.setTimeout(updateMediaSelectedCount, 0);
    }
});

document.addEventListener('submit', async function (event) {
    const form = event.target.closest('form[data-media-upload], form[data-media-delete], form[data-media-metadata-form]');
    if (!form || event.defaultPrevented) {
        return;
    }

    event.preventDefault();

    if (form.matches('[data-media-delete]') && !confirm('Видалити цей файл?')) {
        return;
    }

    if (form.matches('[data-media-upload]')) {
        const limit = Number(form.getAttribute('data-upload-limit') || '0');
        const input = form.querySelector('input[type="file"]');
        if (limit && input && input.files && input.files[0] && input.files[0].size > limit) {
            alert('Файл завеликий для поточного PHP-ліміту: ' + mediaUploadLimitLabel + '.');
            return;
        }
    }

    const panel = document.querySelector('[data-infinite-list]');
    const target = document.querySelector(panel.getAttribute('data-list-target'));
    const input = panel.querySelector('[data-filter-input]');
    const folderFilter = panel.querySelector('[data-media-folder-filter]');
    const typeFilter = panel.querySelector('[data-media-type-filter]');
    const button = form.querySelector('button[type="submit"]');
    const originalHtml = button ? button.innerHTML : '';
    const body = new FormData(form);
    if (input && input.value.trim() !== '') {
        body.set('q', input.value.trim());
    }
    if (folderFilter) {
        body.set('current_folder', folderFilter.value);
        if (form.matches('[data-media-delete]')) {
            body.set('folder', folderFilter.value);
        }
    }
    if (typeFilter) {
        body.set('file_type', typeFilter.value);
    }

    if (button) {
        button.disabled = true;
        button.innerHTML = '<span class="mdi mdi-loading mdi-spin" aria-hidden="true"></span><span>Зачекайте...</span>';
    }

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: body,
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        });
        const data = await response.json();
        if (!response.ok || !data.ok) {
            throw new Error(data.message || 'Не вдалося виконати дію.');
        }

        target.innerHTML = data.html || '';
        updateMediaList(panel, data);

        if (form.matches('[data-media-upload]')) {
            form.reset();
            const fileInput = form.querySelector('[data-media-file-input]');
            if (fileInput) {
                updateMediaFileLabel(fileInput);
            }
        }
        if (form.matches('[data-media-metadata-form]') && mediaMetadataModal) {
            mediaMetadataModal.hide();
        }
        setMediaMessage(data.message || 'Готово.', false);
    } catch (error) {
        setMediaMessage(error.message || 'Помилка.', true);
    } finally {
        if (button) {
            button.disabled = false;
            button.innerHTML = originalHtml;
        }
    }
});

function openMediaMetadataModal(button) {
    mediaMetadataModal = mediaMetadataModal || new window.bootstrap.Modal(mediaMetadataModalNode);
    const form = mediaMetadataModalNode.querySelector('[data-media-metadata-form]');
    const panel = document.querySelector('[data-infinite-list]');
    const folderFilter = panel ? panel.querySelector('[data-media-folder-filter]') : null;
    const fields = ['path', 'folder', 'alt_text', 'title', 'caption', 'description'];

    fields.forEach(function (name) {
        const field = form.querySelector('[data-media-metadata-field="' + name + '"]');
        if (!field) {
            return;
        }
        const dataName = name.replace(/_/g, '-');
        field.value = button.getAttribute('data-' + dataName) || '';
    });
    const currentFolder = form.querySelector('[data-media-current-folder]');
    if (currentFolder && folderFilter) {
        currentFolder.value = folderFilter.value;
    }

    mediaMetadataModalNode.querySelector('#mediaMetadataTitle').textContent = button.getAttribute('data-title') || button.getAttribute('data-name') || 'Метадані файлу';
    mediaMetadataModalNode.querySelector('[data-media-metadata-path]').textContent = button.getAttribute('data-path') || '';
    renderMetadataPreview(button);
    mediaMetadataModal.show();
}

function renderMetadataPreview(button) {
    const preview = mediaMetadataModalNode.querySelector('[data-media-metadata-preview]');
    const url = button.getAttribute('data-url') || '';
    const name = button.getAttribute('data-name') || '';
    const isImage = button.getAttribute('data-is-image') === '1';
    if (isImage) {
        preview.innerHTML = '<img src="' + escapeHtml(url) + '" alt="">';
        return;
    }

    preview.innerHTML =
        '<span class="mdi mdi-file-outline" aria-hidden="true"></span>' +
        '<strong>' + escapeHtml(name) + '</strong>';
}

function updateMediaList(panel, data) {
    panel.setAttribute('data-list-offset', String(data.next_offset || 0));
    panel.setAttribute('data-list-has-more', data.has_more ? '1' : '0');
    panel.querySelector('[data-filter-count]').textContent = String(data.total || 0);
    panel.querySelector('[data-list-empty]').classList.toggle('d-none', panel.querySelector('[data-list-row]') !== null);
    panel.querySelector('[data-list-status]').textContent = data.has_more ? 'Прокрутіть нижче, щоб завантажити ще.' : 'Усі файли завантажено.';

    if (data.stats) {
        document.querySelector('[data-media-stat="total"]').textContent = String(data.stats.total || 0);
        document.querySelector('[data-media-stat="images"]').textContent = String(data.stats.images || 0);
        document.querySelector('[data-media-stat="unused"]').textContent = String(data.stats.unused || 0);
        document.querySelector('[data-media-stat="size"]').textContent = String(data.stats.size || '');
    }
    if (Array.isArray(data.folders)) {
        updateFolderFilters(data.folders);
    }
    updateMediaSelectedCount();
    panel.dispatchEvent(new CustomEvent('admin:check-list'));
}

function updateFolderFilters(folders) {
    const selects = document.querySelectorAll('[data-media-folder-filter]');
    selects.forEach(function (select) {
        const current = select.value;
        select.innerHTML = '<option value="">Усі папки</option><option value="__none">Без папки</option>' +
            folders.map(function (folder) {
                return '<option value="' + escapeHtml(folder) + '">' + escapeHtml(folder) + '</option>';
            }).join('');
        select.value = current;
    });

    const datalist = document.getElementById('mediaFolderOptions');
    if (datalist) {
        datalist.innerHTML = folders.map(function (folder) {
            return '<option value="' + escapeHtml(folder) + '"></option>';
        }).join('');
    }
}

function mediaPreviewDetails(item) {
    const rows = [
        ['Файл', item.name],
        ['Тип', item.type],
        ['Розмір', item.size],
        ['Папка', item.folder || 'Без папки'],
        ['Оновлено', item.modified],
        ['Стан', item.isUsed ? 'Використовується' : 'Вільний'],
        ['Alt-текст', item.altText],
        ['Підпис', item.caption],
        ['Опис', item.description]
    ];
    if (item.referenceLabel) {
        rows.splice(6, 0, ['Використання', item.referenceUrl
            ? '<a href="' + escapeHtml(item.referenceUrl) + '">' + escapeHtml(item.referenceLabel) + '</a>'
            : escapeHtml(item.referenceLabel), true]);
    }
    const html = rows.filter(function (row) { return String(row[1] || '').trim() !== ''; }).map(function (row) {
        return '<div class="media-preview-details-row"><dt>' + escapeHtml(row[0]) + '</dt><dd>' + (row[2] ? row[1] : escapeHtml(row[1])) + '</dd></div>';
    }).join('');
    return '<h3>Деталі</h3><dl>' + html + '</dl>';
}

function setMediaMessage(message, isError) {
    const node = document.querySelector('[data-media-message]');
    node.className = isError ? 'alert mt-3 mb-3' : 'alert alert-success mt-3 mb-3';
    node.textContent = message;
}

function initMediaViewMode() {
    const stored = window.localStorage ? (localStorage.getItem('adminMediaViewMode') || 'compact') : 'compact';
    const mode = stored === 'large' || stored === 'grid' ? 'large' : (stored === 'list' ? 'list' : 'compact');
    setMediaViewMode(mode, false);
}

function initMediaTypeViewMode() {
    const typeFilter = document.querySelector('[data-media-type-filter]');
    if (!typeFilter) {
        return;
    }

    syncMediaViewModeForType(typeFilter.value);
    typeFilter.addEventListener('change', function () {
        syncMediaViewModeForType(typeFilter.value);
    });
}

function syncMediaViewModeForType(fileType) {
    const panel = document.querySelector('[data-infinite-list]');
    if (!panel) {
        return;
    }

    const listOnly = ['pdf', 'word', 'excel', 'other'].includes(fileType);
    document.querySelectorAll('[data-media-view="compact"], [data-media-view="large"]').forEach(function (button) {
        button.hidden = listOnly;
    });

    if (listOnly) {
        panel.dataset.mediaForcedList = '1';
        setMediaViewMode('list', false);
        return;
    }

    if (panel.dataset.mediaForcedList === '1') {
        delete panel.dataset.mediaForcedList;
        const stored = window.localStorage ? (localStorage.getItem('adminMediaViewMode') || 'compact') : 'compact';
        const restored = stored === 'large' || stored === 'grid' ? 'large' : (stored === 'list' ? 'list' : 'compact');
        setMediaViewMode(restored, false);
    }
}

function initMediaUploadField() {
    document.querySelectorAll('[data-media-file-input]').forEach(function (input) {
        const label = input.closest('.media-file-drop');
        if (label) {
            label.addEventListener('dragover', function (event) {
                event.preventDefault();
                label.classList.add('is-dragover');
            });
            label.addEventListener('dragleave', function () {
                label.classList.remove('is-dragover');
            });
            label.addEventListener('drop', function (event) {
                const files = event.dataTransfer ? event.dataTransfer.files : null;
                if (!files || !files.length) {
                    return;
                }

                event.preventDefault();
                input.files = files;
                label.classList.remove('is-dragover');
                updateMediaFileLabel(input);
            });
        }
        updateMediaFileLabel(input);
    });
}

function initMediaRowsObserver() {
    const rows = document.getElementById('mediaRows');
    if (!rows || !window.MutationObserver) {
        return;
    }

    const observer = new MutationObserver(updateMediaSelectedCount);
    observer.observe(rows, {childList: true});
}

function updateMediaFileLabel(input) {
    const label = input.closest('.media-file-drop');
    const target = label ? label.querySelector('[data-media-file-label]') : null;
    const file = input.files && input.files[0] ? input.files[0] : null;
    if (!target) {
        return;
    }

    target.textContent = file ? file.name : 'Оберіть файл або перетягніть його сюди';
    if (label) {
        label.classList.toggle('has-file', Boolean(file));
    }
}

function updateMediaSelectedCount() {
    const node = document.querySelector('[data-media-selected-count]');
    const form = document.getElementById('mediaBulkForm');
    if (!node || !form) {
        return;
    }

    const selected = document.querySelectorAll('[data-bulk-check][form="' + form.id + '"]:checked').length;
    node.textContent = selected + ' вибрано';
    node.hidden = selected === 0;
    document.querySelectorAll('[data-list-row]').forEach(function (row) {
        const checkbox = row.querySelector('[data-bulk-check]');
        row.classList.toggle('is-selected', Boolean(checkbox && checkbox.checked));
    });
}

function setMediaViewMode(mode, persist) {
    const panel = document.querySelector('[data-infinite-list]');
    if (!panel) {
        return;
    }
    const normalized = ['compact', 'large', 'list'].includes(mode) ? mode : 'compact';
    panel.dataset.mediaViewMode = normalized;
    document.querySelectorAll('[data-media-view]').forEach(function (button) {
        const active = button.dataset.mediaView === normalized;
        button.classList.toggle('secondary', !active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    if (persist && window.localStorage) {
        localStorage.setItem('adminMediaViewMode', normalized);
    }
    panel.dispatchEvent(new CustomEvent('admin:check-list'));
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function (char) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
    });
}
