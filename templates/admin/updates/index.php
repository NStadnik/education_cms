<div class="toolbar">
    <div>
        <h1><?= e($title) ?></h1>
        <p class="meta mb-0">Поточна версія: <strong data-update-current-version><?= e($currentVersion) ?></strong></p>
    </div>
    <button class="button secondary" type="button" data-update-check>
        <span class="mdi mdi-refresh" aria-hidden="true"></span>
        <span>Перевірити</span>
    </button>
</div>

<?php if ($message): ?>
    <div class="alert alert-info"><?= e($message) ?></div>
<?php endif; ?>

<div
    class="card admin-form-card"
    data-updates-panel
    data-check-url="<?= e(url('/admin/updates/check')) ?>"
    data-install-url="<?= e(url('/admin/updates/install')) ?>"
>
    <div class="form-section-head">
        <div>
            <h2 data-update-title><?= !empty($recentUpdate) ? 'Оновлення встановлено' : 'Перевірка оновлень' ?></h2>
            <p class="meta mb-0" data-update-subtitle><?= !empty($recentUpdate) ? 'Повторна перевірка GitHub запускається тільки вручну.' : 'Натисніть кнопку перевірки, щоб отримати останній GitHub Release.' ?></p>
        </div>
        <span class="badge text-bg-<?= !empty($recentUpdate) ? 'success' : 'secondary' ?>" data-update-badge><?= !empty($recentUpdate) ? 'Оновлено' : 'Очікує' ?></span>
    </div>

    <div class="alert alert-<?= !empty($recentUpdate) ? 'success' : 'info' ?>" data-update-status>
        <?= !empty($recentUpdate) ? 'Локальне оновлення завершено. Натисніть “Перевірити”, якщо потрібно заново звернутися до GitHub.' : 'Запит до GitHub ще не виконувався.' ?>
    </div>

    <div class="grid grid-3 mb-3">
        <div class="metric">
            <div><span>Встановлено</span><strong data-update-installed><?= e($currentVersion) ?></strong></div>
            <span class="mdi mdi-package-variant-closed metric-icon" aria-hidden="true"></span>
        </div>
        <div class="metric">
            <div><span>Останній реліз</span><strong data-update-latest>—</strong></div>
            <span class="mdi mdi-cloud-download-outline metric-icon" aria-hidden="true"></span>
        </div>
        <div class="metric">
            <div><span>Архів</span><strong data-update-package>—</strong></div>
            <span class="mdi mdi-zip-box-outline metric-icon" aria-hidden="true"></span>
        </div>
    </div>

    <div class="hint-box mb-3 d-none" data-update-zipball-warning>
        У релізі немає окремого архіву <code>education-cms-v*.zip</code>, тому буде використаний стандартний GitHub source zip без checksum.
    </div>

    <div class="hint-box mb-3 d-none" data-update-body></div>

    <div class="form-actions">
        <a class="button secondary d-none" href="#" target="_blank" rel="noopener" data-update-release-link>
            <span class="mdi mdi-open-in-new" aria-hidden="true"></span>
            <span>Відкрити реліз</span>
        </a>
        <button class="button d-none" type="button" data-update-install disabled>
            <span class="mdi mdi-update" aria-hidden="true"></span>
            <span>Встановити оновлення</span>
        </button>
    </div>
</div>

