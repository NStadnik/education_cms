<?php
    $defaultHeaderLayout = [
        'variant' => 'default',
        'show_brand' => true,
        'show_home' => false,
        'show_news' => false,
        'links' => [],
        'cta_label' => '',
        'cta_url' => '',
        'mobile_source' => 'main',
        'hero_enabled' => false,
        'hero_variant' => 'default',
        'hero_title' => '',
        'hero_text' => '',
        'hero_button_label' => '',
        'hero_button_url' => '',
        'home_hero_enabled' => false,
        'home_hero_variant' => 'fullscreen',
        'home_hero_title' => '',
        'home_hero_text' => '',
        'home_hero_button_label' => '',
        'home_hero_button_url' => '',
        'hero_background_image' => '',
        'hero_background_position' => 'center center',
        'hero_background_size' => 'cover',
        'hero_background_repeat' => 'no-repeat',
        'hero_overlay_enabled' => true,
        'hero_overlay_opacity' => '35',
        'home_hero_background_image' => '',
        'home_hero_background_position' => 'center center',
        'home_hero_background_size' => 'cover',
        'home_hero_background_repeat' => 'no-repeat',
        'home_hero_overlay_enabled' => true,
        'home_hero_overlay_opacity' => '35',
    ];
    $defaultFooterLayout = [
        'variant' => 'default',
        'columns' => [],
        'bottom_text' => '',
    ];
    $selectedTemplate = (string) ($settings['site_template'] ?? 'official');
    if (!array_key_exists($selectedTemplate, $siteTemplates)) {
        $selectedTemplate = 'official';
        foreach ($siteTemplates as $key => $template) {
            $selectedTemplate = (string) $key;
            break;
        }
    }
    $templateLayouts = json_decode((string) ($settings['site_template_layouts'] ?? ''), true);
    $templateLayouts = is_array($templateLayouts) ? $templateLayouts : [];
    $legacyHeaderLayout = json_decode((string) ($settings['site_header_layout'] ?? ''), true);
    $legacyFooterLayout = json_decode((string) ($settings['site_footer_layout'] ?? ''), true);
    if (!isset($templateLayouts[$selectedTemplate])) {
        $templateLayouts[$selectedTemplate] = [
            'header' => is_array($legacyHeaderLayout) ? $legacyHeaderLayout : $defaultHeaderLayout,
            'footer' => is_array($legacyFooterLayout) ? $legacyFooterLayout : $defaultFooterLayout,
        ];
    }
    foreach ($siteTemplates as $key => $template) {
        $layout = is_array($templateLayouts[$key] ?? null) ? $templateLayouts[$key] : [];
        $templateLayouts[$key] = [
            'header' => array_replace($defaultHeaderLayout, is_array($layout['header'] ?? null) ? $layout['header'] : []),
            'footer' => array_replace($defaultFooterLayout, is_array($layout['footer'] ?? null) ? $layout['footer'] : []),
        ];
        $templateLayouts[$key]['header']['show_home'] = false;
        $templateLayouts[$key]['header']['show_news'] = false;
    }
    $headerLayout = $templateLayouts[$selectedTemplate]['header'] ?? $defaultHeaderLayout;
    $footerLayout = $templateLayouts[$selectedTemplate]['footer'] ?? $defaultFooterLayout;
    $previewTemplates = [];
    foreach ($siteTemplates as $key => $template) {
        $previewTemplates[$key] = [
            'key' => $key,
            'name' => (string) ($template['name'] ?? $key),
            'css' => !empty($template['css']) ? url((string) $template['css']) : '',
        ];
    }
    $previewContext = [
        'bootstrapCss' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        'mdiCss' => 'https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css',
        'siteCss' => url('/assets/site.css?v=20260709-11'),
        'siteJs' => url('/assets/site.js?v=20260709-11'),
        'templates' => $previewTemplates,
        'institutionName' => (string) ($settings['institution_name'] ?? 'Заклад освіти'),
        'menu' => is_array($previewMenu ?? null) ? $previewMenu : [],
        'homeTitle' => (string) (($previewHomePage['title'] ?? '') ?: 'Головна'),
        'homeExcerpt' => (string) ($previewHomePage['excerpt'] ?? ''),
        'globalFields' => is_array($previewGlobalFields ?? null) ? $previewGlobalFields : [],
        'linkPicker' => is_array($templateLinkPicker ?? null) ? $templateLinkPicker : ['pages' => [], 'categories' => [], 'news' => [], 'media' => []],
    ];
    $activeSiteTemplate = (string) ($settings['site_template'] ?? 'official');
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Оформлення</p>
        <h1>Шаблони сайту</h1>
        <p class="page-subtitle">Редагуйте структуру хедера й футера для кожного шаблону окремо. Активний шаблон сайту вибирається в налаштуваннях.</p>
    </div>
    <div class="template-current-pill" data-current-template-name>
        <?= e($siteTemplates[$selectedTemplate]['name'] ?? $selectedTemplate) ?>
    </div>
</div>

