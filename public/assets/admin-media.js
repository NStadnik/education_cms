const mediaUploadLimitLabel = document.querySelector('[data-media-upload]')?.dataset.uploadLimitLabel || '';
const mediaPreviewModalNode = document.getElementById('mediaPreviewModal');
let mediaPreviewModal = null;

document.addEventListener('click', function (event) {
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
    const path = button.getAttribute('data-path') || '';
    const extension = (button.getAttribute('data-extension') || '').toLowerCase();
    const isImage = button.getAttribute('data-is-image') === '1';
    const title = mediaPreviewModalNode.querySelector('#mediaPreviewTitle');
    const pathNode = mediaPreviewModalNode.querySelector('[data-media-preview-path]');
    const body = mediaPreviewModalNode.querySelector('[data-media-preview-body]');
    const openLink = mediaPreviewModalNode.querySelector('[data-media-preview-open]');

    title.textContent = name;
    pathNode.textContent = path;
    openLink.href = url;

    if (isImage) {
        body.innerHTML = '<img class="media-preview-image" src="' + escapeHtml(url) + '" alt="">';
    } else if (extension === 'pdf') {
        body.innerHTML = '<iframe class="media-preview-pdf" src="' + escapeHtml(url) + '" title="' + escapeHtml(name) + '"></iframe>';
    } else {
        body.innerHTML = '<div class="empty-state media-preview-empty"><span class="mdi mdi-file-eye-outline" aria-hidden="true"></span><p>Попередній перегляд для цього типу файлу недоступний у браузері.</p></div>';
    }

    mediaPreviewModal.show();
});

document.addEventListener('submit', async function (event) {
    const form = event.target.closest('form[data-media-upload], form[data-media-delete]');
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
    const button = form.querySelector('button[type="submit"]');
    const originalHtml = button ? button.innerHTML : '';
    const body = new FormData(form);
    if (input && input.value.trim() !== '') {
        body.set('q', input.value.trim());
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
        panel.setAttribute('data-list-offset', String(data.next_offset || 0));
        panel.setAttribute('data-list-has-more', data.has_more ? '1' : '0');
        panel.querySelector('[data-filter-count]').textContent = String(data.total || 0);
        panel.querySelector('[data-list-empty]').classList.toggle('d-none', target.querySelector('[data-list-row]') !== null);
        panel.querySelector('[data-list-status]').textContent = data.has_more ? 'Прокрутіть нижче, щоб завантажити ще.' : 'Усі файли завантажено.';

        if (data.stats) {
            document.querySelector('[data-media-stat="total"]').textContent = String(data.stats.total || 0);
            document.querySelector('[data-media-stat="images"]').textContent = String(data.stats.images || 0);
            document.querySelector('[data-media-stat="unused"]').textContent = String(data.stats.unused || 0);
            document.querySelector('[data-media-stat="size"]').textContent = String(data.stats.size || '');
        }

        if (form.matches('[data-media-upload]')) {
            form.reset();
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