<div class="hint-box">
    Оновлення не перезаписує <code>config/local.php</code>, <code>storage/</code>, завантажені файли та локальні backup-архіви.
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const panel = document.querySelector('[data-updates-panel]');
    if (!panel) {
        return;
    }

    const checkButton = document.querySelector('[data-update-check]');
    const installButton = panel.querySelector('[data-update-install]');
    const status = panel.querySelector('[data-update-status]');
    const badge = panel.querySelector('[data-update-badge]');
    const title = panel.querySelector('[data-update-title]');
    const subtitle = panel.querySelector('[data-update-subtitle]');
    const installed = panel.querySelector('[data-update-installed]');
    const globalCurrent = document.querySelector('[data-update-current-version]');
    const latest = panel.querySelector('[data-update-latest]');
    const packageName = panel.querySelector('[data-update-package]');
    const body = panel.querySelector('[data-update-body]');
    const releaseLink = panel.querySelector('[data-update-release-link]');
    const zipballWarning = panel.querySelector('[data-update-zipball-warning]');
    let lastRelease = null;

    function setBusy(button, busy, label) {
        if (!button) {
            return;
        }
        if (busy) {
            button.dataset.originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="mdi mdi-loading mdi-spin" aria-hidden="true"></span><span>' + label + '</span>';
            return;
        }
        button.disabled = false;
        if (button.dataset.originalHtml) {
            button.innerHTML = button.dataset.originalHtml;
        }
    }

    function setStatus(message, type) {
        status.className = 'alert alert-' + (type || 'info');
        status.textContent = message;
    }

    function setBadge(text, type) {
        badge.className = 'badge text-bg-' + (type || 'secondary');
        badge.textContent = text;
    }

    function versionText(value) {
        return value || '—';
    }

    function renderRelease(release, message) {
        lastRelease = release || null;
        installButton.classList.add('d-none');
        installButton.disabled = true;
        zipballWarning.classList.add('d-none');
        body.classList.add('d-none');
        releaseLink.classList.add('d-none');

        if (!release) {
            title.textContent = 'Перевірка оновлень';
            subtitle.textContent = 'Натисніть кнопку перевірки, щоб отримати останній GitHub Release.';
            latest.textContent = '—';
            packageName.textContent = '—';
            setBadge('Очікує', 'secondary');
            if (message) {
                setStatus(message, 'info');
            }
            return;
        }

        title.textContent = 'GitHub Release ' + (release.tag || ('v' + release.version));
        subtitle.textContent = release.published_at ? ('Опубліковано: ' + release.published_at) : 'Дата релізу недоступна';
        installed.textContent = versionText(release.current_version);
        if (globalCurrent) {
            globalCurrent.textContent = versionText(release.current_version);
        }
        latest.textContent = versionText(release.version);
        packageName.textContent = release.package_name || 'немає';

        if (release.html_url) {
            releaseLink.href = release.html_url;
            releaseLink.classList.remove('d-none');
        }
        if (release.package_source === 'zipball') {
            zipballWarning.classList.remove('d-none');
        }
        if (release.body) {
            body.textContent = release.body;
            body.style.whiteSpace = 'pre-line';
            body.classList.remove('d-none');
        }

        if (release.has_update) {
            setBadge('Доступне оновлення', 'primary');
            setStatus(message || ('Доступне оновлення до версії ' + release.version + '.'), 'primary');
            if (release.package_url) {
                installButton.classList.remove('d-none');
                installButton.disabled = false;
            }
        } else {
            setBadge('Актуально', 'success');
            setStatus(message || 'Установлена актуальна версія.', 'success');
        }
    }

    async function postJson(url) {
        const formData = new FormData();
        formData.append('_csrf', adminCsrfToken);
        const response = await fetch(url, {
            method: 'POST',
            body: formData,
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        });
        const text = await response.text();
        let data = {};
        try {
            data = text ? JSON.parse(text) : {};
        } catch (error) {
            throw new Error('Сервер повернув неочікувану відповідь.');
        }
        if (!response.ok || !data.ok) {
            throw new Error(data.message || 'Запит не виконано.');
        }
        return data;
    }

    async function checkUpdates() {
        setBusy(checkButton, true, 'Перевірка...');
        installButton.disabled = true;
        setBadge('Перевірка', 'secondary');
        setStatus('Отримуємо інформацію з GitHub...', 'info');

        try {
            const data = await postJson(panel.dataset.checkUrl);
            if (data.current_version) {
                installed.textContent = data.current_version;
                if (globalCurrent) {
                    globalCurrent.textContent = data.current_version;
                }
            }
            renderRelease(data.release, data.message);
        } catch (error) {
            lastRelease = null;
            setBadge('Помилка', 'warning');
            setStatus(error.message || 'Не вдалося перевірити оновлення.', 'warning');
            latest.textContent = '—';
            packageName.textContent = '—';
            installButton.classList.add('d-none');
        } finally {
            setBusy(checkButton, false);
        }
    }

    async function installUpdate() {
        if (!lastRelease || !lastRelease.has_update) {
            setStatus('Спершу перевірте доступні оновлення.', 'warning');
            return;
        }
        if (!confirm('Встановити оновлення до версії ' + lastRelease.version + '?')) {
            return;
        }

        setBusy(installButton, true, 'Оновлення...');
        setBusy(checkButton, true, 'Зачекайте...');
        setBadge('Оновлення', 'secondary');
        setStatus('Завантажуємо архів, створюємо backup і замінюємо системні файли...', 'info');

        try {
            const data = await postJson(panel.dataset.installUrl);
            const currentVersion = data.current_version || data.installed_version || lastRelease.version;
            installed.textContent = currentVersion;
            if (globalCurrent) {
                globalCurrent.textContent = currentVersion;
            }
            latest.textContent = currentVersion;
            setBadge('Оновлено', 'success');
            setStatus(data.message + (data.backup_path ? ' Backup: ' + data.backup_path : ''), 'success');
            installButton.classList.add('d-none');
            lastRelease = null;
        } catch (error) {
            setBadge('Помилка', 'warning');
            setStatus(error.message || 'Помилка оновлення.', 'warning');
            installButton.disabled = false;
        } finally {
            setBusy(checkButton, false);
            setBusy(installButton, false);
        }
    }

    checkButton.addEventListener('click', checkUpdates);
    installButton.addEventListener('click', installUpdate);
});
</script>
