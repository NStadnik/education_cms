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
    <div class="import-progress-track" aria-hidden="true"><span data-import-progress-bar></span></div>
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

    <section class="card admin-form-card">
        <div class="form-section-head">
            <div>
                <h2>Дублі</h2>
                <p class="meta">Перевірка шукає записи за slug: знайдені оновлює, відсутні додає.</p>
            </div>
        </div>
        <div class="source-options">
            <label class="check-row"><input type="checkbox" name="import_check_duplicates" value="1" checked> Перевіряти на дублі</label>
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
                <p class="meta">Підключення читає WordPress дописи, сторінки, рубрики, меню та прив'язки до них.</p>
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
                    <label class="wp-scope-option">
                        <input type="radio" name="wp_import_scope" value="menu" data-wp-scope>
                        <span class="mdi mdi-menu" aria-hidden="true"></span>
                        <strong>Тільки меню</strong>
                        <small>Перенести WordPress меню та mega menu</small>
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
                <label class="check-row" data-wp-media-toggle><input type="checkbox" name="wp_import_media" value="1" checked> Завантажити файли, перенести головні зображення та замінити старі адреси в публікаціях</label>
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

<script src="<?= url('/assets/admin-import.js') ?>"></script>
