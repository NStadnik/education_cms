<?php
    $defaultHeaderLayout = [
        'variant' => 'default',
        'show_brand' => true,
        'show_home' => false,
        'show_news' => false,
        'links' => [],
        'cta_label' => '',
        'cta_url' => '',
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
        'siteCss' => url('/assets/site.css'),
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
                        <p class="eyebrow">Шапка</p>
                        <h2>Хедер сайту</h2>
                        <p class="meta">Налаштуйте бренд, навігацію та кнопку дії для вибраного шаблону.</p>
                    </div>
                </div>
                <input type="hidden" name="site_template_layouts" data-template-layouts-json value="<?= e(json_encode($templateLayouts, JSON_UNESCAPED_UNICODE) ?: '{}') ?>">
                <input type="hidden" data-template-header-json value="<?= e(json_encode($headerLayout, JSON_UNESCAPED_UNICODE) ?: '{}') ?>">
                <div
                    class="template-header-editor"
                    data-template-header-editor
                    data-initial="<?= e(json_encode($headerLayout, JSON_UNESCAPED_UNICODE) ?: '{}') ?>"
                    data-layouts="<?= e(json_encode($templateLayouts, JSON_UNESCAPED_UNICODE) ?: '{}') ?>"
                >
                    <div class="template-editor-grid">
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
                        <label>URL CTA
                            <input data-header-field="cta_url" placeholder="/page/admission">
                        </label>
                    </div>
                    <div class="template-toggle-grid">
                        <label class="check-row"><input type="checkbox" data-header-field="show_brand"> Показувати бренд</label>
                    </div>
                    <div class="template-link-quick-add" data-menu-quick-add>
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
                    <div class="template-menu-presets" aria-label="Швидкі набори меню">
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
                    <div class="template-editor-list-head">
                        <strong>Структура меню</strong>
                        <button class="button secondary compact" type="button" data-header-add-link>
                            <span class="mdi mdi-plus" aria-hidden="true"></span><span>Власний пункт</span>
                        </button>
                    </div>
                    <div class="template-editor-list" data-header-links></div>
                </div>
            </section>

            <section class="card admin-form-card template-layout-editor" data-template-footer-wrap>
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
                <button type="submit"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span>Зберегти шаблон</span></button>
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
                    <div class="template-preview-modes" role="group" aria-label="Розмір перегляду">
                        <button class="button compact" type="button" data-template-preview-mode="desktop" title="Desktop"><span class="mdi mdi-monitor" aria-hidden="true"></span></button>
                        <button class="button secondary compact" type="button" data-template-preview-mode="tablet" title="Tablet"><span class="mdi mdi-tablet" aria-hidden="true"></span></button>
                        <button class="button secondary compact" type="button" data-template-preview-mode="mobile" title="Mobile"><span class="mdi mdi-cellphone" aria-hidden="true"></span></button>
                    </div>
                </div>
                <div class="template-home-preview-shell" data-template-preview-shell data-preview-mode="desktop">
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