<form class="form-grid wide" method="post" action="<?= url('/admin/templates/save') ?>" data-template-form>
    <?= \App\Core\Csrf::field() ?>
    <section class="card admin-form-card template-picker-card">
        <div class="form-section-head">
            <div>
                <h2>Шаблон для редагування</h2>
                <p class="meta">Оберіть, структуру якого шаблону змінювати. Це не перемикає активний шаблон сайту.</p>
            </div>
            <button class="button secondary compact" type="button" data-template-picker-toggle aria-expanded="true">
                <span class="mdi mdi-chevron-up" aria-hidden="true"></span><span>Згорнути</span>
            </button>
        </div>
        <div class="template-options" data-template-picker-options>
            <?php foreach ($siteTemplates as $key => $template): ?>
                <?php
                    $preview = is_array($template['preview'] ?? null) ? $template['preview'] : [];
                    $previewTop = (string) ($preview['top'] ?? '#10233f');
                    $previewHero = (string) ($preview['hero'] ?? '#dfeafb');
                    $previewLine = (string) ($preview['line'] ?? '#c8d1df');
                    $features = is_array($template['features'] ?? null) ? $template['features'] : [];
                ?>
                <label class="template-option">
                    <input type="radio" name="template_editor_key" value="<?= e($key) ?>" data-template-label="<?= e($template['name']) ?>" <?= checked($selectedTemplate === $key) ?>>
                    <span class="template-preview" aria-hidden="true" data-preview-top="<?= e($previewTop) ?>" data-preview-hero="<?= e($previewHero) ?>" data-preview-line="<?= e($previewLine) ?>">
                        <span class="template-preview-top"></span>
                        <span class="template-preview-hero"></span>
                        <span class="template-preview-lines"><span></span><span></span><span></span></span>
                    </span>
                    <span class="template-option-body">
                        <span class="template-option-title">
                            <strong><?= e($template['name']) ?></strong>
                            <span class="template-selected-badge">Редагується</span>
                        </span>
                        <span><?= e($template['description']) ?></span>
                        <?php if ($activeSiteTemplate === (string) $key): ?>
                            <span class="template-active-site-badge">Активний на сайті</span>
                        <?php endif; ?>
                        <?php if ($features): ?>
                            <span class="template-feature-list">
                                <?php foreach ($features as $feature): ?>
                                    <small><?= e((string) $feature) ?></small>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                        <code><?= e($key) ?></code>
                        <button
                            class="button secondary compact"
                            type="button"
                            data-site-template-preview
                            data-template="<?= e($key) ?>"
                            data-name="<?= e($template['name']) ?>"
                            data-description="<?= e($template['description']) ?>"
                            data-preview-top="<?= e($previewTop) ?>"
                            data-preview-hero="<?= e($previewHero) ?>"
                            data-preview-line="<?= e($previewLine) ?>"
                        >
                            <span class="mdi mdi-eye-outline" aria-hidden="true"></span><span>Переглянути</span>
                        </button>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="template-workbench-summary" aria-label="Поточний стан конструктора">
        <article class="template-summary-item template-summary-primary">
            <span class="mdi mdi-palette-outline" aria-hidden="true"></span>
            <div>
                <small>Редагується</small>
                <strong data-summary-template-name><?= e($siteTemplates[$selectedTemplate]['name'] ?? $selectedTemplate) ?></strong>
            </div>
        </article>
        <article class="template-summary-item">
            <span class="mdi mdi-menu" aria-hidden="true"></span>
            <div>
                <small>Пункти меню</small>
                <strong data-template-stat-menu>0</strong>
            </div>
        </article>
        <article class="template-summary-item">
            <span class="mdi mdi-page-layout-footer" aria-hidden="true"></span>
            <div>
                <small>Футер</small>
                <strong data-template-stat-footer>0 колонок</strong>
            </div>
        </article>
        <article class="template-summary-item">
            <span class="mdi mdi-content-save-outline" aria-hidden="true"></span>
            <div>
                <small>Стан</small>
                <strong data-template-save-state>Без змін</strong>
            </div>
        </article>
    </section>

    <div class="template-builder-layout">
        <div class="template-builder-controls">
            <section class="card admin-form-card template-layout-editor" data-template-layout-editor>
                <div class="form-section-head">
                    <div>
                        <p class="eyebrow">Конструктор</p>
                        <h2>Налаштування шаблону</h2>
                        <p class="meta">Розділіть навігацію, hero, меню під hero, мобільне меню та футер по вкладках.</p>
                    </div>
                </div>
                <div class="template-editor-tabs" role="tablist" aria-label="Розділи налаштувань шаблону">
                    <button class="button compact" type="button" data-template-editor-tab="menu" aria-selected="true">
                        <span class="mdi mdi-menu" aria-hidden="true"></span><span>Меню</span>
                    </button>
                    <button class="button secondary compact" type="button" data-template-editor-tab="hero" aria-selected="false">
                        <span class="mdi mdi-page-layout-header" aria-hidden="true"></span><span>Hero</span>
                    </button>
                    <button class="button secondary compact" type="button" data-template-editor-tab="home-hero" aria-selected="false">
                        <span class="mdi mdi-home-variant-outline" aria-hidden="true"></span><span>Hero головної</span>
                    </button>
                    <button class="button secondary compact" type="button" data-template-editor-tab="secondary" aria-selected="false">
                        <span class="mdi mdi-tab" aria-hidden="true"></span><span>Меню під hero</span>
                    </button>
                    <button class="button secondary compact" type="button" data-template-editor-tab="mobile" aria-selected="false">
                        <span class="mdi mdi-cellphone" aria-hidden="true"></span><span>Мобільне меню</span>
                    </button>
                    <button class="button secondary compact" type="button" data-template-editor-tab="footer" aria-selected="false">
                        <span class="mdi mdi-page-layout-footer" aria-hidden="true"></span><span>Футер</span>
                    </button>
                </div>
                <input type="hidden" name="site_template_layouts" data-template-layouts-json value="<?= e(json_encode($templateLayouts, JSON_UNESCAPED_UNICODE) ?: '{}') ?>">
                <input type="hidden" data-template-header-json value="<?= e(json_encode($headerLayout, JSON_UNESCAPED_UNICODE) ?: '{}') ?>">
                <div
                    class="template-header-editor"
                    data-template-header-editor
                    data-initial="<?= e(json_encode($headerLayout, JSON_UNESCAPED_UNICODE) ?: '{}') ?>"
                    data-layouts="<?= e(json_encode($templateLayouts, JSON_UNESCAPED_UNICODE) ?: '{}') ?>"
                >
                    <div class="template-editor-grid" data-template-tab-panel="menu">
                        <label>Варіант хедера
                            <select data-header-field="variant">
                                <option value="default">Стандартний</option>
                                <option value="centered">Центрований</option>
                                <option value="compact">Компактний</option>
                            </select>
                        </label>
                        <label>Текст CTA
                            <input data-header-field="cta_label" placeholder="Наприклад: Вступникам">
                        </label>
                        <div class="field-with-action">
                            <label>URL CTA
                                <input data-header-field="cta_url" placeholder="/page/admission">
                            </label>
                            <button class="button secondary compact icon-button" type="button" data-header-url-picker="cta_url" title="Обрати посилання">
                                <span class="mdi mdi-link-plus" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>
                    <div class="template-toggle-grid" data-template-tab-panel="menu">
                        <label class="check-row"><input type="checkbox" data-header-field="show_brand"> Показувати бренд</label>
                    </div>
                    <div class="template-link-quick-add" data-menu-quick-add data-template-tab-panel="menu">
                        <div>
                            <strong>Швидко додати в меню</strong>
                            <span>Сторінки, категорії новин або окремі новини можна додати одним кліком.</span>
                        </div>
                        <label>Куди додати
                            <select data-menu-parent></select>
                        </label>
                        <button class="button secondary compact" type="button" data-menu-picker-open>
                            <span class="mdi mdi-link-plus" aria-hidden="true"></span><span>Обрати посилання</span>
                        </button>
                    </div>
                    <div class="template-menu-presets" aria-label="Швидкі набори меню" data-template-tab-panel="menu">
                        <button class="button secondary compact" type="button" data-menu-preset="core">
                            <span class="mdi mdi-home-plus-outline" aria-hidden="true"></span><span>Головна + Новини</span>
                        </button>
                        <button class="button secondary compact" type="button" data-menu-preset="pages">
                            <span class="mdi mdi-file-tree-outline" aria-hidden="true"></span><span>Опубліковані сторінки</span>
                        </button>
                        <button class="button secondary compact" type="button" data-menu-preset="clear">
                            <span class="mdi mdi-menu-open" aria-hidden="true"></span><span>Очистити меню</span>
                        </button>
                    </div>
                    <div class="template-menu-blueprints" aria-label="Готові шаблони меню" data-template-tab-panel="menu">
                        <div>
                            <strong>Готові шаблони меню</strong>
                            <span>Спочатку оберіть шаблон у модальному вікні, перегляньте структуру і лише потім застосуйте.</span>
                        </div>
                        <button class="template-blueprint-open" type="button" data-template-library-open="menu" data-template-library-target="main">
                            <span class="mdi mdi-view-grid-plus-outline" aria-hidden="true"></span>
                            <strong>Обрати шаблон</strong>
                            <small>Відкрити вибір з попереднім переглядом</small>
                        </button>
                    </div>
                    <div class="template-header-extra-grid" data-template-tab-panel="hero" hidden>
                        <section class="template-subeditor">
                            <div class="template-subeditor-head">
                                <div>
                                    <strong>Hero під хедером</strong>
                                    <span>Окремий перший блок одразу після навігації.</span>
                                </div>
                                <label class="check-row"><input type="checkbox" data-header-field="hero_enabled"> Увімкнути</label>
                            </div>
                            <div class="template-editor-grid template-editor-grid-2">
                                <label>Вигляд
                                    <select data-header-field="hero_variant">
                                        <option value="default">Стандартний</option>
                                        <option value="accent">Акцентний</option>
                                        <option value="compact">Компактний</option>
                                        <option value="fullscreen">На весь екран</option>
                                    </select>
                                </label>
                                <label>Заголовок
                                    <input data-header-field="hero_title" placeholder="Ласкаво просимо">
                                </label>
                                <label class="template-editor-wide">Текст
                                    <textarea data-header-field="hero_text" rows="3" placeholder="Короткий вступний текст для відвідувачів"></textarea>
                                </label>
                                <label>Текст кнопки
                                    <input data-header-field="hero_button_label" placeholder="Детальніше">
                                </label>
                                <div class="field-with-action">
                                    <label>URL кнопки
                                        <input data-header-field="hero_button_url" placeholder="/page/about">
                                    </label>
                                    <button class="button secondary compact icon-button" type="button" data-header-url-picker="hero_button_url" title="Обрати посилання">
                                        <span class="mdi mdi-link-plus" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="template-hero-background" data-hero-background-controls="hero">
                                <div class="template-subeditor-head">
                                    <div>
                                        <strong>Фонове зображення</strong>
                                        <span>Оберіть зображення з медіафайлів і налаштуйте його відображення.</span>
                                    </div>
                                    <label class="check-row"><input type="checkbox" data-header-field="hero_overlay_enabled"> Затемнення</label>
                                </div>
                                <div class="template-editor-grid template-editor-grid-2">
                                    <div class="template-hero-image-field template-editor-wide">
                                        <input type="hidden" data-header-field="hero_background_image">
                                        <div class="template-hero-image-summary" data-hero-background-summary="hero">Фонове зображення не вибрано</div>
                                        <div class="template-editor-list-actions">
                                            <button class="button secondary compact" type="button" data-hero-background-open="hero">
                                                <span class="mdi mdi-image-search-outline" aria-hidden="true"></span><span>Обрати з медіафайлів</span>
                                            </button>
                                            <button class="button secondary compact" type="button" data-hero-background-clear="hero">
                                                <span class="mdi mdi-close" aria-hidden="true"></span><span>Очистити</span>
                                            </button>
                                        </div>
                                    </div>
                                    <label>Позиція
                                        <select data-header-field="hero_background_position">
                                            <option value="center center">По центру</option>
                                            <option value="center top">Зверху</option>
                                            <option value="center bottom">Знизу</option>
                                            <option value="left center">Ліворуч</option>
                                            <option value="right center">Праворуч</option>
                                        </select>
                                    </label>
                                    <label>Розмір
                                        <select data-header-field="hero_background_size">
                                            <option value="cover">Заповнити</option>
                                            <option value="contain">Вмістити</option>
                                            <option value="auto">Оригінальний</option>
                                        </select>
                                    </label>
                                    <label>Повтор
                                        <select data-header-field="hero_background_repeat">
                                            <option value="no-repeat">Не повторювати</option>
                                            <option value="repeat">Повторювати</option>
                                            <option value="repeat-x">По горизонталі</option>
                                            <option value="repeat-y">По вертикалі</option>
                                        </select>
                                    </label>
                                    <label>Сила затемнення
                                        <input type="range" min="0" max="80" step="5" data-header-field="hero_overlay_opacity">
                                    </label>
                                </div>
                                <div class="template-hero-background-preview" data-hero-background-preview="hero"></div>
                            </div>
                            <div class="template-menu-blueprints template-hero-blueprints" aria-label="Готові шаблони hero">
                                <div>
                                    <strong>Готові шаблони hero</strong>
                                    <span>Оберіть шаблон у модальному вікні, перегляньте перший екран і застосуйте.</span>
                                </div>
                                <button class="template-blueprint-open" type="button" data-template-library-open="hero">
                                    <span class="mdi mdi-view-grid-plus-outline" aria-hidden="true"></span>
                                    <strong>Обрати шаблон</strong>
                                    <small>Відкрити вибір з попереднім переглядом</small>
                                </button>
                            </div>
                        </section>
                    </div>
                    <div class="template-header-extra-grid" data-template-tab-panel="home-hero" hidden>
                        <section class="template-subeditor">
                            <div class="template-subeditor-head">
                                <div>
                                    <strong>Hero головної сторінки</strong>
                                    <span>Окремий блок тільки для головної сторінки, незалежний від hero під хедером.</span>
                                </div>
                                <label class="check-row"><input type="checkbox" data-header-field="home_hero_enabled"> Увімкнути</label>
                            </div>
                            <div class="template-editor-grid template-editor-grid-2">
                                <label>Вигляд
                                    <select data-header-field="home_hero_variant">
                                        <option value="default">Стандартний</option>
                                        <option value="accent">Акцентний</option>
                                        <option value="compact">Компактний</option>
                                        <option value="fullscreen">На весь екран</option>
                                    </select>
                                </label>
                                <label>Заголовок
                                    <input data-header-field="home_hero_title" placeholder="Ласкаво просимо">
                                </label>
                                <label class="template-editor-wide">Текст
                                    <textarea data-header-field="home_hero_text" rows="3" placeholder="Короткий вступний текст для головної сторінки"></textarea>
                                </label>
                                <label>Текст кнопки
                                    <input data-header-field="home_hero_button_label" placeholder="Детальніше">
                                </label>
                                <div class="field-with-action">
                                    <label>URL кнопки
                                        <input data-header-field="home_hero_button_url" placeholder="/page/about">
                                    </label>
                                    <button class="button secondary compact icon-button" type="button" data-header-url-picker="home_hero_button_url" title="Обрати посилання">
                                        <span class="mdi mdi-link-plus" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="template-hero-background" data-hero-background-controls="home">
                                <div class="template-subeditor-head">
                                    <div>
                                        <strong>Фонове зображення</strong>
                                        <span>Окремий фон для hero головної сторінки.</span>
                                    </div>
                                    <label class="check-row"><input type="checkbox" data-header-field="home_hero_overlay_enabled"> Затемнення</label>
                                </div>
                                <div class="template-editor-grid template-editor-grid-2">
                                    <div class="template-hero-image-field template-editor-wide">
                                        <input type="hidden" data-header-field="home_hero_background_image">
                                        <div class="template-hero-image-summary" data-hero-background-summary="home">Фонове зображення не вибрано</div>
                                        <div class="template-editor-list-actions">
                                            <button class="button secondary compact" type="button" data-hero-background-open="home">
                                                <span class="mdi mdi-image-search-outline" aria-hidden="true"></span><span>Обрати з медіафайлів</span>
                                            </button>
                                            <button class="button secondary compact" type="button" data-hero-background-clear="home">
                                                <span class="mdi mdi-close" aria-hidden="true"></span><span>Очистити</span>
                                            </button>
                                        </div>
                                    </div>
                                    <label>Позиція
                                        <select data-header-field="home_hero_background_position">
                                            <option value="center center">По центру</option>
                                            <option value="center top">Зверху</option>
                                            <option value="center bottom">Знизу</option>
                                            <option value="left center">Ліворуч</option>
                                            <option value="right center">Праворуч</option>
                                        </select>
                                    </label>
                                    <label>Розмір
                                        <select data-header-field="home_hero_background_size">
                                            <option value="cover">Заповнити</option>
                                            <option value="contain">Вмістити</option>
                                            <option value="auto">Оригінальний</option>
                                        </select>
                                    </label>
                                    <label>Повтор
                                        <select data-header-field="home_hero_background_repeat">
                                            <option value="no-repeat">Не повторювати</option>
                                            <option value="repeat">Повторювати</option>
                                            <option value="repeat-x">По горизонталі</option>
                                            <option value="repeat-y">По вертикалі</option>
                                        </select>
                                    </label>
                                    <label>Сила затемнення
                                        <input type="range" min="0" max="80" step="5" data-header-field="home_hero_overlay_opacity">
                                    </label>
                                </div>
                                <div class="template-hero-background-preview" data-hero-background-preview="home"></div>
                            </div>
                            <div class="template-menu-blueprints template-hero-blueprints" aria-label="Готові шаблони hero головної">
                                <div>
                                    <strong>Готові шаблони hero</strong>
                                    <span>Оберіть шаблон у модальному вікні, перегляньте перший екран і застосуйте до головної.</span>
                                </div>
                                <button class="template-blueprint-open" type="button" data-template-library-open="hero" data-template-library-target="home">
                                    <span class="mdi mdi-view-grid-plus-outline" aria-hidden="true"></span>
                                    <strong>Обрати шаблон</strong>
                                    <small>Відкрити вибір з попереднім переглядом</small>
                                </button>
                            </div>
                        </section>
                    </div>
                    <section class="template-subeditor" data-template-tab-panel="secondary" hidden>
                        <div class="template-subeditor-head">
                            <div>
                                <strong>Меню під hero</strong>
                                <span>Швидкі посилання окремим рядком під hero-блоком.</span>
                            </div>
                            <label class="check-row"><input type="checkbox" data-header-field="secondary_enabled"> Увімкнути</label>
                        </div>
                        <div class="template-editor-grid template-editor-grid-2">
                            <label>Стиль
                                <select data-header-field="secondary_variant">
                                    <option value="pills">Плашки</option>
                                    <option value="tabs">Вкладки</option>
                                    <option value="plain">Простий</option>
                                </select>
                            </label>
                            <div class="template-editor-list-actions">
                                <button class="button secondary compact" type="button" data-secondary-add-section>
                                    <span class="mdi mdi-format-list-group" aria-hidden="true"></span><span>Секція</span>
                                </button>
                                <button class="button secondary compact" type="button" data-secondary-add-link>
                                    <span class="mdi mdi-plus" aria-hidden="true"></span><span>Пункт</span>
                                </button>
                            </div>
                        </div>
                        <div class="template-menu-blueprints template-secondary-blueprints" aria-label="Готові шаблони меню під hero">
                            <div>
                                <strong>Готові шаблони меню</strong>
                                <span>Спочатку оберіть шаблон у модальному вікні, перегляньте структуру і лише потім застосуйте.</span>
                            </div>
                            <button class="template-blueprint-open" type="button" data-template-library-open="menu" data-template-library-target="secondary">
                                <span class="mdi mdi-view-grid-plus-outline" aria-hidden="true"></span>
                                <strong>Обрати шаблон</strong>
                                <small>Відкрити вибір з попереднім переглядом</small>
                            </button>
                        </div>
                        <div class="template-editor-list template-secondary-list" data-secondary-links></div>
                    </section>
                    <section class="template-subeditor" data-template-tab-panel="mobile" hidden>
                        <div class="template-subeditor-head">
                            <div>
                                <strong>Мобільне меню</strong>
                                <span>Окремі налаштування поведінки та вигляду меню на малих екранах.</span>
                            </div>
                        </div>
                        <div class="template-editor-grid template-editor-grid-2">
                            <label>Тип мобільного меню
                                <select data-header-field="mobile_variant">
                                    <option value="drawer">Згортання під хедером</option>
                                    <option value="panel">Панель на всю ширину</option>
                                    <option value="compact">Компактний список</option>
                                </select>
                            </label>
                            <label>Наповнення
                                <select data-header-field="mobile_source">
                                    <option value="main">Основне меню</option>
                                    <option value="secondary">Меню під hero</option>
                                    <option value="both">Основне + під hero</option>
                                </select>
                            </label>
                            <label>Текст кнопки
                                <input data-header-field="mobile_label" placeholder="Меню">
                            </label>
                        </div>
                        <div class="template-toggle-grid">
                            <label class="check-row"><input type="checkbox" data-header-field="mobile_show_brand"> Показувати бренд у мобільному меню</label>
                            <label class="check-row"><input type="checkbox" data-header-field="mobile_show_cta"> Показувати CTA у мобільному меню</label>
                        </div>
                    </section>
                    <div class="template-editor-list-head" data-template-tab-panel="menu">
                        <strong>Структура меню <span class="template-menu-issues" data-menu-issues hidden></span></strong>
                        <div class="template-editor-list-actions">
                            <label class="template-menu-search">Пошук у меню
                                <input type="search" data-menu-search placeholder="Назва, URL або іконка">
                            </label>
                            <button class="button secondary compact" type="button" data-menu-search-clear hidden>
                                <span class="mdi mdi-close" aria-hidden="true"></span><span>Очистити</span>
                            </button>
                            <button class="button secondary compact" type="button" data-menu-issues-only hidden aria-pressed="false">
                                <span class="mdi mdi-alert-circle-outline" aria-hidden="true"></span><span>Проблеми</span>
                            </button>
                            <button class="button secondary compact" type="button" data-menu-next-issue hidden>
                                <span class="mdi mdi-crosshairs-gps" aria-hidden="true"></span><span>Наступна</span>
                            </button>
                            <button class="button secondary compact" type="button" data-menu-expand-all>
                                <span class="mdi mdi-arrow-expand-vertical" aria-hidden="true"></span><span>Розгорнути</span>
                            </button>
                            <button class="button secondary compact" type="button" data-menu-collapse-all>
                                <span class="mdi mdi-arrow-collapse-vertical" aria-hidden="true"></span><span>Згорнути</span>
                            </button>
                            <button class="button secondary compact" type="button" data-header-add-section>
                                <span class="mdi mdi-format-list-group" aria-hidden="true"></span><span>Секція</span>
                            </button>
                            <button class="button secondary compact" type="button" data-header-add-link>
                                <span class="mdi mdi-plus" aria-hidden="true"></span><span>Власний пункт</span>
                            </button>
                        </div>
                    </div>
                    <div class="template-editor-list" data-header-links data-template-tab-panel="menu"></div>
                </div>
            </section>

            <section class="card admin-form-card template-layout-editor" data-template-footer-wrap data-template-tab-panel="footer" hidden>
                <div class="form-section-head">
                    <div>
                        <p class="eyebrow">Підвал</p>
                        <h2>Футер сайту</h2>
                        <p class="meta">Зберіть футер із колонок Bootstrap-сітки. Кожна колонка має власні пункти.</p>
                    </div>
                </div>
                <input type="hidden" data-template-footer-json value="<?= e(json_encode($footerLayout, JSON_UNESCAPED_UNICODE) ?: '{}') ?>">
                <div
                    class="template-footer-editor"
                    data-template-footer-editor
                    data-initial="<?= e(json_encode($footerLayout, JSON_UNESCAPED_UNICODE) ?: '{}') ?>"
                >
                    <div class="template-editor-grid template-editor-grid-2">
                        <label>Варіант футера
                            <select data-footer-field="variant">
                                <option value="default">Стандартний</option>
                                <option value="light">Світлий</option>
                                <option value="dark">Темний</option>
                            </select>
                        </label>
                        <label>Нижній текст
                            <input data-footer-field="bottom_text" placeholder="© Заклад освіти">
                        </label>
                    </div>
                    <div class="template-menu-blueprints template-footer-blueprints" aria-label="Готові шаблони футера">
                        <div>
                            <strong>Готові шаблони футера</strong>
                            <span>Оберіть шаблон у модальному вікні, перегляньте колонки і застосуйте.</span>
                        </div>
                        <button class="template-blueprint-open" type="button" data-template-library-open="footer">
                            <span class="mdi mdi-view-grid-plus-outline" aria-hidden="true"></span>
                            <strong>Обрати шаблон</strong>
                            <small>Відкрити вибір з попереднім переглядом</small>
                        </button>
                    </div>
                    <div class="template-editor-list-head">
                        <strong>Колонки футера</strong>
                        <button class="button secondary compact" type="button" data-footer-add-column>
                            <span class="mdi mdi-plus" aria-hidden="true"></span><span>Колонка</span>
                        </button>
                    </div>
                    <div class="template-footer-columns" data-footer-columns></div>
                </div>
            </section>

            <div class="template-save-bar">
                <div>
                    <strong data-template-save-title>Готово до збереження</strong>
                    <span data-template-save-copy>Зміни застосуються до вибраного шаблону після збереження.</span>
                </div>
                <div class="template-save-actions">
                    <button class="button secondary" type="button" data-template-revert disabled>
                        <span class="mdi mdi-restore" aria-hidden="true"></span><span>Скасувати</span>
                    </button>
                    <button type="submit"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span>Зберегти шаблон</span></button>
                </div>
            </div>
        </div>

        <aside class="template-builder-preview">
            <section class="card admin-form-card template-preview-panel">
                <div class="form-section-head">
                    <div>
                        <p class="eyebrow">Перегляд</p>
                        <h2>Головна сторінка</h2>
                        <p class="meta">Показує поточний шаблон і незбережені зміни.</p>
                    </div>
                    <div class="template-preview-head-actions">
                        <div class="template-preview-modes" role="group" aria-label="Розмір перегляду">
                            <button class="button compact" type="button" data-template-preview-mode="desktop" title="Desktop"><span class="mdi mdi-monitor" aria-hidden="true"></span></button>
                            <button class="button secondary compact" type="button" data-template-preview-mode="tablet" title="Tablet"><span class="mdi mdi-tablet" aria-hidden="true"></span></button>
                            <button class="button secondary compact" type="button" data-template-preview-mode="mobile" title="Mobile"><span class="mdi mdi-cellphone" aria-hidden="true"></span></button>
                        </div>
                        <button class="button secondary compact template-preview-collapse-toggle" type="button" data-template-preview-collapse aria-expanded="true" aria-controls="templateHomePreviewShell">
                            <span class="mdi mdi-chevron-up" aria-hidden="true"></span><span>Згорнути</span>
                        </button>
                    </div>
                </div>
                <div class="template-home-preview-shell" id="templateHomePreviewShell" data-template-preview-shell data-preview-mode="desktop">
                    <iframe
                        title="Попередній перегляд головної сторінки"
                        data-template-home-preview
                        data-context="<?= e(json_encode($previewContext, JSON_UNESCAPED_UNICODE) ?: '{}') ?>"
                    ></iframe>
                </div>
            </section>
        </aside>
    </div>
