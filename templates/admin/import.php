<div class="page-head">
    <div>
        <p class="eyebrow">Дані</p>
        <h1>Імпорт</h1>
        <p class="page-subtitle">Завантажте CSV/JSON або підключіться до WordPress бази даних, перегляньте записи й запустіть імпорт.</p>
    </div>
</div>

<div data-import-message></div>

<form class="form-grid wide" method="post" action="<?= url('/admin/import/run') ?>" enctype="multipart/form-data" data-no-ajax data-import-form data-preview-url="<?= url('/admin/import/preview') ?>">
    <?= \App\Core\Csrf::field() ?>

    <section class="card admin-form-card">
        <div class="form-section-head">
            <div>
                <h2>Джерело</h2>
                <p class="meta">Оберіть файл/текст або підключення до зовнішньої бази даних.</p>
            </div>
        </div>

        <div class="source-options">
            <label class="check-row"><input type="radio" name="source" value="file" checked data-import-source> CSV / JSON</label>
            <label class="check-row"><input type="radio" name="source" value="database" data-import-source> Підключення до БД</label>
        </div>
    </section>

    <section class="card admin-form-card" data-file-source>
        <div class="form-section-head">
            <div>
                <h2>Варіант імпорту</h2>
                <p class="meta">Для файлу або вставленого тексту оберіть, які записи потрібно створити.</p>
            </div>
        </div>
        <div class="import-options">
            <?php foreach ($importOptions as $key => $option): ?>
                <?php if ($key === 'wordpress') { continue; } ?>
                <label class="import-option">
                    <input type="radio" name="type" value="<?= e($key) ?>" <?= checked($key === 'news') ?>>
                    <span class="mdi mdi-database-import-outline import-option-icon" aria-hidden="true"></span>
                    <span>
                        <strong><?= e($option['name']) ?></strong>
                        <small><?= e($option['description']) ?></small>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card admin-form-card" data-db-source hidden>
        <input type="hidden" name="db_profile" value="wordpress">
        <div class="form-section-head">
            <div>
                <h2>WordPress база</h2>
                <p class="meta">Підключення читає таблицю <code>wp_posts</code>: дописи імпортуються як новини, сторінки як сторінки.</p>
            </div>
        </div>
        <div class="grid grid-3">
            <label>Host<input name="db_host" value="127.0.0.1" autocomplete="off"></label>
            <label>Port<input name="db_port" value="3306" autocomplete="off"></label>
            <label>Charset<input name="db_charset" value="utf8mb4" autocomplete="off"></label>
        </div>
        <div class="grid grid-3">
            <label>Назва БД<input name="db_name" autocomplete="off"></label>
            <label>Користувач<input name="db_user" autocomplete="off"></label>
            <label>Пароль<input type="password" name="db_password" autocomplete="new-password"></label>
        </div>
        <div class="grid grid-3">
            <label>Префікс таблиць<input name="db_prefix" value="wp_" autocomplete="off"></label>
            <label>Статус
                <select name="wp_status">
                    <option value="publish">Тільки опубліковані</option>
                    <option value="draft">Тільки чернетки</option>
                    <option value="any">Усі статуси</option>
                </select>
            </label>
            <label>Ліміт імпорту<input type="number" name="db_limit" value="200" min="1" max="1000"></label>
        </div>
        <div class="grid grid-3">
            <label>URL старого сайту<input name="wp_site_url" placeholder="https://example.com" autocomplete="off"></label>
            <label>Шлях до wp-content/uploads<input name="wp_uploads_path" placeholder="/home/user/site/wp-content/uploads" autocomplete="off"></label>
            <label>Ліміт файлів<input type="number" name="wp_media_limit" value="1000" min="1" max="5000"></label>
        </div>
        <label class="check-row"><input type="checkbox" name="wp_import_media" value="1" checked> Імпортувати файли та замінити старі адреси в публікаціях</label>
        <div class="hint-box">Якщо вказати локальний шлях до uploads, файли копіюються з диска. Якщо шлях порожній, система спробує завантажити їх за URL з WordPress.</div>
    </section>

    <div class="editor-layout" data-file-source>
        <section class="card admin-form-card">
            <div class="form-section-head">
                <div>
                    <h2>Джерело</h2>
                    <p class="meta">Перший рядок CSV має містити назви колонок. Підтримуються кома або крапка з комою.</p>
                </div>
            </div>

            <div class="form-grid wide">
                <label>Формат
                    <select name="format">
                        <option value="auto">Визначити автоматично</option>
                        <option value="csv">CSV</option>
                        <option value="json">JSON</option>
                    </select>
                </label>
                <label>Файл CSV або JSON<input type="file" name="import_file" accept=".csv,.json,text/csv,application/json"></label>
                <label>Або вставте дані вручну<textarea class="textarea-large" name="import_text" placeholder="title,body,status&#10;Перша новина,Текст новини,published"></textarea></label>
            </div>
        </section>

        <aside class="card admin-form-card editor-sidebar">
            <div class="form-section-head">
                <div>
                    <h2>Колонки</h2>
                    <p class="meta">Можна використовувати англійські або українські назви полів.</p>
                </div>
            </div>

            <div class="import-columns">
                <?php foreach ($importOptions as $option): ?>
                    <?php if (($option['name'] ?? '') === 'WordPress') { continue; } ?>
                    <div>
                        <strong><?= e($option['name']) ?></strong>
                        <code><?= e($option['columns']) ?></code>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="hint-box">
                Для документів імпортується лише картка без файлу. Файл можна додати пізніше у редагуванні документа.
            </div>

            <div class="form-actions stacked">
                <button type="button" class="button secondary" data-import-preview><span class="mdi mdi-eye-outline" aria-hidden="true"></span><span>Попередній перегляд</span></button>
                <button type="submit"><span class="mdi mdi-database-import-outline" aria-hidden="true"></span><span>Запустити імпорт</span></button>
            </div>
        </aside>
    </div>

    <section class="card admin-form-card" data-db-source hidden>
        <div class="form-actions">
            <button type="button" class="button secondary" data-import-preview><span class="mdi mdi-eye-outline" aria-hidden="true"></span><span>Попередній перегляд</span></button>
            <button type="submit"><span class="mdi mdi-database-import-outline" aria-hidden="true"></span><span>Імпортувати з WordPress</span></button>
        </div>
    </section>
