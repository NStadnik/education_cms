<?php
    $isEdit = !empty($item['id']);
    $blocks = $item ? json_decode($item['blocks_json'], true) : [];
    $layoutBlocks = array_values(array_filter($blocks ?: [], static fn ($block): bool => is_array($block) && ($block['type'] ?? '') === 'layout'));
    $hasLayoutBlocks = !empty($layoutBlocks);
    $blockCount = count($layoutBlocks ?: ($blocks ?: []));
    $statusLabel = (($item['status'] ?? 'draft') === 'published') ? 'Опубліковано' : 'Чернетка';
    $textToHtml = static function (string $text): string {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if ($text !== strip_tags($text)) {
            return $text;
        }

        $paragraphs = preg_split('/\R{2,}/', $text) ?: [];
        $html = [];
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph !== '') {
                $html[] = '<p>' . nl2br(e($paragraph), false) . '</p>';
            }
        }

        return implode("\n", $html);
    };
    $simpleTextParts = [];
    foreach (($blocks ?: []) as $block) {
        if (!is_array($block)) {
            continue;
        }
        if (($block['type'] ?? '') === 'layout') {
            $sectionParts = [];
            $sectionTitle = trim((string) ($block['title'] ?? ''));
            if ($sectionTitle !== '') {
                $sectionParts[] = '<h2>' . e($sectionTitle) . '</h2>';
            }
            foreach (($block['rows'] ?? []) as $row) {
                foreach (($row['columns'] ?? []) as $column) {
                    foreach (($column['cards'] ?? []) as $card) {
                        $cardParts = [];
                        $cardTitle = trim((string) ($card['title'] ?? ''));
                        $cardText = trim((string) ($card['text'] ?? ''));
                        $cardImage = trim((string) ($card['image'] ?? ''));
                        $buttonText = trim((string) ($card['button_text'] ?? ''));
                        $buttonUrl = trim((string) ($card['button_url'] ?? ''));
                        $cardLinks = [];
                        foreach (($card['links'] ?? []) as $link) {
                            if (!is_array($link)) {
                                continue;
                            }
                            $label = trim((string) ($link['label'] ?? $link['text'] ?? $link['title'] ?? ''));
                            $href = trim((string) ($link['url'] ?? $link['href'] ?? ''));
                            if ($label !== '' && $href !== '') {
                                $cardLinks[] = ['label' => $label, 'url' => $href];
                            }
                        }
                        if (!$cardLinks && $buttonText !== '' && $buttonUrl !== '') {
                            $cardLinks[] = ['label' => $buttonText, 'url' => $buttonUrl];
                        }
                        if ($cardImage !== '') {
                            $cardParts[] = '<p><img src="' . e($cardImage) . '" alt="' . e($cardTitle) . '"></p>';
                        }
                        if ($cardTitle !== '') {
                            $cardParts[] = '<h3>' . e($cardTitle) . '</h3>';
                        }
                        if ($cardText !== '') {
                            $cardParts[] = $textToHtml($cardText);
                        }
                        if ($cardLinks) {
                            $cardParts[] = '<p>' . implode('<br>', array_map(static function (array $link): string {
                                return '<a href="' . e($link['url']) . '">' . e($link['label']) . '</a>';
                            }, $cardLinks)) . '</p>';
                        }
                        if ($cardParts) {
                            $sectionParts[] = implode("\n", $cardParts);
                        }
                    }
                }
            }
            if ($sectionParts) {
                $simpleTextParts[] = implode("\n\n", $sectionParts);
            }
            continue;
        }
        $blockParts = [];
        $blockTitle = trim((string) ($block['title'] ?? ''));
        $blockText = trim((string) ($block['text'] ?? ''));
        if ($blockTitle !== '') {
            $blockParts[] = '<h2>' . e($blockTitle) . '</h2>';
        }
        if ($blockText !== '') {
            $blockParts[] = $textToHtml($blockText);
        }
        $simpleTextParts[] = trim(implode("\n", $blockParts));
    }
    $simpleText = trim(implode("\n\n", array_filter($simpleTextParts)));
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Контент сайту</p>
        <h1><?= $isEdit ? 'Редагувати сторінку' : 'Нова сторінка' ?></h1>
        <p class="page-subtitle">Заповніть назву, опис і текст сторінки. Розширений конструктор доступний окремо.</p>
    </div>
    <div class="form-actions">
        <a class="button secondary" href="<?= url('/admin/pages') ?>"><span class="mdi mdi-arrow-left" aria-hidden="true"></span><span>До списку</span></a>
        <?php if (!empty($item['slug']) && ($item['status'] ?? '') === 'published'): ?>
            <a class="button secondary" href="<?= url($item['slug'] === 'home' ? '/' : '/page/' . $item['slug']) ?>"><span class="mdi mdi-eye-outline" aria-hidden="true"></span><span>Переглянути</span></a>
        <?php endif; ?>
    </div>