</form>

<div class="modal fade" id="templateMenuBlueprintModal" tabindex="-1" aria-labelledby="templateMenuBlueprintTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1" data-menu-template-target-label>Готовий шаблон меню</p>
                    <h2 class="modal-title h5" id="templateMenuBlueprintTitle" data-menu-template-title>Попередній перегляд</h2>
                    <p class="meta mb-0" data-menu-template-description></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
            </div>
            <div class="modal-body">
                <div class="template-blueprint-modal-grid">
                    <div class="template-blueprint-choice-list" data-template-blueprint-list></div>
                    <div class="template-menu-visual template-menu-blueprint-preview">
                        <div class="template-menu-visual-head">
                            <strong data-template-preview-label>Попередній перегляд</strong>
                            <span data-menu-template-count></span>
                        </div>
                        <div class="template-menu-visual-body" data-menu-template-preview></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button" data-menu-template-apply>
                    <span class="mdi mdi-check" aria-hidden="true"></span><span>Застосувати</span>
                </button>
                <button type="button" class="button secondary" data-bs-dismiss="modal"><span class="mdi mdi-close" aria-hidden="true"></span><span>Закрити</span></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="templateMenuPickerModal" tabindex="-1" aria-labelledby="templateMenuPickerTitle" aria-hidden="true" data-link-picker-url="<?= url('/admin/templates/link-picker') ?>">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1">Меню</p>
                    <h2 class="modal-title h5" id="templateMenuPickerTitle">Обрати посилання</h2>
                    <p class="meta mb-0" data-menu-picker-target>Пункт буде додано у верхній рівень меню.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
            </div>
            <div class="modal-body">
                <div class="template-menu-picker" data-menu-picker>
                    <div class="template-menu-picker-tabs" role="tablist" aria-label="Тип посилання">
                        <button class="button compact" type="button" data-menu-picker-type="pages"><span class="mdi mdi-file-document-outline" aria-hidden="true"></span><span>Сторінки</span></button>
                        <button class="button secondary compact" type="button" data-menu-picker-type="categories"><span class="mdi mdi-shape-outline" aria-hidden="true"></span><span>Категорії</span></button>
                        <button class="button secondary compact" type="button" data-menu-picker-type="news"><span class="mdi mdi-newspaper-variant-outline" aria-hidden="true"></span><span>Новини</span></button>
                        <button class="button secondary compact" type="button" data-menu-picker-type="media"><span class="mdi mdi-folder-image" aria-hidden="true"></span><span>Медіафайли</span></button>
                    </div>
                    <div class="template-menu-picker-filters">
                        <label class="template-menu-picker-search">Пошук
                            <input type="search" data-menu-picker-search placeholder="Назва, slug, файл або текст">
                        </label>
                        <label data-menu-picker-filter="status">Статус
                            <select data-menu-picker-status>
                                <option value="published">Опубліковані</option>
                                <option value="draft">Чернетки</option>
                                <option value="">Усі</option>
                            </select>
                        </label>
                        <label data-menu-picker-filter="scope" hidden>Категорії
                            <select data-menu-picker-scope>
                                <option value="">Усі</option>
                                <option value="root">Кореневі</option>
                                <option value="children">Дочірні</option>
                            </select>
                        </label>
                        <label data-menu-picker-filter="category" hidden>Категорія новини
                            <select data-menu-picker-category>
                                <option value="">Усі категорії</option>
                                <?php foreach (($templatePickerCategories ?? []) as $category): ?>
                                    <option value="<?= e((string) ($category['id'] ?? '')) ?>"><?= e((string) ($category['label'] ?? $category['category'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="template-menu-picker-status" data-menu-picker-status-text>Оберіть тип посилання.</div>
                    <div class="template-menu-picker-list" data-menu-picker-list></div>
                    <button class="button secondary compact template-menu-picker-more" type="button" data-menu-picker-more hidden>
                        <span class="mdi mdi-chevron-down" aria-hidden="true"></span><span>Показати ще</span>
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button" data-menu-picker-add-selected disabled>
                    <span class="mdi mdi-plus-box-multiple-outline" aria-hidden="true"></span><span data-menu-picker-add-label>Додати вибрані</span>
                </button>
                <button type="button" class="button secondary" data-bs-dismiss="modal"><span class="mdi mdi-close" aria-hidden="true"></span><span>Закрити</span></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="templateHeroBackgroundModal" tabindex="-1" aria-labelledby="templateHeroBackgroundTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1">Фонове зображення</p>
                    <h2 class="modal-title h5" id="templateHeroBackgroundTitle" data-hero-background-title>Обрати з медіафайлів</h2>
                    <p class="meta mb-0">Виберіть зображення для hero-блоку. Після вибору можна налаштувати позицію, розмір і затемнення.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
            </div>
            <div class="modal-body">
                <div class="template-hero-media-picker">
                    <label>Пошук
                        <input type="search" data-hero-background-search placeholder="Назва або шлях файлу">
                    </label>
                    <div class="template-menu-picker-status" data-hero-background-status></div>
                    <div class="template-hero-media-grid" data-hero-background-grid></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button secondary" data-hero-background-modal-clear>
                    <span class="mdi mdi-close" aria-hidden="true"></span><span>Без зображення</span>
                </button>
                <button type="button" class="button secondary" data-bs-dismiss="modal"><span class="mdi mdi-close" aria-hidden="true"></span><span>Закрити</span></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="templateIconPickerModal" tabindex="-1" aria-labelledby="templateIconPickerTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1">Іконка</p>
                    <h2 class="modal-title h5" id="templateIconPickerTitle">Обрати MDI іконку</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
            </div>
            <div class="modal-body">
                <div class="template-icon-picker">
                    <label>Пошук
                        <input type="search" data-icon-picker-search placeholder="home, news, calendar...">
                    </label>
                    <div class="template-menu-picker-status" data-icon-picker-status></div>
                    <div class="template-icon-picker-grid" data-icon-picker-grid></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button secondary" data-icon-picker-clear><span class="mdi mdi-close" aria-hidden="true"></span><span>Без іконки</span></button>
                <button type="button" class="button secondary" data-bs-dismiss="modal"><span class="mdi mdi-close" aria-hidden="true"></span><span>Закрити</span></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="siteTemplatePreviewModal" tabindex="-1" aria-labelledby="siteTemplatePreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5" id="siteTemplatePreviewTitle">Попередній перегляд</h2>
                    <p class="meta mb-0" data-template-preview-description></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
            </div>
            <div class="modal-body">
                <div class="site-template-preview-large" data-template-preview-large>
                    <div class="template-preview-top"></div>
                    <div class="template-preview-hero"></div>
                    <div class="template-preview-grid">
                        <span></span><span></span><span></span>
                    </div>
                    <div class="template-preview-lines"><span></span><span></span><span></span></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button secondary" data-bs-dismiss="modal"><span class="mdi mdi-close" aria-hidden="true"></span><span>Закрити</span></button>
            </div>
        </div>
    </div>
</div>

<script src="<?= url('/assets/admin-templates.js') ?>"></script>
