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
                <h2>Режим сайту</h2>
                <p class="meta">Неавторизовані відвідувачі побачать сучасну заглушку. Адміністратори після входу можуть переглядати сайт без обмежень.</p>
            </div>
            <?php $siteMode = (string) ($settings['site_mode'] ?? 'online'); ?>
            <span class="status <?= $siteMode === 'online' ? 'ok' : 'warn' ?>"><?= $siteMode === 'online' ? 'Сайт відкритий' : 'Заглушка активна' ?></span>
        </div>
        <div class="site-mode-grid">
            <label>Публічний доступ
                <select name="site_mode">
                    <option value="online" <?= selected($siteMode, 'online') ?>>Звичайний режим</option>
                    <option value="maintenance" <?= selected($siteMode, 'maintenance') ?>>Режим обслуговування</option>
                    <option value="coming_soon" <?= selected($siteMode, 'coming_soon') ?>>Скоро відкриття</option>
                    <option value="private" <?= selected($siteMode, 'private') ?>>Закритий доступ</option>
                </select>
            </label>
            <label>Заголовок заглушки
                <input name="site_mode_title" value="<?= e($settings['site_mode_title'] ?? '') ?>" placeholder="Автоматичний текст для вибраного режиму">
            </label>
        </div>
        <label>Повідомлення для відвідувачів
            <textarea name="site_mode_message" rows="4" placeholder="Коротко поясніть, що відбувається і коли сайт буде доступний."><?= e($settings['site_mode_message'] ?? '') ?></textarea>
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
        <?php $siteLogo = (string) ($settings['site_logo'] ?? ''); ?>
        <label>Назва<input name="institution_name" value="<?= e($settings['institution_name'] ?? '') ?>"></label>
        <div class="settings-logo-picker" data-settings-logo-picker data-thumb-base="<?= url('/thumb/') ?>">
            <input type="hidden" name="site_logo" value="<?= e($siteLogo) ?>" data-settings-logo-input>
            <div class="settings-logo-preview" data-settings-logo-preview>
                <?php if ($siteLogo !== ''): ?>
                    <img src="<?= url('/thumb/' . $siteLogo . '?w=160&h=160&fit=contain') ?>" alt="">
                <?php else: ?>
                    <span class="mdi mdi-image-outline" aria-hidden="true"></span>
                <?php endif; ?>
            </div>
            <div class="settings-logo-body">
                <strong>Логотип</strong>
                <span data-settings-logo-name><?= $siteLogo !== '' ? e($siteLogo) : 'Логотип не вибрано' ?></span>
                <div class="settings-logo-actions">
                    <button class="button secondary compact" type="button" data-settings-logo-open>
                        <span class="mdi mdi-image-search-outline" aria-hidden="true"></span><span>Обрати з медіа</span>
                    </button>
                    <button class="button secondary compact" type="button" data-settings-logo-clear <?= $siteLogo === '' ? 'hidden' : '' ?>>
                        <span class="mdi mdi-close" aria-hidden="true"></span><span>Очистити</span>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <?php $lcloud = is_array($lcloud ?? null) ? $lcloud : []; ?>
    <section class="card admin-form-card">
        <div class="form-section-head">
            <div>
                <h2>Інтеграція з ЛКЛАУД</h2>
                <p class="meta">Налаштуйте SSO-вхід викладачів і захист API публікацій. Секрети після збереження не відображаються.</p>
            </div>
            <span class="status <?= !empty($lcloud['enabled']) ? 'ok' : 'warn' ?>"><?= !empty($lcloud['enabled']) ? 'Увімкнено' : 'Вимкнено' ?></span>
        </div>
        <label class="check-row"><input type="checkbox" name="lcloud_enabled" value="1" <?= checked(!empty($lcloud['enabled'])) ?>> Увімкнути SSO ЛКЛАУД</label>
        <div class="form-grid wide">
            <label>Issuer
                <input name="lcloud_issuer" value="<?= e((string) ($lcloud['issuer'] ?? 'lcloud')) ?>" required autocomplete="off">
                <small class="meta">Значення claim <code>iss</code> у JWT.</small>
            </label>
            <label>Audience
                <input name="lcloud_audience" value="<?= e((string) ($lcloud['audience'] ?? 'education-cms')) ?>" required autocomplete="off">
                <small class="meta">Значення claim <code>aud</code> для цієї інсталяції CMS.</small>
            </label>
            <label>URL ЛКЛАУД для CORS
                <input type="url" name="lcloud_allowed_origin" value="<?= e((string) ($lcloud['allowed_origin'] ?? '')) ?>" placeholder="https://lcloud.example" autocomplete="off">
            </label>
            <label>Новий SSO-секрет
                <input type="password" name="lcloud_sso_secret" value="" minlength="32" placeholder="<?= !empty($lcloud['sso_secret']) ? 'Секрет уже налаштовано' : 'Щонайменше 32 символи' ?>" autocomplete="new-password">
                <small class="meta">Залиште порожнім, щоб не змінювати поточний секрет.</small>
            </label>
            <label>Новий API-ключ
                <input type="password" name="lcloud_api_key" value="" placeholder="<?= !empty($lcloud['api_key']) ? 'API-ключ уже налаштовано' : 'Введіть окремий API-ключ' ?>" autocomplete="new-password">
                <small class="meta">Залиште порожнім, щоб не змінювати поточний ключ.</small>
            </label>
        </div>
        <div class="form-grid wide">
            <label class="check-row"><input type="checkbox" name="lcloud_clear_sso_secret" value="1"> Видалити збережений SSO-секрет</label>
            <label class="check-row"><input type="checkbox" name="lcloud_clear_api_key" value="1"> Видалити збережений API-ключ</label>
        </div>
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

<div class="modal fade" id="settingsLogoPickerModal" tabindex="-1" aria-labelledby="settingsLogoPickerTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1">Медіафайли</p>
                    <h2 class="modal-title h5" id="settingsLogoPickerTitle">Обрати логотип</h2>
                    <p class="meta mb-0">Показуються лише зображення з медіатеки.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
            </div>
            <div class="modal-body">
                <div class="settings-logo-modal-tools">
                    <label class="list-search-field">
                        <span class="mdi mdi-magnify" aria-hidden="true"></span>
                        <input type="search" data-settings-logo-search placeholder="Пошук зображення">
                    </label>
                    <span class="list-count-pill" data-settings-logo-status>Готово</span>
                </div>
                <div class="settings-logo-grid" data-settings-logo-grid></div>
                <button class="button secondary compact settings-logo-more" type="button" data-settings-logo-more hidden>
                    <span class="mdi mdi-chevron-down" aria-hidden="true"></span><span>Показати ще</span>
                </button>
            </div>
            <div class="modal-footer">
                <button type="button" class="button secondary" data-bs-dismiss="modal"><span class="mdi mdi-close" aria-hidden="true"></span><span>Закрити</span></button>
            </div>
        </div>
    </div>
</div>

<script src="<?= url('/assets/admin-settings.js?v=20260710-2') ?>"></script>
