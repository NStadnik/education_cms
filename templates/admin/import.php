<div class="page-head">
    <div>
        <p class="eyebrow">Дані</p>
        <h1>Імпорт</h1>
        <p class="page-subtitle">Завантажте CSV/JSON або підключіться до WordPress бази даних, перегляньте записи й запустіть імпорт.</p>
    </div>
</div>

<div data-import-message></div>
<div class="import-progress" data-import-progress hidden>
    <div class="import-progress-head">
        <div>
            <strong data-import-progress-title>Перебіг імпорту</strong>
            <span data-import-progress-detail>Очікування запуску</span>
        </div>
        <span class="import-progress-percent" data-import-progress-percent>0%</span>
    </div>
    <div class="import-progress-track" aria-hidden="true"><span data-import-progress-bar style="width: 0%"></span></div>
    <div class="import-progress-steps">
        <div class="import-progress-step" data-import-progress-step="media">
            <span class="mdi mdi-file-upload-outline" aria-hidden="true"></span>
            <div>
                <strong>Файли</strong>
                <small data-import-progress-media>Не запускалось</small>
            </div>
        </div>
        <div class="import-progress-step" data-import-progress-step="posts">
            <span class="mdi mdi-newspaper-variant-outline" aria-hidden="true"></span>
            <div>
                <strong>Матеріали</strong>
                <small data-import-progress-posts>Не запускалось</small>
            </div>
        </div>
    </div>
