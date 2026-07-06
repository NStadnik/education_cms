<div class="page-head">
    <div>
        <p class="eyebrow">Конфігурація</p>
        <h1>Налаштування закладу</h1>
    </div>
</div>
<form class="form-grid wide" method="post" action="<?= url('/admin/settings/save') ?>">
    <?= \App\Core\Csrf::field() ?>
    <section class="card admin-form-card">
        <div class="form-section-head">
            <div>
                <h2>Шаблон сайту</h2>
                <p class="meta">Активний шаблон визначає глобальне оформлення публічного сайту. Структура хедера й футера редагується в розділі “Шаблони”.</p>
            </div>
        </div>
        <label>Активний шаблон
            <select name="site_template">
                <?php foreach (($siteTemplates ?? []) as $key => $template): ?>
                    <option value="<?= e((string) $key) ?>" <?= selected($settings['site_template'] ?? 'official', $key) ?>>
                        <?= e($template['name'] ?? (string) $key) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </section>

    <section class="card admin-form-card">
        <div class="form-section-head">
            <div>
                <h2>Головна сторінка</h2>
                <p class="meta">Виберіть опубліковану сторінку, яка відкриватиметься за адресою сайту.</p>
            </div>
        </div>
        <?php $selectedHomePageId = (int) ($settings['home_page_id'] ?? 0); ?>
        <label>Сторінка
            <select name="home_page_id">
                <option value="">Автоматично: сторінка зі slug home</option>
                <?php foreach (($homePages ?? []) as $page): ?>
                    <?php
                        $page = is_array($page) ? $page : [];
                        $pageId = (int) ($page['id'] ?? 0);
                        $isPublished = ($page['status'] ?? '') === 'published';
                    ?>
                    <option value="<?= e((string) $pageId) ?>" <?= selected($selectedHomePageId, $pageId) ?> <?= ($isPublished || $selectedHomePageId === $pageId) ? '' : 'disabled' ?>>
                        <?= e($page['title'] ?? 'Без назви') ?><?= $isPublished ? '' : ' (чернетка)' ?> /<?= e($page['slug'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </section>

    <section class="card admin-form-card">
        <div class="form-section-head">
            <div>
                <h2>Дані закладу</h2>
                <p class="meta">Ці дані використовуються в шапці, підвалі та публічних сторінках сайту.</p>
            </div>
        </div>
        <label>Назва<input name="institution_name" value="<?= e($settings['institution_name'] ?? '') ?>"></label>
    </section>

    <section class="card admin-form-card" data-global-fields>
        <div class="form-section-head">
            <div>
                <h2>Глобальні поля</h2>
                <p class="meta">Додайте будь-які потрібні дані: адресу, телефон, email, ЄДРПОУ або інші реквізити.</p>
            </div>
            <button class="button secondary compact" type="button" data-add-global-field>
                <span class="mdi mdi-plus" aria-hidden="true"></span>
                <span>Додати поле</span>
            </button>
        </div>
        <div class="global-fields-list" data-global-fields-list>
            <?php foreach (($globalFields ?? []) as $field): ?>
                <?php $field = is_array($field) ? $field : []; ?>
                <div class="global-field-row" data-global-field-row>
                    <label>Назва поля<input name="global_field_label[]" value="<?= e($field['label'] ?? '') ?>"></label>
                    <label>Значення<input name="global_field_value[]" value="<?= e($field['value'] ?? '') ?>"></label>
                    <button class="button secondary compact" type="button" data-remove-global-field title="Видалити поле">
                        <span class="mdi mdi-delete-outline" aria-hidden="true"></span>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
        <template data-global-field-template>
            <div class="global-field-row" data-global-field-row>
                <label>Назва поля<input name="global_field_label[]" value=""></label>
                <label>Значення<input name="global_field_value[]" value=""></label>
                <button class="button secondary compact" type="button" data-remove-global-field title="Видалити поле">
                    <span class="mdi mdi-delete-outline" aria-hidden="true"></span>
                </button>
            </div>
        </template>
    </section>

    <button type="submit"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span>Зберегти</span></button>
</form>

<script src="<?= url('/assets/admin-settings.js') ?>"></script>
