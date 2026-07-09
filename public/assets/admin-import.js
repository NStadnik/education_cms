document.addEventListener('DOMContentLoaded', function () {
document.querySelectorAll('[data-import-form]').forEach(function (form) {
    const message = document.querySelector('[data-import-message]');
    const progress = document.querySelector('[data-import-progress]');
    const progressTitle = progress ? progress.querySelector('[data-import-progress-title]') : null;
    const progressDetail = progress ? progress.querySelector('[data-import-progress-detail]') : null;
    const progressPercent = progress ? progress.querySelector('[data-import-progress-percent]') : null;
    const progressBar = progress ? progress.querySelector('[data-import-progress-bar]') : null;
    const pauseButton = progress ? progress.querySelector('[data-import-pause]') : null;
    const pauseLabel = progress ? progress.querySelector('[data-import-pause-label]') : null;
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
    const wpMenuImportButton = form.querySelector('[data-wp-menu-import]');
    const dbPreviewButtons = form.querySelectorAll('[data-db-preview-button]');
    const fileBlocks = form.querySelectorAll('[data-file-source]');
    const dbBlocks = form.querySelectorAll('[data-db-source]');
    const modalNode = document.getElementById('importPreviewModal');
    const modal = modalNode ? new bootstrap.Modal(modalNode) : null;
    const modalBody = modalNode ? modalNode.querySelector('[data-import-preview-body]') : null;
    const confirmButton = modalNode ? modalNode.querySelector('[data-import-confirm]') : null;
    let importRunning = false;
    let pauseAvailable = false;
    let pauseRequested = false;
    let paused = false;
    let resumeImport = null;

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

    function syncPauseButton() {
        if (!pauseButton || !pauseLabel) {
            return;
        }
        pauseButton.hidden = !importRunning || !pauseAvailable;
        pauseButton.disabled = !importRunning || !pauseAvailable;
        pauseButton.classList.toggle('secondary', !paused);
        pauseButton.classList.toggle('button', true);
        pauseLabel.textContent = paused ? 'Продовжити' : (pauseRequested ? 'Пауза...' : 'Пауза');
        const icon = pauseButton.querySelector('.mdi');
        if (icon) {
            icon.className = 'mdi ' + (paused ? 'mdi-play' : 'mdi-pause');
        }
    }

    function requestPause() {
        if (!importRunning || !pauseAvailable || paused) {
            return;
        }
        pauseRequested = true;
        syncPauseButton();
        setMessage('Імпорт буде поставлено на паузу після завершення поточного пакета.', false);
    }

    function resumePausedImport() {
        if (!paused) {
            return;
        }
        paused = false;
        pauseRequested = false;
        const resume = resumeImport;
        resumeImport = null;
        syncPauseButton();
        setMessage('Імпорт продовжено.', false);
        if (resume) {
            resume();
        }
    }

    function pauseIfRequested(label) {
        if (!pauseRequested) {
            return Promise.resolve();
        }

        paused = true;
        syncPauseButton();
        const detail = label ? 'Пауза: ' + label : 'Імпорт на паузі.';
        if (progressDetail) {
            progressDetail.textContent = detail;
        }
        setMessage('Імпорт на паузі. Натисніть «Продовжити», щоб запустити наступний пакет.', false);

        return new Promise(function (resolve) {
            resumeImport = resolve;
        });
    }

    function finishImport() {
        importRunning = false;
        pauseAvailable = false;
        pauseRequested = false;
        paused = false;
        resumeImport = null;
        syncPauseButton();
    }

    function resetProgress() {
        importRunning = true;
        pauseAvailable = false;
        pauseRequested = false;
        paused = false;
        resumeImport = null;
        syncPauseButton();
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
        const createdText = created !== undefined ? ', ' + (stage === 'media' ? 'імпортовано' : mutationLabel()) + ' ' + created : '';
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
            const text = await response.text();
            let data = null;
            try {
                data = JSON.parse(text);
            } catch (error) {
                throw new Error(text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() || 'Сервер повернув неочікувану відповідь.');
            }
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Не вдалося виконати імпорт.');
            }
            return data;
        } finally {
            setLoading(button, false, loadingText);
        }
    }

    function isStepImportEnabled() {
        return activeSource() === 'database';
    }

    function importScope() {
        const checked = checkedField('wp_import_scope');
        return checked ? checked.value : 'all';
    }

    function importMode() {
        const duplicateCheck = fieldByName('import_check_duplicates');
        if (duplicateCheck) {
            return duplicateCheck.checked ? 'upsert' : 'create';
        }
        const checked = checkedField('import_mode');
        return checked ? checked.value : 'create';
    }

    function mutationLabel() {
        if (importMode() === 'upsert') {
            return 'оброблено';
        }
        return importMode() === 'update' ? 'оновлено' : 'створено';
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
        dbPreviewButtons.forEach(function (button) {
            button.hidden = mediaOnly;
        });
    }

    function intInput(name, fallback) {
        const input = fieldByName(name);
        const value = input ? parseInt(input.value || '', 10) : NaN;
        return Number.isFinite(value) ? value : fallback;
    }

    function setInputValue(name, value) {
        const input = fieldByName(name);
        if (input && input.value !== undefined) {
            input.value = String(value);
        }
    }

    function progressText(stage, done, total, created) {
        const totalText = total > 0 ? total : done;
        const label = stage.indexOf('Файли') === 0 ? 'імпортовано' : mutationLabel();
        return stage + ': ' + done + ' / ' + totalText + (created !== undefined ? ', ' + label + ' ' + created : '');
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
        pauseAvailable = true;
        syncPauseButton();
        const scope = importScope();
        const importMedia = scope === 'media' || scope === 'all';
        const importPosts = scope !== 'media';
        const mediaRange = importMedia && importPosts ? 45 : (importMedia ? 100 : 0);
        const postsBase = importMedia && importPosts ? 45 : 0;
        const postsRange = importPosts ? (importMedia ? 55 : 100) : 0;
        const mediaStartOffset = intInput('wp_media_offset', 0);
        const mediaLimit = Math.min(intInput('wp_media_limit', 20), 20);
        let mediaOffset = mediaStartOffset;
        let mediaEndOffset = mediaStartOffset;
        let mediaTotal = 0;
        let mediaImported = 0;

        if (importMedia) {
            while (true) {
                const mediaData = await requestImport(form.action, button, 'Файли...', {
                    wp_media_only: '1',
                    wp_media_offset: String(mediaOffset),
                    wp_media_limit: String(mediaLimit),
                    wp_media_seconds: '25'
                });
                const stats = mediaData.stats || {};
                mediaTotal = parseInt(stats.media_total || mediaData.total || mediaTotal || 0, 10);
                mediaOffset = parseInt(mediaData.next_offset || stats.media_next_offset || mediaOffset, 10);
                mediaEndOffset = mediaOffset;
                setInputValue('wp_media_offset', mediaOffset);
                mediaImported += parseInt(stats.media_imported || 0, 10);
                setMessage(progressText('Файли WordPress', mediaOffset, mediaTotal, mediaImported), false);
                updateProgressSegment('media', mediaOffset, mediaTotal, mediaImported, 0, mediaRange);
                if (!mediaData.has_more || mediaOffset >= mediaTotal) {
                    break;
                }
                await pauseIfRequested('файли WordPress оброблено до №' + mediaOffset);
            }
        }

        if (importPosts) {
            await pauseIfRequested('перед імпортом матеріалів');
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
        const postLimit = importMedia ? Math.min(intInput('db_limit', 200), 50) : intInput('db_limit', 200);
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
                extra.wp_content_media_limit = '20';
                extra.wp_content_media_seconds = '20';
            }
            const postData = await requestImport(form.action, button, 'Матеріали...', extra);
            const stats = postData.stats || {};
            postTotal = parseInt(stats.posts_total || postData.total || postTotal || 0, 10);
            postOffset = parseInt(postData.next_offset || stats.posts_next_offset || postOffset, 10);
            setInputValue('db_offset', postOffset);
            createdTotal += parseInt(postData.created || 0, 10);
            setMessage(progressText('Матеріали WordPress', postOffset, postTotal, createdTotal), false);
            updateProgressSegment('posts', postOffset, postTotal, createdTotal, postsBase, postsRange);
            if (!postData.has_more || postOffset >= postTotal) {
                break;
            }
            await pauseIfRequested('матеріали WordPress оброблено до №' + postOffset);
        }

        setMessage('Імпорт завершено: ' + mutationLabel() + ' ' + createdTotal + ' із ' + postTotal + ' матеріалів' + (importMedia ? ', файлів оброблено ' + mediaImported : '') + '.', false);
        showProgress({
            title: 'Імпорт завершено',
            detail: 'WordPress імпорт виконано.',
            percent: 100,
            mediaText: importMedia ? (mediaImported + ' із ' + mediaTotal + ' файлів') : 'Не імпортувались',
            postsText: mutationLabel() + ' ' + createdTotal + ' із ' + postTotal + ' матеріалів',
            mediaState: importMedia ? 'is-done' : 'is-muted',
            postsState: 'is-done'
        });
        if (modal) {
            modal.hide();
        }
    }

    async function run(button) {
        if (importRunning) {
            if (paused) {
                resumePausedImport();
            } else {
                setMessage('Імпорт уже виконується. Щоб зупинити наступний пакет, натисніть «Пауза».', false);
            }
            return;
        }

        try {
            resetProgress();
            const scope = importScope();
            if (isStepImportEnabled()) {
                await runStepImport(button);
                return;
            }
            const extra = {};
            if (activeSource() === 'database' && scope === 'media') {
                extra.wp_media_only = '1';
            }
            if (activeSource() === 'database' && scope === 'all') {
                extra.db_limit = String(Math.min(intInput('db_limit', 200), 50));
                extra.wp_content_media_limit = '20';
                extra.wp_content_media_seconds = '20';
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
                setMessage('Імпорт завершено: ' + mutationLabel() + ' ' + data.created + ' із ' + data.total + ' записів.', false);
                showProgress({
                    title: 'Імпорт завершено',
                    detail: importMode() === 'upsert' ? 'Дублі перевірено, записи оновлено або створено.' : 'Записи створено.',
                    percent: 100,
                    mediaText: activeSource() === 'database' && scope === 'posts' ? 'Не імпортувались' : 'Готово',
                    postsText: mutationLabel() + ' ' + data.created + ' із ' + data.total + ' записів',
                    mediaState: activeSource() === 'database' && scope === 'posts' ? 'is-muted' : 'is-done',
                    postsState: 'is-done'
                });
            }
            if (modal) {
                modal.hide();
            }
        } catch (error) {
            let errorMessage = error.message || 'Помилка імпорту.';
            if (activeSource() === 'database') {
                errorMessage += ' Прогрес збережено у полях offset: файли №' + intInput('wp_media_offset', 0) + ', матеріали №' + intInput('db_offset', 0) + '. Запустіть імпорт ще раз, щоб продовжити.';
            }
            setMessage(errorMessage, true);
        } finally {
            finishImport();
        }
    }

    async function runMenuImport(button) {
        if (importRunning) {
            setMessage('Зачекайте завершення поточного імпорту перед імпортом меню.', true);
            return;
        }

        try {
            resetProgress();
            const data = await requestImport(form.action, button, 'Меню...', {wp_menu_only: '1', wp_import_scope: 'menu'});
            const stats = data.stats || {};
            const menuItems = parseInt(stats.menu_items_imported || data.created || 0, 10);
            setMessage('Імпорт меню завершено: перенесено ' + menuItems + ' пунктів.', false);
            showProgress({
                title: 'Імпорт завершено',
                detail: 'WordPress меню перенесено у шаблон.',
                percent: 100,
                mediaText: 'Не імпортувались',
                postsText: menuItems + ' пунктів меню',
                mediaState: 'is-muted',
                postsState: 'is-done'
            });
        } catch (error) {
            setMessage(error.message || 'Помилка імпорту меню.', true);
        } finally {
            finishImport();
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
    if (pauseButton) {
        pauseButton.addEventListener('click', function () {
            if (paused) {
                resumePausedImport();
                return;
            }
            requestPause();
        });
    }
    if (wpMenuImportButton) {
        wpMenuImportButton.addEventListener('click', function () { runMenuImport(wpMenuImportButton); });
    }
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        run(activeSubmitButton());
    });

    syncSource();
});
});