</div>

<div class="metrics page-edit-metrics">
    <div class="metric"><div><span>Статус</span><strong><?= e($statusLabel) ?></strong></div><span class="mdi mdi-circle-edit-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Секцій</span><strong data-page-block-count><?= e((string) $blockCount) ?></strong></div><span class="mdi mdi-view-grid-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Порядок</span><strong><?= e((string) ($item['sort_order'] ?? 0)) ?></strong></div><span class="mdi mdi-sort-numeric-ascending metric-icon" aria-hidden="true"></span></div>
</div>

<form class="page-editor-form" method="post" action="<?= url('/admin/pages/save') ?>">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= e((string) ($item['id'] ?? '')) ?>">

    <div class="editor-layout">
        <section class="card admin-form-card page-editor-main">
            <div class="form-section-head">
                <div>
                    <p class="eyebrow">Сторінка</p>
                    <h2>Основний вміст</h2>
                    <p class="meta">Ці дані відображаються на публічній сторінці та в меню.</p>
                </div>
            </div>

            <div class="form-grid wide">
                <div class="page-title-grid">
                    <label>Назва<input name="title" value="<?= e($item['title'] ?? '') ?>" required placeholder="Назва сторінки"></label>
                    <label>Короткий опис<textarea class="textarea-small" name="excerpt" placeholder="Опис для першого екрану або списків сторінок"><?= e($item['excerpt'] ?? '') ?></textarea></label>
                </div>
                <input type="hidden" name="editor_mode" data-editor-mode value="<?= $hasLayoutBlocks ? 'advanced' : 'simple' ?>">
                <div class="page-editor-mode-switch" data-editor-mode-switch>
                    <button class="button <?= $hasLayoutBlocks ? 'secondary' : '' ?> compact" type="button" data-editor-mode-button="simple">
                        <span class="mdi mdi-text-box-edit-outline" aria-hidden="true"></span><span>Простий редактор</span>
                    </button>
                    <button class="button <?= $hasLayoutBlocks ? '' : 'secondary' ?> compact" type="button" data-editor-mode-button="advanced">
                        <span class="mdi mdi-view-grid-plus-outline" aria-hidden="true"></span><span>Розширений конструктор</span>
                    </button>
                </div>
                <section class="page-simple-editor" data-simple-editor-panel <?= $hasLayoutBlocks ? 'hidden' : '' ?>>
                    <div class="form-section-head">
                        <div>
                            <h2>Текст сторінки</h2>
                            <p class="meta">Для звичайної сторінки достатньо заголовка, короткого опису і цього тексту.</p>
                        </div>
                    </div>
                    <label>Вміст
                        <textarea class="textarea-large" name="blocks_text" data-tiptap-editor placeholder="Введіть текст сторінки. Можна вставляти HTML з базовим форматуванням."><?= e($simpleText) ?></textarea>
                    </label>
                </section>
                <input type="hidden" name="layout_json" data-layout-json>
                <div class="layout-builder page-advanced-editor" data-advanced-editor-panel data-layout-builder data-initial="<?= e(json_encode($blocks ?: [], JSON_UNESCAPED_UNICODE) ?: '[]') ?>" <?= $hasLayoutBlocks ? '' : 'hidden' ?>>
                    <div class="layout-builder-head">
                        <div>
                            <strong>Редактор секцій</strong>
                            <p class="meta mb-0">Створюйте секції, ряди, колонки та картки. На сайті вони рендеряться через Bootstrap grid.</p>
                        </div>
                        <div class="layout-builder-actions">
                            <button class="button secondary compact" type="button" data-layout-import-export-open>
                                <span class="mdi mdi-code-json" aria-hidden="true"></span><span>Імпорт/експорт</span>
                            </button>
                            <button class="button compact" type="button" data-layout-open-section-picker>
                                <span class="mdi mdi-view-grid-plus-outline" aria-hidden="true"></span><span>Додати секцію</span>
                            </button>
                        </div>
                    </div>
                    <div class="layout-section-list" data-layout-sections></div>
                </div>
            </div>
        </section>

        <aside class="card admin-form-card editor-sidebar page-editor-sidebar">
            <div class="sidebar-section">
                <div class="form-section-head">
                    <div>
                        <p class="eyebrow">Публікація</p>
                        <h2>Стан сторінки</h2>
                        <p class="meta">Керує видимістю та позицією сторінки.</p>
                    </div>
                </div>
                <div class="form-grid">
                    <label>Статус
                        <select name="status">
                            <option value="draft" <?= selected($item['status'] ?? '', 'draft') ?>>Чернетка</option>
                            <option value="published" <?= selected($item['status'] ?? '', 'published') ?>>Опубліковано</option>
                        </select>
                    </label>
                    <label>Шаблон
                        <select name="template">
                            <?php foreach ($templates as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= selected($item['template'] ?? 'default', $value) ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Сортування<input type="number" name="sort_order" value="<?= e((string) ($item['sort_order'] ?? 0)) ?>"></label>
                </div>
            </div>

            <div class="sidebar-section">
                <div class="form-section-head">
                    <div>
                        <p class="eyebrow">Адреса</p>
                        <h2>SEO</h2>
                        <p class="meta">Slug можна лишити порожнім, тоді він сформується з назви.</p>
                    </div>
                </div>
                <div class="form-grid">
                    <label>Slug<input name="slug" value="<?= e($item['slug'] ?? '') ?>" placeholder="storinka"></label>
                </div>
            </div>

            <div class="page-publish-actions">
                <button type="submit"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span>Зберегти сторінку</span></button>
                <a class="button secondary" href="<?= url('/admin/pages') ?>"><span class="mdi mdi-close" aria-hidden="true"></span><span>Скасувати</span></a>
                <?php if ($isEdit): ?>
                    <button class="button danger" type="submit" form="pageDeleteForm"><span class="mdi mdi-delete-outline" aria-hidden="true"></span><span>Видалити</span></button>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</form>
<?php if ($isEdit): ?>
    <form id="pageDeleteForm" method="post" action="<?= url('/admin/pages/bulk') ?>" data-no-ajax data-delete-confirm="Видалити цю сторінку?" data-after-success-url="<?= url('/admin/pages') ?>">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="bulk_action" value="delete">
        <input type="hidden" name="ids[]" value="<?= e((string) $item['id']) ?>">
    </form>
<?php endif; ?>

<div class="modal fade" id="layoutImportExportModal" tabindex="-1" aria-labelledby="layoutImportExportTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1">Конструктор</p>
                    <h5 class="modal-title" id="layoutImportExportTitle">
                        Імпорт та експорт JSON
                    </h5>
                </div>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Закрити"></button>
            </div>

            <div class="modal-body">

                <ul class="nav nav-tabs mb-4" id="layoutImportExportTabs" role="tablist">

                    <li class="nav-item" role="presentation">
                        <button class="nav-link active"
                                data-bs-toggle="tab"
                                data-bs-target="#layoutExportTab"
                                type="button">
                            <span class="mdi mdi-export"></span>
                            Експорт
                        </button>
                    </li>

                    <li class="nav-item" role="presentation">
                        <button class="nav-link"
                                data-bs-toggle="tab"
                                data-bs-target="#layoutImportTab"
                                type="button">
                            <span class="mdi mdi-import"></span>
                            Імпорт
                        </button>
                    </li>

                </ul>

                <div class="tab-content">

                    <!-- ======================== -->
                    <!-- ЕКСПОРТ -->
                    <!-- ======================== -->

                    <div class="tab-pane fade show active"
                         id="layoutExportTab">

                        <label class="w-100">
                            Експорт JSON

                            <textarea
                                class="textarea-small"
                                readonly
                                data-layout-export-field
                                placeholder="JSON поточної структури конструктора"></textarea>

                        </label>

                        <div class="d-flex gap-2 flex-wrap mt-3">

                            <button
                                class="button secondary compact"
                                type="button"
                                data-layout-export-copy>

                                <span class="mdi mdi-content-copy"></span>
                                <span>Скопіювати</span>

                            </button>

                        </div>

                    </div>

                    <!-- ======================== -->
                    <!-- ІМПОРТ -->
                    <!-- ======================== -->

                    <div class="tab-pane fade"
                         id="layoutImportTab">

                        <label class="w-100">
                            Імпорт JSON

                            <textarea
                                class="textarea-small"
                                data-layout-import-field
                                placeholder="Вставте JSON секцій або blocks_json"></textarea>

                        </label>

                        <div class="layout-import-export-example mt-3">

                            <label class="w-100">

                                Приклад JSON

                                <textarea
                                    class="textarea-small"
                                    readonly
                                    data-layout-example-field
                                    placeholder="Приклад структури"></textarea>

                            </label>

                        </div>

                        <div class="layout-import-export-actions mt-3">

                            <button
                                class="button secondary compact"
                                type="button"
                                data-layout-example-copy>

                                <span class="mdi mdi-content-copy"></span>
                                <span>Скопіювати приклад</span>

                            </button>

                            <button
                                class="button secondary compact"
                                type="button"
                                data-layout-example-use>

                                <span class="mdi mdi-file-replace-outline"></span>
                                <span>Вставити приклад</span>

                            </button>

                            <button
                                class="button secondary compact"
                                type="button"
                                data-layout-import-apply>

                                <span class="mdi mdi-file-import-outline"></span>
                                <span>Імпортувати</span>

                            </button>

                            <button
                                class="button secondary compact"
                                type="button"
                                data-layout-import-clear>

                                <span class="mdi mdi-close"></span>
                                <span>Очистити</span>

                            </button>

                            <span class="meta"
                                  data-layout-import-status></span>

                        </div>

                    </div>

                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="button secondary"
                    data-bs-dismiss="modal">

                    <span class="mdi mdi-close"></span>
                    <span>Закрити</span>

                </button>

            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="layoutSectionPickerModal" tabindex="-1" aria-labelledby="layoutSectionPickerTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1">Конструктор</p>
                    <h5 class="modal-title" id="layoutSectionPickerTitle">Обрати секцію</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
            </div>
            <div class="modal-body">
                <div class="layout-section-template-grid">
                    <button type="button" class="layout-section-template-card" data-layout-template="hero">
                        <span class="layout-section-template-icon mdi mdi-page-layout-header" aria-hidden="true"></span>
                        <strong>Hero-секція</strong>
                        <small>Великий вступ із CTA-кнопкою</small>
                        <span class="layout-section-template-preview is-hero"><i></i><i></i><i></i></span>
                    </button>
                    <button type="button" class="layout-section-template-card" data-layout-template="media-story">
                        <span class="layout-section-template-icon mdi mdi-image-text" aria-hidden="true"></span>
                        <strong>Медіа + текст</strong>
                        <small>Дві колонки для історії або послуги</small>
                        <span class="layout-section-template-preview is-media"><i></i><i></i></span>
                    </button>
                    <button type="button" class="layout-section-template-card" data-layout-template="feature-grid">
                        <span class="layout-section-template-icon mdi mdi-view-grid-outline" aria-hidden="true"></span>
                        <strong>Переваги</strong>
                        <small>Три сучасні інформаційні картки</small>
                        <span class="layout-section-template-preview is-grid"><i></i><i></i><i></i></span>
                    </button>
                    <button type="button" class="layout-section-template-card" data-layout-template="stats">
                        <span class="layout-section-template-icon mdi mdi-chart-box-outline" aria-hidden="true"></span>
                        <strong>Показники</strong>
                        <small>Три картки з цифрами та підписами</small>
                        <span class="layout-section-template-preview is-stats"><i></i><i></i><i></i></span>
                    </button>
                    <button type="button" class="layout-section-template-card" data-layout-template="cta">
                        <span class="layout-section-template-icon mdi mdi-bullhorn-outline" aria-hidden="true"></span>
                        <strong>CTA-блок</strong>
                        <small>Акцентний заклик до дії</small>
                        <span class="layout-section-template-preview is-cta"><i></i></span>
                    </button>
                    <button type="button" class="layout-section-template-card" data-layout-template="contact">
                        <span class="layout-section-template-icon mdi mdi-card-account-phone-outline" aria-hidden="true"></span>
                        <strong>Контакти</strong>
                        <small>Контактна інформація і кнопка</small>
                        <span class="layout-section-template-preview is-contact"><i></i><i></i></span>
                    </button>
                    <button type="button" class="layout-section-template-card" data-layout-template="blank">
                        <span class="layout-section-template-icon mdi mdi-plus-box-outline" aria-hidden="true"></span>
                        <strong>Порожня секція</strong>
                        <small>Одна колонка для власної структури</small>
                        <span class="layout-section-template-preview is-blank"><i></i></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="layoutCardModal" tabindex="-1" aria-labelledby="layoutCardModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable layout-card-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1">Картка</p>
                    <h5 class="modal-title" id="layoutCardModalTitle" data-card-modal-title>Нова картка</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger py-2" data-card-modal-error hidden></div>
                <div class="layout-card-modal-layout">
                    <div class="layout-card-modal-grid">
                        <div class="layout-card-modal-wide layout-card-preset-panel">
                            <div class="layout-card-preset-head">
                                <strong>Швидкий шаблон</strong>
                            </div>
                            <div class="layout-card-preset-grid" role="list" aria-label="Шаблони карток">
                                <button type="button" data-card-template-quick="feature"><span class="mdi mdi-lightbulb-on-outline" aria-hidden="true"></span><strong>Інфо</strong><small>Перевага або послуга</small></button>
                                <button type="button" data-card-template-quick="media"><span class="mdi mdi-image-text" aria-hidden="true"></span><strong>Медіа</strong><small>Зображення і текст</small></button>
                                <button type="button" data-card-template-quick="cta"><span class="mdi mdi-bullhorn-outline" aria-hidden="true"></span><strong>CTA</strong><small>Заклик до дії</small></button>
                                <button type="button" data-card-template-quick="stat"><span class="mdi mdi-chart-box-outline" aria-hidden="true"></span><strong>Цифра</strong><small>Показник</small></button>
                                <button type="button" data-card-template-quick="quote"><span class="mdi mdi-format-quote-close" aria-hidden="true"></span><strong>Цитата</strong><small>Відгук або думка</small></button>
                                <button type="button" data-card-template-quick="contact"><span class="mdi mdi-card-account-phone-outline" aria-hidden="true"></span><strong>Контакти</strong><small>Телефон і email</small></button>
                                <button type="button" data-card-template-quick="announcement"><span class="mdi mdi-bullhorn-variant-outline" aria-hidden="true"></span><strong>Оголошення</strong><small>Важлива інформація</small></button>
                                <button type="button" data-card-template-quick="document"><span class="mdi mdi-file-document-outline" aria-hidden="true"></span><strong>Документ</strong><small>Посилання на файл</small></button>
                                <button type="button" data-card-template-quick="schedule"><span class="mdi mdi-calendar-clock" aria-hidden="true"></span><strong>Розклад</strong><small>Час або події</small></button>
                                <button type="button" data-card-template-quick="faq"><span class="mdi mdi-help-circle-outline" aria-hidden="true"></span><strong>FAQ</strong><small>Питання й відповідь</small></button>
                                <button type="button" data-card-template-quick="step"><span class="mdi mdi-format-list-numbered" aria-hidden="true"></span><strong>Крок</strong><small>Етап процесу</small></button>
                                <button type="button" data-card-template-quick="download"><span class="mdi mdi-download-box-outline" aria-hidden="true"></span><strong>Завантаження</strong><small>Матеріал або файл</small></button>
                                <button type="button" data-card-template-quick="person"><span class="mdi mdi-account-tie-outline" aria-hidden="true"></span><strong>Особа</strong><small>Контакт відповідального</small></button>
                                <button type="button" data-card-template-quick="warning"><span class="mdi mdi-alert-outline" aria-hidden="true"></span><strong>Увага</strong><small>Попередження</small></button>
                            </div>
                        </div>
                        <div class="layout-card-modal-wide layout-card-modal-stats" aria-live="polite">
                            <span><span class="mdi mdi-palette-outline" aria-hidden="true"></span><strong data-card-modal-stat-style>Картка</strong></span>
                            <span><span class="mdi mdi-text-box-outline" aria-hidden="true"></span><strong data-card-modal-stat-text>0 символів</strong></span>
                            <span><span class="mdi mdi-image-outline" aria-hidden="true"></span><strong data-card-modal-stat-image>Без зображення</strong></span>
                            <span><span class="mdi mdi-gesture-tap-button" aria-hidden="true"></span><strong data-card-modal-stat-button>Без кнопки</strong></span>
                        </div>
                        <label>Стиль
                            <select data-card-modal-field="style">
                                <option value="default">Картка</option>
                                <option value="accent">Акцент</option>
                                <option value="plain">Без рамки</option>
                                <option value="feature">Сучасна інформаційна</option>
                                <option value="media">Медіа</option>
                                <option value="cta">CTA</option>
                                <option value="stat">Показник</option>
                                <option value="quote">Цитата</option>
                                <option value="contact">Контакти</option>
                            </select>
                        </label>
                        <label>Заголовок
                            <input data-card-modal-field="title" required placeholder="Заголовок картки">
                        </label>
                        <div class="layout-card-modal-wide layout-card-rich-text">
                            <label>Текст
                                <textarea data-card-modal-field="text" data-tiptap-editor rows="6" required placeholder="Основний текст картки"></textarea>
                            </label>
                            <div class="layout-card-text-tools" aria-label="Швидке форматування тексту картки">
                                <button class="button secondary compact" type="button" data-card-text-list="ul">
                                    <span class="mdi mdi-format-list-bulleted" aria-hidden="true"></span><span>Список</span>
                                </button>
                                <button class="button secondary compact" type="button" data-card-text-list="ol">
                                    <span class="mdi mdi-format-list-numbered" aria-hidden="true"></span><span>Нумерація</span>
                                </button>
                                <button class="button secondary compact" type="button" data-card-text-list="documents">
                                    <span class="mdi mdi-file-document-multiple-outline" aria-hidden="true"></span><span>Список документів</span>
                                </button>
                            </div>
                        </div>
                        <div class="layout-card-modal-wide layout-card-image-field">
                            <label>Зображення
                                <input data-card-modal-field="image" readonly placeholder="Оберіть з медіафайлів">
                            </label>
                            <div class="layout-card-image-actions">
                                <button class="button secondary compact" type="button" data-card-image-picker-open>
                                    <span class="mdi mdi-image-search-outline" aria-hidden="true"></span><span>Обрати</span>
                                </button>
                                <button class="button secondary compact" type="button" data-card-image-clear>
                                    <span class="mdi mdi-close" aria-hidden="true"></span><span>Очистити</span>
                                </button>
                            </div>
                        </div>
                        <div class="layout-card-modal-wide layout-card-links-editor">
                            <div class="layout-card-links-head">
                                <strong>Покликання</strong>
                                <div>
                                    <button class="button secondary compact" type="button" data-card-link-library-add>
                                        <span class="mdi mdi-plus" aria-hidden="true"></span><span>Додати</span>
                                    </button>
                                    <button class="button secondary compact" type="button" data-card-link-library-pick>
                                        <span class="mdi mdi-link-plus" aria-hidden="true"></span><span>Обрати посилання</span>
                                    </button>
                                </div>
                            </div>
                            <div class="layout-card-link-list" data-card-link-list></div>
                            <div class="layout-card-link-empty" data-card-link-empty>Додайте одне або кілька покликань для картки.</div>
                        </div>
                        <div class="layout-card-modal-wide layout-card-image-preview" data-card-image-preview hidden></div>
                    </div>
                    <aside class="layout-card-modal-preview" aria-live="polite">
                        <div class="layout-card-modal-preview-head">
                            <strong>Попередній вигляд</strong>
                            <span data-card-modal-preview-label>Картка</span>
                        </div>
                        <article class="card content-card page-layout-card page-layout-card-default" data-card-modal-preview>
                            <div class="layout-card-modal-preview-empty">Заповніть картку</div>
                        </article>
                    </aside>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button secondary" data-bs-dismiss="modal"><span class="mdi mdi-close" aria-hidden="true"></span><span>Скасувати</span></button>
                <button type="button" data-layout-card-save><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span data-layout-card-save-label>Додати картку</span></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="layoutCardImagePickerModal" tabindex="-1" aria-labelledby="layoutCardImagePickerTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1">Медіафайли</p>
                    <h5 class="modal-title" id="layoutCardImagePickerTitle">Обрати зображення</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
            </div>
            <div class="modal-body">
                <div class="layout-card-image-picker-tools">
                    <input type="search" class="form-control" data-card-image-search placeholder="Пошук за назвою або шляхом">
                    <div class="layout-card-image-picker-status meta" data-card-image-status></div>
                </div>
                <div class="layout-card-image-picker-grid" data-card-image-grid></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button secondary" data-bs-dismiss="modal">Скасувати</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= url('/assets/admin-pages-form.js') ?>"></script>
