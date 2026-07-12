<div class="page-head">
    <div>
        <p class="eyebrow">Конфігурація</p>
        <h1>Налаштування закладу</h1>
    </div>
</div>
<form class="form-grid wide" method="post" action="<?= url('/admin/settings/save') ?>">
    <?= \App\Core\Csrf::field() ?>
    <nav class="settings-tabs" role="tablist" aria-label="Розділи налаштувань" data-settings-tabs>
        <button type="button" role="tab" aria-selected="true" data-settings-tab="general"><span class="mdi mdi-office-building-cog-outline" aria-hidden="true"></span><span>Загальні</span></button>
        <button type="button" role="tab" aria-selected="false" data-settings-tab="site"><span class="mdi mdi-web" aria-hidden="true"></span><span>Сайт</span></button>
        <button type="button" role="tab" aria-selected="false" data-settings-tab="mail"><span class="mdi mdi-email" aria-hidden="true"></span><span>Пошта</span></button>
        <button type="button" role="tab" aria-selected="false" data-settings-tab="integrations"><span class="mdi mdi-connection" aria-hidden="true"></span><span>Інтеграції</span></button>
    </nav>

    <section class="card admin-form-card" data-settings-panel="site" hidden>
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

    <section class="card admin-form-card" data-settings-panel="site" hidden>
        <div class="form-section-head">
            <div>
                <h2>Перенесення на основний домен</h2>
                <p class="meta">Після підготовки сайту на піддомені замініть його адресу в посиланнях сторінок, новин, форм, медіаописів і налаштувань.</p>
            </div>
        </div>
        <div class="domain-replace-panel">
            <div class="form-grid wide">
                <label>Старий домен
                    <input type="url" name="old_domain_preview" placeholder="https://site.lcloud.example" data-old-domain>
                </label>
                <label>Новий домен
                    <input type="url" name="new_domain_preview" placeholder="https://example.edu.ua" data-new-domain>
                </label>
            </div>
            <label class="check-row"><input type="checkbox" data-domain-confirm> Я перевірив адреси та підтверджую заміну</label>
            <div class="settings-domain-actions">
                <button class="button secondary" type="button" data-domain-replace data-replace-url="<?= url('/admin/settings/domain/replace') ?>">
                    <span class="mdi mdi-swap-horizontal" aria-hidden="true"></span><span>Замінити домен у посиланнях</span>
                </button>
                <span class="meta" data-domain-replace-status aria-live="polite"></span>
            </div>
            <p class="meta">Операція змінює лише дані в базі. Файли, системні адреси входу та домен у налаштуваннях хостингу не змінюються.</p>
        </div>
    </section>

    <section class="card admin-form-card" data-settings-panel="site" hidden>
        <div class="form-section-head">
            <div>
                <h2>Режим сайту</h2>
                <p class="meta">Неавторизовані відвідувачі побачать сучасну заглушку. Адміністратори після входу можуть переглядати сайт без обмежень.</p>
            </div>
            <?php $siteMode = (string) ($settings['site_mode'] ?? 'online'); ?>
            <span class="status <?= $siteMode === 'online' ? 'ok' : 'warn' ?>" data-site-mode-status><?= $siteMode === 'online' ? 'Сайт відкритий' : 'Заглушка активна' ?></span>
        </div>
        <div class="site-mode-grid">
            <label>Публічний доступ
                <select name="site_mode" data-site-mode-select>
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

    <section class="card admin-form-card" data-settings-panel="site" hidden>
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

    <section class="card admin-form-card" data-settings-panel="general">
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

    <?php $mail = is_array($mail ?? null) ? $mail : []; ?>
    <section class="card admin-form-card" data-settings-panel="mail" hidden>
        <div class="form-section-head">
            <div>
                <h2>Надсилання пошти</h2>
                <p class="meta">Параметри для системних повідомлень, відповідей форм і сповіщень про модерацію.</p>
            </div>
            <span class="status <?= !empty($mail['enabled']) ? 'ok' : 'warn' ?>" data-mail-status><?= !empty($mail['enabled']) ? 'Увімкнено' : 'Вимкнено' ?></span>
        </div>
        <label class="check-row"><input type="checkbox" name="mail_enabled" value="1" <?= checked(!empty($mail['enabled'])) ?> data-mail-enabled> Увімкнути надсилання пошти</label>
        <div class="form-grid wide">
            <label class="check-row"><input type="checkbox" name="mail_notify_news" value="1" <?= checked(!empty($mail['notify_news'])) ?>> Сповіщати про модерацію новин</label>
            <label class="check-row"><input type="checkbox" name="mail_notify_forms" value="1" <?= checked(!empty($mail['notify_forms'])) ?>> Сповіщати про нові відповіді форм</label>
        </div>
        <div class="form-grid wide">
            <label>Спосіб надсилання
                <select name="mail_transport" data-mail-transport>
                    <option value="mail" <?= selected($mail['transport'] ?? 'mail', 'mail') ?>>PHP mail()</option>
                    <option value="smtp" <?= selected($mail['transport'] ?? 'mail', 'smtp') ?>>SMTP-сервер</option>
                </select>
            </label>
            <label>Email відправника
                <input type="email" name="mail_from_email" value="<?= e((string) ($mail['from_email'] ?? '')) ?>" placeholder="noreply@example.edu.ua" autocomplete="off" data-mail-from-email <?= !empty($mail['enabled']) ? 'required' : '' ?>>
            </label>
            <label>Ім’я відправника
                <input name="mail_from_name" value="<?= e((string) ($mail['from_name'] ?? '')) ?>" placeholder="Назва закладу" autocomplete="off">
            </label>
            <label>Reply-To
                <input type="email" name="mail_reply_to" value="<?= e((string) ($mail['reply_to'] ?? '')) ?>" placeholder="office@example.edu.ua" autocomplete="off">
            </label>
        </div>
        <div class="form-section-head">
            <div><h3>SMTP</h3><p class="meta">Ці поля використовуються, коли вибрано SMTP-сервер.</p></div>
        </div>
        <div class="form-grid wide">
            <label>SMTP-сервер
                <input name="mail_smtp_host" value="<?= e((string) ($mail['smtp_host'] ?? '')) ?>" placeholder="smtp.example.edu.ua" autocomplete="off" data-mail-smtp-host <?= !empty($mail['enabled']) && ($mail['transport'] ?? 'mail') === 'smtp' ? 'required' : '' ?>>
            </label>
            <label>Порт
                <input type="number" name="mail_smtp_port" value="<?= e((string) ($mail['smtp_port'] ?? 587)) ?>" min="1" max="65535">
            </label>
            <label>Шифрування
                <select name="mail_smtp_encryption">
                    <option value="tls" <?= selected($mail['smtp_encryption'] ?? 'tls', 'tls') ?>>STARTTLS / TLS</option>
                    <option value="ssl" <?= selected($mail['smtp_encryption'] ?? 'tls', 'ssl') ?>>SSL</option>
                    <option value="none" <?= selected($mail['smtp_encryption'] ?? 'tls', 'none') ?>>Без шифрування</option>
                </select>
            </label>
            <label>SMTP-логін
                <input name="mail_smtp_username" value="<?= e((string) ($mail['smtp_username'] ?? '')) ?>" autocomplete="off">
            </label>
            <label>Новий SMTP-пароль
                <input type="password" name="mail_smtp_password" value="" placeholder="<?= !empty($mail['smtp_password']) ? 'Пароль уже налаштовано' : 'Введіть пароль' ?>" autocomplete="new-password" data-mail-password>
                <small class="meta">Залиште порожнім, щоб не змінювати поточний пароль.</small>
            </label>
        </div>
        <label class="check-row"><input type="checkbox" name="mail_clear_smtp_password" value="1"> Видалити збережений SMTP-пароль</label>
        <div class="mail-test-panel" data-mail-test-panel>
            <div>
                <strong>Перевірити надсилання</strong>
                <p class="meta">Спочатку збережіть параметри вище, потім надішліть тестовий лист.</p>
            </div>
            <div class="mail-test-controls">
                <input type="email" data-mail-test-email value="<?= e((string) ($user['email'] ?? '')) ?>" placeholder="recipient@example.edu.ua" aria-label="Email для тестового листа">
                <button class="button secondary" type="button" data-mail-test-button data-test-url="<?= url('/admin/settings/mail/test') ?>"><span class="mdi mdi-email-fast-outline" aria-hidden="true"></span><span>Надіслати тест</span></button>
            </div>
            <div class="meta" data-mail-test-status aria-live="polite"></div>
        </div>
    </section>

    <?php $lcloud = is_array($lcloud ?? null) ? $lcloud : []; ?>
    <section class="card admin-form-card" data-settings-panel="integrations" hidden>
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

    <section class="card admin-form-card" data-global-fields data-settings-panel="general">
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

<script src="<?= url('/assets/admin-settings.js?v=20260712-1') ?>"></script>