</div>

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
                <p class="meta">Підключення читає WordPress дописи, сторінки, рубрики та прив'язки до них.</p>
            </div>
        </div>
        <div class="wp-import-layout">
            <div class="wp-import-panel">
                <div class="wp-import-panel-head">
                    <span class="mdi mdi-database-cog-outline" aria-hidden="true"></span>
                    <div>
                        <strong>Підключення</strong>
                        <small>Дані доступу до старої WordPress бази</small>
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
                </div>
            </div>

            <div class="wp-import-panel">
                <div class="wp-import-panel-head">
                    <span class="mdi mdi-tune-variant" aria-hidden="true"></span>
                    <div>
                        <strong>Режим імпорту</strong>
                        <small>Виберіть обсяг роботи для запуску</small>
                    </div>
                </div>
                <div class="wp-scope-options">
                    <label class="wp-scope-option">
                        <input type="radio" name="wp_import_scope" value="all" checked data-wp-scope>
                        <span class="mdi mdi-folder-sync-outline" aria-hidden="true"></span>
                        <strong>Файли і матеріали</strong>
                        <small>Повний перенос з оновленням посилань</small>
                    </label>
                    <label class="wp-scope-option">
                        <input type="radio" name="wp_import_scope" value="media" data-wp-scope>
                        <span class="mdi mdi-file-upload-outline" aria-hidden="true"></span>
                        <strong>Тільки файли</strong>
                        <small>Завантажити вкладення без новин і сторінок</small>
                    </label>
                    <label class="wp-scope-option">
                        <input type="radio" name="wp_import_scope" value="posts" data-wp-scope>
                        <span class="mdi mdi-newspaper-variant-outline" aria-hidden="true"></span>
                        <strong>Тільки матеріали</strong>
                        <small>Створити новини та сторінки без файлів</small>
                    </label>
                </div>
            </div>

            <div class="wp-import-panel" data-wp-post-fields>
                <div class="wp-import-panel-head">
                    <span class="mdi mdi-filter-outline" aria-hidden="true"></span>
                    <div>
                        <strong>Матеріали</strong>
                        <small>Фільтри для дописів і сторінок</small>
                    </div>
                </div>
                <div class="grid grid-3">
                    <label>Статус
                        <select name="wp_status">
                            <option value="publish">Тільки опубліковані</option>
                            <option value="draft">Тільки чернетки</option>
                            <option value="any">Усі статуси</option>
                        </select>
                    </label>
                    <label>Тип матеріалів
                        <select name="wp_post_type">
                            <option value="any">Дописи і сторінки</option>
                            <option value="post">Тільки дописи</option>
                            <option value="page">Тільки сторінки</option>
                        </select>
                    </label>
                    <label>Пошук<input name="wp_search" placeholder="Назва, slug або текст" autocomplete="off"></label>
                </div>
                <div class="grid grid-3">
                    <label>Дата від<input type="date" name="wp_date_from"></label>
                    <label>Дата до<input type="date" name="wp_date_to"></label>
                    <label>Почати з матеріалу №<input type="number" name="db_offset" value="0" min="0"></label>
                </div>
                <div class="grid grid-3">
                    <label>Матеріалів за пакет<input type="number" name="db_limit" value="200" min="1" max="1000"></label>
                </div>
            </div>

            <div class="wp-import-panel" data-wp-media-fields>
                <div class="wp-import-panel-head">
                    <span class="mdi mdi-folder-upload-outline" aria-hidden="true"></span>
                    <div>
                        <strong>Файли</strong>
                        <small>Джерело для WordPress uploads</small>
                    </div>
                </div>
                <div class="grid grid-3">
                    <label>URL старого сайту<input name="wp_site_url" placeholder="https://example.com" autocomplete="off"></label>
                    <label>Шлях до wp-content/uploads<input name="wp_uploads_path" placeholder="/home/user/site/wp-content/uploads" autocomplete="off"></label>
                    <label>Почати з файлу №<input type="number" name="wp_media_offset" value="0" min="0"></label>
                </div>
                <div class="grid grid-3">
                    <label>Файлів за пакет<input type="number" name="wp_media_limit" value="1000" min="1" max="5000"></label>
                </div>
                <label class="check-row" data-wp-media-toggle><input type="checkbox" name="wp_import_media" value="1" checked> Завантажити файли та замінити старі адреси в публікаціях</label>
            </div>

            <div class="wp-import-panel compact">
                <div class="wp-import-panel-head">
                    <span class="mdi mdi-progress-upload" aria-hidden="true"></span>
                    <div>
                        <strong>Пакетне виконання</strong>
                        <small>Для великих баз імпорт іде порціями з прогресом</small>
                    </div>
                </div>
                <label class="check-row"><input type="checkbox" name="wp_step_import" value="1" checked> Поетапно завантажувати великі обсяги</label>
            </div>
        </div>
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
            <button type="button" class="button secondary" data-import-preview data-db-preview-button><span class="mdi mdi-eye-outline" aria-hidden="true"></span><span>Попередній перегляд</span></button>
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
    const progress = document.querySelector('[data-import-progress]');
    const progressTitle = progress ? progress.querySelector('[data-import-progress-title]') : null;
    const progressDetail = progress ? progress.querySelector('[data-import-progress-detail]') : null;
    const progressPercent = progress ? progress.querySelector('[data-import-progress-percent]') : null;
    const progressBar = progress ? progress.querySelector('[data-import-progress-bar]') : null;
    const progressMedia = progress ? progress.querySelector('[data-import-progress-media]') : null;
    const progressPosts = progress ? progress.querySelector('[data-import-progress-posts]') : null;
    const progressSteps = progress ? Array.from(progress.querySelectorAll('[data-import-progress-step]')) : [];
    const progressMediaStep = progressSteps.find(function (step) { return step.dataset.importProgressStep === 'media'; }) || null;
    const progressPostsStep = progressSteps.find(function (step) { return step.dataset.importProgressStep === 'posts'; }) || null;
    const previewButtons = form.querySelectorAll('[data-import-preview]');
    const sourceInputs = form.querySelectorAll('[data-import-source]');
    const scopeInputs = form.querySelectorAll('[data-wp-scope]');
    const wpPostFields = form.querySelector('[data-wp-post-fields]');
    const wpMediaFields = form.querySelector('[data-wp-media-fields]');
    const wpMediaToggle = form.querySelector('[data-wp-media-toggle]');
    const dbPreviewButtons = form.querySelectorAll('[data-db-preview-button]');
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

    function clampPercent(value) {
        if (!Number.isFinite(value)) {
            return 0;
        }
        return Math.max(0, Math.min(100, Math.round(value)));
    }

    function setProgressStage(node, state) {
        if (!node) {
            return;
        }
        node.classList.remove('is-active', 'is-done', 'is-muted');
        if (state) {
            node.classList.add(state);
        }
    }

    function showProgress(options) {
        if (!progress) {
            return;
        }
        const data = Object.assign({
            title: 'Перебіг імпорту',
            detail: '',
            percent: 0,
            mediaText: null,
            postsText: null,
            mediaState: null,
            postsState: null
        }, options || {});
        const percent = clampPercent(data.percent);
        progress.hidden = false;
        if (progressTitle) {
            progressTitle.textContent = data.title;
        }
        if (progressDetail) {
            progressDetail.textContent = data.detail;
        }
        if (progressPercent) {
            progressPercent.textContent = percent + '%';
        }
        if (progressBar) {
            progressBar.style.width = percent + '%';
        }
        if (data.mediaText !== null && progressMedia) {
            progressMedia.textContent = data.mediaText;
        }
        if (data.postsText !== null && progressPosts) {
            progressPosts.textContent = data.postsText;
        }
        if (data.mediaState !== null) {
            setProgressStage(progressMediaStep, data.mediaState);
        }
        if (data.postsState !== null) {
            setProgressStage(progressPostsStep, data.postsState);
        }
    }

    function resetProgress() {
        showProgress({
            title: 'Перебіг імпорту',
            detail: 'Підготовка запиту...',
            percent: 0,
            mediaText: 'Очікування',
            postsText: 'Очікування',
            mediaState: 'is-muted',
            postsState: 'is-muted'
        });
    }

    function updateProgressSegment(stage, done, total, created, percentBase, percentRange) {
        const safeDone = Math.max(0, done || 0);
        const safeTotal = Math.max(0, total || safeDone);
        const stagePercent = safeTotal > 0 ? safeDone / safeTotal : 0;
        const percent = percentBase + (stagePercent * percentRange);
        const countText = safeDone + ' / ' + (safeTotal || safeDone);
        const createdText = created !== undefined ? ', створено ' + created : '';
        if (stage === 'media') {
            showProgress({
                title: 'Імпорт WordPress',
                detail: 'Завантаження файлів...',
                percent: percent,
                mediaText: countText + createdText,
                mediaState: safeDone >= safeTotal && safeTotal > 0 ? 'is-done' : 'is-active',
                postsState: 'is-muted'
            });
            return;
        }
        showProgress({
            title: 'Імпорт WordPress',
            detail: 'Імпорт матеріалів...',
            percent: percent,
            postsText: countText + createdText,
            postsState: safeDone >= safeTotal && safeTotal > 0 ? 'is-done' : 'is-active',
            mediaState: progressMediaStep && progressMediaStep.classList.contains('is-done') ? 'is-done' : null
        });
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

    function fieldByName(name) {
        return form.elements.namedItem(name);
    }

    function fieldsByName(name) {
        const field = fieldByName(name);
        if (!field) {
            return [];
        }
        if (typeof field.length === 'number' && !field.nodeType) {
            return Array.from(field);
        }
        return [field];
    }

    function checkedField(name) {
        return fieldsByName(name).find(function (field) {
            return field.checked;
        }) || null;
    }

    function activeSource() {
        const checked = checkedField('source');
        return checked ? checked.value : 'file';
    }

    function syncSource() {
        const database = activeSource() === 'database';
        fileBlocks.forEach(function (block) { block.hidden = database; });
        dbBlocks.forEach(function (block) { block.hidden = !database; });
        if (database) {
            const wordpress = fieldsByName('type').find(function (field) {
                return field.value === 'wordpress';
            });
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
            const current = checkedField('type');
            if (!current || current.value === 'wordpress') {
                const first = Array.from(form.querySelectorAll('.import-option input')).find(function (input) {
                    return input.name === 'type';
                });
                if (first) {
                    first.checked = true;
                }
            }
        }
        syncWordPressScope();
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

    async function requestImport(url, button, loadingText, extraFields) {
        setMessage('', false);
        setLoading(button, true, loadingText);
        try {
            const body = new FormData(form);
            Object.keys(extraFields || {}).forEach(function (key) {
                body.set(key, extraFields[key]);
            });
            const response = await fetch(url, {
                method: 'POST',
                body: body,
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

    function isStepImportEnabled() {
        const checkbox = fieldByName('wp_step_import');
        return activeSource() === 'database' && checkbox && checkbox.checked;
    }

    function importScope() {
        const checked = checkedField('wp_import_scope');
        return checked ? checked.value : 'all';
    }

    function setGroupDisabled(group, disabled) {
        if (!group) {
            return;
        }
        group.querySelectorAll('input, select, textarea').forEach(function (field) {
            field.disabled = disabled;
        });
    }

    function syncWordPressScope() {
        const scope = importScope();
        const mediaOnly = scope === 'media';
        const postsOnly = scope === 'posts';
        if (wpPostFields) {
            wpPostFields.hidden = mediaOnly;
            setGroupDisabled(wpPostFields, mediaOnly);
        }
        if (wpMediaFields) {
            wpMediaFields.hidden = postsOnly;
            setGroupDisabled(wpMediaFields, postsOnly);
        }
        if (wpMediaToggle) {
            wpMediaToggle.hidden = scope !== 'all';
            setGroupDisabled(wpMediaToggle, scope !== 'all');
        }
        dbPreviewButtons.forEach(function (button) {
            button.hidden = mediaOnly;
        });
    }

    function intInput(name, fallback) {
        const input = fieldByName(name);
        const value = input ? parseInt(input.value || '', 10) : NaN;
        return Number.isFinite(value) ? value : fallback;
    }

    function progressText(stage, done, total, created) {
        const totalText = total > 0 ? total : done;
        return stage + ': ' + done + ' / ' + totalText + (created !== undefined ? ', створено ' + created : '');
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

    async function runStepImport(button) {
        resetProgress();
        const scope = importScope();
        const mediaCheckbox = fieldByName('wp_import_media');
        const importMedia = scope === 'media' || (scope !== 'posts' && !!(mediaCheckbox && mediaCheckbox.checked));
        const importPosts = scope !== 'media';
        const mediaRange = importMedia && importPosts ? 45 : (importMedia ? 100 : 0);
        const postsBase = importMedia && importPosts ? 45 : 0;
        const postsRange = importPosts ? (importMedia ? 55 : 100) : 0;
        const mediaStartOffset = intInput('wp_media_offset', 0);
        const mediaLimit = intInput('wp_media_limit', 1000);
        let mediaOffset = mediaStartOffset;
        let mediaEndOffset = mediaStartOffset;
        let mediaTotal = 0;
        let mediaImported = 0;

        if (importMedia) {
            while (true) {
                const mediaData = await requestImport(form.action, button, 'Файли...', {
                    wp_media_only: '1',
                    wp_media_offset: String(mediaOffset),
                    wp_media_limit: String(mediaLimit)
                });
                const stats = mediaData.stats || {};
                mediaTotal = parseInt(stats.media_total || mediaData.total || mediaTotal || 0, 10);
                mediaOffset = parseInt(mediaData.next_offset || stats.media_next_offset || mediaOffset, 10);
                mediaEndOffset = mediaOffset;
                mediaImported += parseInt(stats.media_imported || 0, 10);
                setMessage(progressText('Файли WordPress', mediaOffset, mediaTotal, mediaImported), false);
                updateProgressSegment('media', mediaOffset, mediaTotal, mediaImported, 0, mediaRange);
                if (!mediaData.has_more || mediaOffset >= mediaTotal) {
                    break;
                }
            }
        }

        if (!importPosts) {
            setMessage('Імпорт файлів завершено: оброблено ' + mediaImported + ' із ' + mediaTotal + ' файлів.', false);
            showProgress({
                title: 'Імпорт завершено',
                detail: 'Файли WordPress оброблено.',
                percent: 100,
                mediaText: mediaImported + ' із ' + mediaTotal + ' файлів',
                mediaState: 'is-done',
                postsText: 'Не імпортувались',
                postsState: 'is-muted'
            });
            if (modal) {
                modal.hide();
            }
            return;
        }

        let postOffset = intInput('db_offset', 0);
        const postLimit = intInput('db_limit', 200);
        let postTotal = 0;
        let createdTotal = 0;
        while (true) {
            const extra = {
                db_offset: String(postOffset),
                db_limit: String(postLimit)
            };
            if (importMedia) {
                extra.wp_media_replace_only = '1';
                extra.wp_media_offset = String(mediaStartOffset);
                extra.wp_media_map_limit = String(Math.max(mediaLimit, mediaEndOffset - mediaStartOffset));
            }
            const postData = await requestImport(form.action, button, 'Матеріали...', extra);
            const stats = postData.stats || {};
            postTotal = parseInt(stats.posts_total || postData.total || postTotal || 0, 10);
            postOffset = parseInt(postData.next_offset || stats.posts_next_offset || postOffset, 10);
            createdTotal += parseInt(postData.created || 0, 10);
            setMessage(progressText('Матеріали WordPress', postOffset, postTotal, createdTotal), false);
            updateProgressSegment('posts', postOffset, postTotal, createdTotal, postsBase, postsRange);
            if (!postData.has_more || postOffset >= postTotal) {
                break;
            }
        }

        setMessage('Імпорт завершено: створено ' + createdTotal + ' із ' + postTotal + ' матеріалів' + (importMedia ? ', файлів оброблено ' + mediaImported + '.' : '.'), false);
        showProgress({
            title: 'Імпорт завершено',
            detail: 'WordPress імпорт виконано.',
            percent: 100,
            mediaText: importMedia ? (mediaImported + ' із ' + mediaTotal + ' файлів') : 'Не імпортувались',
            postsText: createdTotal + ' із ' + postTotal + ' матеріалів',
            mediaState: importMedia ? 'is-done' : 'is-muted',
            postsState: 'is-done'
        });
        if (modal) {
            modal.hide();
        }
    }

    async function run(button) {
        try {
            resetProgress();
            if (isStepImportEnabled()) {
                await runStepImport(button);
                return;
            }
            const scope = importScope();
            const extra = {};
            if (activeSource() === 'database' && scope === 'media') {
                extra.wp_media_only = '1';
            }
            if (activeSource() === 'database' && scope === 'posts') {
                extra.wp_import_media = '0';
            }
            const data = await requestImport(form.action, button, 'Імпорт...', extra);
            if (activeSource() === 'database' && scope === 'media') {
                const stats = data.stats || {};
                setMessage('Імпорт файлів завершено: оброблено ' + (stats.media_imported || 0) + ' із ' + (stats.media_total || data.total || 0) + ' файлів.', false);
                showProgress({
                    title: 'Імпорт завершено',
                    detail: 'Файли WordPress оброблено.',
                    percent: 100,
                    mediaText: (stats.media_imported || 0) + ' із ' + (stats.media_total || data.total || 0) + ' файлів',
                    postsText: 'Не імпортувались',
                    mediaState: 'is-done',
                    postsState: 'is-muted'
                });
            } else {
                setMessage('Імпорт завершено: створено ' + data.created + ' із ' + data.total + ' записів.', false);
                showProgress({
                    title: 'Імпорт завершено',
                    detail: 'Записи створено.',
                    percent: 100,
                    mediaText: activeSource() === 'database' && scope === 'posts' ? 'Не імпортувались' : 'Готово',
                    postsText: data.created + ' із ' + data.total + ' записів',
                    mediaState: activeSource() === 'database' && scope === 'posts' ? 'is-muted' : 'is-done',
                    postsState: 'is-done'
                });
            }
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
    scopeInputs.forEach(function (input) {
        input.addEventListener('change', syncWordPressScope);
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
