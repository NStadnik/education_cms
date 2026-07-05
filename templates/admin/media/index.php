<div class="page-head">
    <div>
        <p class="eyebrow">Файлове сховище</p>
        <h1>Медіафайли</h1>
        <p class="page-subtitle">Керуйте всіма файлами, завантаженими у сховище сайту.</p>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert"><?= e($error) ?></div>
<?php endif; ?>
<div class="alert d-none" data-media-message></div>

<div class="metrics">
    <div class="metric"><div><span>Усього</span><strong data-media-stat="total"><?= e((string) $stats['total']) ?></strong></div><span class="mdi mdi-folder-multiple-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Зображень</span><strong data-media-stat="images"><?= e((string) $stats['images']) ?></strong></div><span class="mdi mdi-image-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Вільні</span><strong data-media-stat="unused"><?= e((string) $stats['unused']) ?></strong></div><span class="mdi mdi-trash-can-outline metric-icon" aria-hidden="true"></span></div>
</div>

<section class="card admin-form-card">
    <div class="form-section-head">
        <div>
            <h2>Завантажити файл</h2>
            <p class="meta">Підтримуються PDF, Word, Excel і зображення JPG, PNG, WEBP. PHP-ліміт: <?= e($uploadLimitLabel ?? 'не визначено') ?>.</p>
        </div>
    </div>
    <form class="form-grid wide" method="post" action="<?= url('/admin/media/upload') ?>" enctype="multipart/form-data" data-media-upload data-upload-limit="<?= e((string) ($uploadLimitBytes ?? 0)) ?>">
        <?= \App\Core\Csrf::field() ?>
        <?php if (!empty($uploadLimitBytes)): ?><input type="hidden" name="MAX_FILE_SIZE" value="<?= e((string) $uploadLimitBytes) ?>"><?php endif; ?>
        <label>Файл<input type="file" name="file" required></label>
        <div class="form-actions">
            <button type="submit"><span class="mdi mdi-upload-outline" aria-hidden="true"></span><span>Завантажити</span></button>
        </div>
    </form>
</section>

<div class="list-panel" data-infinite-list data-list-url="<?= url('/admin/media') ?>" data-list-target="#mediaRows" data-list-offset="<?= e((string) count($items)) ?>" data-list-limit="<?= e((string) $limit) ?>" data-list-has-more="<?= count($items) < $total ? '1' : '0' ?>">
    <div class="list-tools">
        <input data-filter-input type="search" value="<?= e($query ?? '') ?>" placeholder="Пошук файлів" aria-label="Пошук медіафайлів">
        <span class="meta"><span data-filter-count><?= e((string) $total) ?></span> файлів · <span data-media-stat="size"><?= e($stats['size']) ?></span></span>
    </div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>Файл</th><th>Тип</th><th>Розмір</th><th>Оновлено</th><th>Використання</th><th></th></tr></thead>
            <tbody id="mediaRows"><?= $this->partial('admin/media/rows', ['items' => $items]) ?></tbody>
        </table>
    </div>
    <div class="empty-state <?= $items ? 'd-none' : '' ?>" data-list-empty>Файли не знайдені.</div>
    <div class="list-sentinel" data-list-sentinel></div>
    <p class="meta list-status" data-list-status><?= count($items) < $total ? 'Прокрутіть нижче, щоб завантажити ще.' : 'Усі файли завантажено.' ?></p>
</div>

<div class="modal fade" id="mediaPreviewModal" tabindex="-1" aria-labelledby="mediaPreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5" id="mediaPreviewTitle">Попередній перегляд</h2>
                    <p class="meta mb-0" data-media-preview-path></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
            </div>
            <div class="modal-body">
                <div class="media-preview-frame" data-media-preview-body></div>
            </div>
            <div class="modal-footer">
                <a class="button secondary" href="#" target="_blank" rel="noopener" data-media-preview-open>
                    <span class="mdi mdi-open-in-new" aria-hidden="true"></span><span>Відкрити файл</span>
                </a>
                <button type="button" class="button secondary" data-bs-dismiss="modal">
                    <span class="mdi mdi-close" aria-hidden="true"></span><span>Закрити</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const mediaUploadLimitLabel = <?= json_encode((string) ($uploadLimitLabel ?? ''), JSON_UNESCAPED_UNICODE) ?>;
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
</script>