</form>

<div class="modal fade" id="importPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5">Попередній перегляд імпорту</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
            </div>
            <div class="modal-body" data-import-preview-body>
                <div class="meta">Завантаження...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button secondary" data-bs-dismiss="modal"><span class="mdi mdi-close" aria-hidden="true"></span><span>Закрити</span></button>
                <button type="button" data-import-confirm><span class="mdi mdi-database-import-outline" aria-hidden="true"></span><span>Імпортувати</span></button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
document.querySelectorAll('[data-import-form]').forEach(function (form) {
    const message = document.querySelector('[data-import-message]');
    const previewButtons = form.querySelectorAll('[data-import-preview]');
    const sourceInputs = form.querySelectorAll('[data-import-source]');
    const fileBlocks = form.querySelectorAll('[data-file-source]');
    const dbBlocks = form.querySelectorAll('[data-db-source]');
    const modalNode = document.getElementById('importPreviewModal');
    const modal = modalNode ? new bootstrap.Modal(modalNode) : null;
    const modalBody = modalNode ? modalNode.querySelector('[data-import-preview-body]') : null;
    const confirmButton = modalNode ? modalNode.querySelector('[data-import-confirm]') : null;

    function setMessage(text, isError) {
        if (!message) {
            return;
        }
        message.className = text ? (isError ? 'alert alert-warning' : 'alert alert-success') : '';
        message.textContent = text || '';
    }

    function setLoading(button, loading, text) {
        if (!button) {
            return;
        }
        if (loading) {
            button.dataset.originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="mdi mdi-loading mdi-spin" aria-hidden="true"></span><span>' + text + '</span>';
        } else {
            button.disabled = false;
            button.innerHTML = button.dataset.originalHtml || button.innerHTML;
        }
    }

    function activeSource() {
        const checked = form.querySelector('[name="source"]:checked');
        return checked ? checked.value : 'file';
    }

    function syncSource() {
        const database = activeSource() === 'database';
        fileBlocks.forEach(function (block) { block.hidden = database; });
        dbBlocks.forEach(function (block) { block.hidden = !database; });
        if (database) {
            const wordpress = form.querySelector('input[name="type"][value="wordpress"]');
            if (!wordpress) {
                const input = document.createElement('input');
                input.type = 'radio';
                input.name = 'type';
                input.value = 'wordpress';
                input.checked = true;
                input.hidden = true;
                form.appendChild(input);
            } else {
                wordpress.checked = true;
            }
        } else {
            const current = form.querySelector('input[name="type"]:checked');
            if (!current || current.value === 'wordpress') {
                const first = form.querySelector('.import-option input[name="type"]');
                if (first) {
                    first.checked = true;
                }
            }
        }
    }

    function activeSubmitButton() {
        return Array.from(form.querySelectorAll('button[type="submit"]')).find(function (button) {
            return !button.closest('[hidden]');
        }) || form.querySelector('button[type="submit"]');
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
        });
    }

    function renderPreview(data) {
        const summary = (data.summary || []).map(function (item) {
            return '<div class="metric import-preview-metric"><span>' + escapeHtml(item.label) + '</span><strong>' + escapeHtml(item.value) + '</strong></div>';
        }).join('');
        const rows = (data.rows || []).map(function (row) {
            return '<tr><td>' + escapeHtml(row.target) + '</td><td>' + escapeHtml(row.title) + '</td><td>' + escapeHtml(row.status) + '</td><td>' + escapeHtml(row.date) + '</td><td>' + escapeHtml(row.excerpt) + '</td></tr>';
        }).join('');
        modalBody.innerHTML =
            '<div class="metrics import-preview-metrics">' + summary + '</div>' +
            '<div class="table-scroll"><table><thead><tr><th>Тип</th><th>Назва</th><th>Статус</th><th>Дата</th><th>Фрагмент</th></tr></thead><tbody>' + rows + '</tbody></table></div>' +
            '<p class="meta mt-3">Показано до 10 записів для перевірки перед імпортом.</p>';
    }

    async function requestImport(url, button, loadingText) {
        setMessage('', false);
        setLoading(button, true, loadingText);
        try {
            const response = await fetch(url, {
                method: 'POST',
                body: new FormData(form),
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Не вдалося виконати імпорт.');
            }
            return data;
        } finally {
            setLoading(button, false, loadingText);
        }
    }

    async function preview(button) {
        try {
            const data = await requestImport(form.dataset.previewUrl, button, 'Перегляд...');
            renderPreview(data);
            if (modal) {
                modal.show();
            }
        } catch (error) {
            setMessage(error.message || 'Помилка попереднього перегляду.', true);
        }
    }

    async function run(button) {
        try {
            const data = await requestImport(form.action, button, 'Імпорт...');
            setMessage('Імпорт завершено: створено ' + data.created + ' із ' + data.total + ' записів.', false);
            if (modal) {
                modal.hide();
            }
        } catch (error) {
            setMessage(error.message || 'Помилка імпорту.', true);
        }
    }

    sourceInputs.forEach(function (input) {
        input.addEventListener('change', syncSource);
    });
    previewButtons.forEach(function (button) {
        button.addEventListener('click', function () { preview(button); });
    });
    if (confirmButton) {
        confirmButton.addEventListener('click', function () { run(confirmButton); });
    }
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        run(activeSubmitButton());
    });

    syncSource();
});
});
</script>
