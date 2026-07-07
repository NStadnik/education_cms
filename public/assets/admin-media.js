const mediaUploadLimitLabel = document.querySelector('[data-media-upload]')?.dataset.uploadLimitLabel || '';
const mediaPreviewModalNode = document.getElementById('mediaPreviewModal');
const mediaMetadataModalNode = document.getElementById('mediaMetadataModal');
let mediaPreviewModal = null;
let mediaMetadataModal = null;

document.addEventListener('click', function (event) {
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
    const extension = (button.getAttribute('data-extension') || '').toLowerCase();
    const isImage = button.getAttribute('data-is-image') === '1';
    const altText = button.getAttribute('data-alt-text') || '';
    const caption = button.getAttribute('data-caption') || '';
    const description = button.getAttribute('data-description') || '';
    const title = mediaPreviewModalNode.querySelector('#mediaPreviewTitle');
    const pathNode = mediaPreviewModalNode.querySelector('[data-media-preview-path]');
    const body = mediaPreviewModalNode.querySelector('[data-media-preview-body]');
    const openLink = mediaPreviewModalNode.querySelector('[data-media-preview-open]');

    title.textContent = mediaTitle || name;
    pathNode.textContent = path;
    openLink.href = url;

    if (isImage) {
        body.innerHTML =
            '<img class="media-preview-image" src="' + escapeHtml(url) + '" alt="' + escapeHtml(altText) + '">' +
            mediaPreviewMeta(caption, description);
    } else if (extension === 'pdf') {
        body.innerHTML =
            '<iframe class="media-preview-pdf" src="' + escapeHtml(url) + '" title="' + escapeHtml(name) + '"></iframe>' +
            mediaPreviewMeta(caption, description);
    } else {
        body.innerHTML = '<div class="empty-state media-preview-empty"><span class="mdi mdi-file-eye-outline" aria-hidden="true"></span><p>Попередній перегляд для цього типу файлу недоступний у браузері.</p></div>';
    }

    mediaPreviewModal.show();
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

function mediaPreviewMeta(caption, description) {
    if (!caption && !description) {
        return '';
    }

    return '<div class="media-preview-meta">' +
        (caption ? '<strong>' + escapeHtml(caption) + '</strong>' : '') +
        (description ? '<p>' + escapeHtml(description) + '</p>' : '') +
    '</div>';
}

function setMediaMessage(message, isError) {
    const node = document.querySelector('[data-media-message]');
    node.className = isError ? 'alert mt-3 mb-3' : 'alert alert-success mt-3 mb-3';
    node.textContent = message;
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function (char) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
    });
}
