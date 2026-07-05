<div class="page-head">
    <div>
        <p class="eyebrow">Дані</p>
        <h1>Імпорт</h1>
        <p class="page-subtitle">Завантажте CSV або JSON, щоб швидко створити новини, сторінки, документи, розділи публічної інформації чи глобальні поля.</p>
    </div>
</div>

<?php if (!empty($result)): ?>
    <div class="alert alert-success">
        Імпорт завершено: створено <?= e((string) $result['created']) ?> із <?= e((string) $result['total']) ?> записів.
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-warning">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<form class="form-grid wide" method="post" action="<?= url('/admin/import/run') ?>" enctype="multipart/form-data" data-no-ajax>
    <?= \App\Core\Csrf::field() ?>

    <section class="card admin-form-card">
        <div class="form-section-head">
            <div>
                <h2>Варіант імпорту</h2>
                <p class="meta">Оберіть, які записи потрібно створити.</p>
            </div>
        </div>

        <div class="import-options">
            <?php foreach ($importOptions as $key => $option): ?>
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

    <div class="editor-layout">
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
                <button type="submit"><span class="mdi mdi-database-import-outline" aria-hidden="true"></span><span>Запустити імпорт</span></button>
            </div>
        </aside>
    </div>
</form>
