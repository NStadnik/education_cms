<?php
    $isEdit = !empty($item['id']);
    $blocks = $item ? json_decode($item['blocks_json'], true) : [];
    $layoutBlocks = array_values(array_filter($blocks ?: [], static fn ($block): bool => is_array($block) && ($block['type'] ?? '') === 'layout'));
    $hasLayoutBlocks = !empty($layoutBlocks);
    $blockCount = count($layoutBlocks ?: ($blocks ?: []));
    $statusLabel = (($item['status'] ?? 'draft') === 'published') ? 'Опубліковано' : 'Чернетка';
    $simpleTextParts = [];
    foreach (($blocks ?: []) as $block) {
        if (!is_array($block)) {
            continue;
        }
        if (($block['type'] ?? '') === 'layout') {
            foreach (($block['rows'] ?? []) as $row) {
                foreach (($row['columns'] ?? []) as $column) {
                    foreach (($column['cards'] ?? []) as $card) {
                        $cardTitle = trim((string) ($card['title'] ?? ''));
                        $cardText = trim((string) ($card['text'] ?? ''));
                        if ($cardTitle !== '' || $cardText !== '') {
                            $simpleTextParts[] = trim(($cardTitle !== '' ? $cardTitle . "\n" : '') . $cardText);
                        }
                    }
                }
            }
            continue;
        }
        $simpleTextParts[] = trim((string) ($block['text'] ?? ''));
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
                        <textarea class="textarea-large" name="blocks_text" data-rich-editor placeholder="Введіть текст сторінки. Можна вставляти HTML з базовим форматуванням."><?= e($simpleText) ?></textarea>
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
                            <button class="button secondary compact" type="button" data-layout-template="hero">
                                <span class="mdi mdi-page-layout-header" aria-hidden="true"></span><span>Hero</span>
                            </button>
                            <button class="button secondary compact" type="button" data-layout-template="two-cards">
                                <span class="mdi mdi-view-column-outline" aria-hidden="true"></span><span>2 колонки</span>
                            </button>
                            <button class="button secondary compact" type="button" data-layout-template="three-cards">
                                <span class="mdi mdi-view-grid-outline" aria-hidden="true"></span><span>3 колонки</span>
                            </button>
                            <button class="button compact" type="button" data-layout-add-section>
                                <span class="mdi mdi-plus" aria-hidden="true"></span><span>Секція</span>
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

<div class="modal fade" id="layoutCardModal" tabindex="-1" aria-labelledby="layoutCardModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
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
                <div class="layout-card-modal-grid">
                    <label>Стиль
                        <select data-card-modal-field="style">
                            <option value="default">Картка</option>
                            <option value="accent">Акцент</option>
                            <option value="plain">Без рамки</option>
                        </select>
                    </label>
                    <label>Заголовок
                        <input data-card-modal-field="title" required placeholder="Заголовок картки">
                    </label>
                    <label class="layout-card-modal-wide">Текст
                        <textarea data-card-modal-field="text" rows="6" required placeholder="Основний текст картки"></textarea>
                    </label>
                    <label class="layout-card-modal-wide">Зображення URL
                        <input data-card-modal-field="image" placeholder="/uploads/image.jpg">
                    </label>
                    <label>Текст кнопки
                        <input data-card-modal-field="button_text" placeholder="Детальніше">
                    </label>
                    <label>URL кнопки
                        <input data-card-modal-field="button_url" placeholder="/page/about">
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button secondary" data-bs-dismiss="modal"><span class="mdi mdi-close" aria-hidden="true"></span><span>Скасувати</span></button>
                <button type="button" data-layout-card-save><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span data-layout-card-save-label>Додати картку</span></button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const builder = document.querySelector('[data-layout-builder]');
    if (!builder) {
        return;
    }

    const list = builder.querySelector('[data-layout-sections]');
    const hidden = document.querySelector('[data-layout-json]');
    const blockCountNode = document.querySelector('[data-page-block-count]');
    const modeInput = document.querySelector('[data-editor-mode]');
    const simplePanel = document.querySelector('[data-simple-editor-panel]');
    const advancedPanel = document.querySelector('[data-advanced-editor-panel]');
    const modeButtons = document.querySelectorAll('[data-editor-mode-button]');
    const cardModalNode = document.getElementById('layoutCardModal');
    const cardModal = cardModalNode && window.bootstrap ? new window.bootstrap.Modal(cardModalNode) : null;
    const cardModalError = cardModalNode ? cardModalNode.querySelector('[data-card-modal-error]') : null;
    let cardModalState = null;
    let initialBlocks = [];
    try {
        initialBlocks = JSON.parse(builder.dataset.initial || '[]');
    } catch (error) {
        initialBlocks = [];
    }
    let sections = normalizeInitial(initialBlocks);

    function normalizeInitial(blocks) {
        if (!Array.isArray(blocks) || blocks.length === 0) {
            return [emptySection()];
        }

        const layoutBlocks = blocks.filter(function (block) {
            return block && block.type === 'layout';
        });
        if (layoutBlocks.length) {
            return layoutBlocks.map(normalizeSection);
        }

        return blocks.map(function (block) {
            return {
                type: 'layout',
                title: block.title || '',
                background: 'default',
                rows: [{
                    columns: [{
                        width: 'col-md-12',
                        cards: [{
                            style: 'default',
                            title: block.title || 'Текст',
                            text: block.text || '',
                            image: '',
                            button_text: '',
                            button_url: ''
                        }]
                    }]
                }]
            };
        });
    }

    function emptySection() {
        return {
            type: 'layout',
            title: '',
            background: 'default',
            rows: [rowFromPreset('col-md-12')]
        };
    }

    function presetSection(type) {
        if (type === 'hero') {
            return {
                type: 'layout',
                title: 'Перший екран',
                background: 'accent',
                rows: [{
                    columns: [{
                        width: 'col-md-12',
                        cards: [{
                            style: 'plain',
                            title: 'Заголовок сторінки',
                            text: '<p>Короткий вступний текст для відвідувачів.</p>',
                            image: '',
                            button_text: 'Дізнатися більше',
                            button_url: '#'
                        }]
                    }]
                }]
            };
        }
        if (type === 'two-cards') {
            return {
                type: 'layout',
                title: 'Інформаційний блок',
                background: 'default',
                rows: [rowFromPreset('6-6')]
            };
        }
        if (type === 'three-cards') {
            return {
                type: 'layout',
                title: 'Переваги',
                background: 'light',
                rows: [rowFromPreset('4-4-4')]
            };
        }

        return emptySection();
    }

    function normalizeSection(section) {
        return {
            type: 'layout',
            title: section.title || '',
            background: ['default', 'light', 'accent'].includes(section.background) ? section.background : 'default',
            rows: Array.isArray(section.rows) && section.rows.length ? section.rows.map(normalizeRow) : [rowFromPreset('col-md-12')]
        };
    }

    function normalizeRow(row) {
        return {
            columns: Array.isArray(row.columns) && row.columns.length ? row.columns.map(normalizeColumn) : [column('col-md-12')]
        };
    }

    function normalizeColumn(item) {
        return {
            width: ['col-md-12', 'col-md-8', 'col-md-6', 'col-md-4'].includes(item.width) ? item.width : 'col-md-12',
            cards: Array.isArray(item.cards) ? item.cards.map(normalizeCard) : []
        };
    }

    function normalizeCard(card) {
        return {
            style: ['default', 'accent', 'plain'].includes(card.style) ? card.style : 'default',
            title: card.title || '',
            text: card.text || '',
            image: card.image || '',
            button_text: card.button_text || '',
            button_url: card.button_url || ''
        };
    }

    function emptyCard() {
        return {style: 'default', title: '', text: '', image: '', button_text: '', button_url: ''};
    }

    function column(width) {
        return {width: width, cards: []};
    }

    function rowFromPreset(preset) {
        if (preset === '6-6') {
            return {columns: [column('col-md-6'), column('col-md-6')]};
        }
        if (preset === '4-4-4') {
            return {columns: [column('col-md-4'), column('col-md-4'), column('col-md-4')]};
        }
        if (preset === '8-4') {
            return {columns: [column('col-md-8'), column('col-md-4')]};
        }
        return {columns: [column('col-md-12')]};
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (character) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character];
        });
    }

    function hasHtml(value) {
        return /<\/?[a-z][\s\S]*>/i.test(String(value || ''));
    }

    function sanitizePreviewHtml(value) {
        const template = document.createElement('template');
        template.innerHTML = String(value || '');
        template.content.querySelectorAll('script, iframe, object, embed, link, meta').forEach(function (node) {
            node.remove();
        });
        template.content.querySelectorAll('*').forEach(function (node) {
            Array.from(node.attributes).forEach(function (attribute) {
                const name = attribute.name.toLowerCase();
                const rawValue = String(attribute.value || '').trim();
                if (name.indexOf('on') === 0 || name === 'srcdoc') {
                    node.removeAttribute(attribute.name);
                }
                if ((name === 'href' || name === 'src') && /^javascript:/i.test(rawValue)) {
                    node.removeAttribute(attribute.name);
                }
            });
        });

        return template.innerHTML;
    }

    function cardTextPreview(value) {
        const text = String(value || '').trim();
        if (text === '') {
            return '<p class="meta mb-0">Текст картки не заповнено.</p>';
        }

        return hasHtml(text) ? sanitizePreviewHtml(text) : escapeHtml(text).replace(/\n/g, '<br>');
    }

    function render() {
        list.innerHTML = sections.map(function (section, sectionIndex) {
            return '<section class="layout-editor-section" data-section-index="' + sectionIndex + '" data-drag-kind="section">' +
                '<div class="layout-editor-section-head">' +
                    dragHandle('Перетягнути секцію') +
                    '<label>Назва секції<input data-layout-field="section.title" value="' + escapeHtml(section.title) + '" placeholder="Необов’язково"></label>' +
                    '<label>Фон<select data-layout-field="section.background">' +
                        option('default', 'Звичайний', section.background) +
                        option('light', 'Світлий', section.background) +
                        option('accent', 'Акцентний', section.background) +
                    '</select></label>' +
                    '<span class="layout-editor-count">Рядів: ' + section.rows.length + '</span>' +
                    '<button class="button secondary compact" type="button" data-layout-add-row><span class="mdi mdi-view-column-outline"></span><span>Ряд</span></button>' +
                    '<button class="button danger compact" type="button" data-layout-remove-section title="Видалити секцію"><span class="mdi mdi-delete-outline"></span></button>' +
                '</div>' +
                '<div class="layout-editor-rows">' + section.rows.map(function (row, rowIndex) {
                    return renderRow(sectionIndex, rowIndex, row);
                }).join('') + '</div>' +
            '</section>';
        }).join('');
        sync();
    }

    function renderRow(sectionIndex, rowIndex, row) {
        return '<div class="layout-editor-row" data-row-index="' + rowIndex + '" data-drag-kind="row">' +
            '<div class="layout-editor-row-head">' +
                dragHandle('Перетягнути ряд') +
                '<strong>Ряд ' + (rowIndex + 1) + '</strong>' +
                '<select data-layout-row-preset>' +
                    '<option value="">Змінити сітку</option>' +
                    '<option value="col-md-12">1 колонка</option>' +
                    '<option value="6-6">2 колонки</option>' +
                    '<option value="4-4-4">3 колонки</option>' +
                    '<option value="8-4">8 / 4</option>' +
                '</select>' +
                '<button class="button danger compact" type="button" data-layout-remove-row title="Видалити ряд"><span class="mdi mdi-delete-outline"></span></button>' +
            '</div>' +
            '<div class="layout-editor-columns">' + row.columns.map(function (col, colIndex) {
                return renderColumn(sectionIndex, rowIndex, colIndex, col);
            }).join('') + '</div>' +
        '</div>';
    }

    function renderColumn(sectionIndex, rowIndex, colIndex, col) {
        return '<div class="layout-editor-column" data-column-index="' + colIndex + '">' +
            '<div class="layout-editor-column-head">' +
                '<strong>' + columnLabel(col.width) + '</strong>' +
                '<button class="button secondary compact" type="button" data-layout-add-card><span class="mdi mdi-card-plus-outline"></span><span>Картка</span></button>' +
            '</div>' +
            (col.cards.length ? col.cards.map(function (card, cardIndex) {
                return renderCard(cardIndex, card);
            }).join('') : '<div class="layout-editor-empty">Карток ще немає. Натисніть “Картка”, заповніть поля і додайте її до колонки.</div>') +
        '</div>';
    }

    function renderCard(cardIndex, card) {
        const title = String(card.title || '').trim();
        const image = String(card.image || '').trim();
        const buttonText = String(card.button_text || '').trim();
        const buttonUrl = String(card.button_url || '').trim();
        const style = ['default', 'accent', 'plain'].includes(card.style) ? card.style : 'default';

        return '<article class="layout-editor-card layout-editor-card-preview page-layout-card page-layout-card-' + escapeHtml(style) + '" data-card-index="' + cardIndex + '" data-drag-kind="card">' +
            '<div class="layout-editor-card-head">' +
                dragHandle('Перетягнути картку') +
                '<strong>' + escapeHtml(title || ('Картка ' + (cardIndex + 1))) + '</strong>' +
                '<div class="layout-editor-card-actions">' +
                    '<button class="button secondary compact" type="button" data-layout-edit-card title="Редагувати картку"><span class="mdi mdi-pencil-outline"></span></button>' +
                    '<button class="button danger compact" type="button" data-layout-remove-card title="Видалити картку"><span class="mdi mdi-delete-outline"></span></button>' +
                '</div>' +
            '</div>' +
            '<div class="layout-editor-card-preview-body">' +
                (image ? '<img src="' + escapeHtml(image) + '" alt="' + escapeHtml(title) + '">' : '') +
                (title ? '<h3>' + escapeHtml(title) + '</h3>' : '') +
                '<div class="rich-content">' + cardTextPreview(card.text) + '</div>' +
                (buttonText && buttonUrl ? '<span class="button compact layout-card-preview-button">' + escapeHtml(buttonText) + '</span>' : '') +
            '</div>' +
        '</article>';
    }

    function option(value, label, current) {
        return '<option value="' + value + '"' + (value === current ? ' selected' : '') + '>' + label + '</option>';
    }

    function dragHandle(title) {
        return '<span class="layout-drag-handle" draggable="true" title="' + title + '" aria-label="' + title + '"><span class="mdi mdi-drag"></span></span>';
    }

    function columnLabel(width) {
        return {
            'col-md-12': '1/1',
            'col-md-8': '2/3',
            'col-md-6': '1/2',
            'col-md-4': '1/3'
        }[width] || width;
    }

    function indexes(node) {
        const section = node.closest('[data-section-index]');
        const row = node.closest('[data-row-index]');
        const column = node.closest('[data-column-index]');
        const card = node.closest('[data-card-index]');
        return {
            section: section ? Number(section.dataset.sectionIndex) : -1,
            row: row ? Number(row.dataset.rowIndex) : -1,
            column: column ? Number(column.dataset.columnIndex) : -1,
            card: card ? Number(card.dataset.cardIndex) : -1
        };
    }

    function sync() {
        hidden.value = JSON.stringify(sections);
        if (blockCountNode) {
            blockCountNode.textContent = String(sections.length);
        }
    }

    function setEditorMode(mode) {
        const isAdvanced = mode === 'advanced';
        if (modeInput) {
            modeInput.value = isAdvanced ? 'advanced' : 'simple';
        }
        if (simplePanel) {
            simplePanel.hidden = isAdvanced;
        }
        if (advancedPanel) {
            advancedPanel.hidden = !isAdvanced;
        }
        modeButtons.forEach(function (button) {
            const active = button.dataset.editorModeButton === (isAdvanced ? 'advanced' : 'simple');
            button.classList.toggle('secondary', !active);
        });
    }

    modeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            setEditorMode(button.dataset.editorModeButton);
        });
    });

    builder.addEventListener('input', updateField);
    builder.addEventListener('change', function (event) {
        updateField(event);
        const preset = event.target.closest('[data-layout-row-preset]');
        if (preset && preset.value) {
            const i = indexes(preset);
            sections[i.section].rows[i.row] = rowFromPreset(preset.value);
            render();
        }
    });

    function updateField(event) {
        const field = event.target.closest('[data-layout-field]');
        if (!field) {
            return;
        }
        const i = indexes(field);
        const path = field.dataset.layoutField;
        if (path === 'section.title') sections[i.section].title = field.value;
        if (path === 'section.background') sections[i.section].background = field.value;
        if (path === 'card.style') sections[i.section].rows[i.row].columns[i.column].cards[i.card].style = field.value;
        if (path === 'card.title') sections[i.section].rows[i.row].columns[i.column].cards[i.card].title = field.value;
        if (path === 'card.text') sections[i.section].rows[i.row].columns[i.column].cards[i.card].text = field.value;
        if (path === 'card.image') sections[i.section].rows[i.row].columns[i.column].cards[i.card].image = field.value;
        if (path === 'card.button_text') sections[i.section].rows[i.row].columns[i.column].cards[i.card].button_text = field.value;
        if (path === 'card.button_url') sections[i.section].rows[i.row].columns[i.column].cards[i.card].button_url = field.value;
        sync();
    }

    function cardModalField(name) {
        return cardModalNode ? cardModalNode.querySelector('[data-card-modal-field="' + name + '"]') : null;
    }

    function setCardModalError(message) {
        if (!cardModalError) {
            return;
        }
        cardModalError.hidden = message === '';
        cardModalError.textContent = message;
    }

    function setCardModalValues(card) {
        ['style', 'title', 'text', 'image', 'button_text', 'button_url'].forEach(function (name) {
            const field = cardModalField(name);
            if (field) {
                field.value = card[name] || (name === 'style' ? 'default' : '');
            }
        });
    }

    function readCardModalValues() {
        const card = emptyCard();
        ['style', 'title', 'text', 'image', 'button_text', 'button_url'].forEach(function (name) {
            const field = cardModalField(name);
            if (field) {
                card[name] = field.value.trim();
            }
        });
        if (!['default', 'accent', 'plain'].includes(card.style)) {
            card.style = 'default';
        }

        return card;
    }

    function openCardModal(i, cardIndex) {
        if (!cardModal) {
            return;
        }
        const isEdit = Number.isInteger(cardIndex) && cardIndex >= 0;
        const card = isEdit
            ? sections[i.section].rows[i.row].columns[i.column].cards[cardIndex]
            : emptyCard();

        cardModalState = {
            section: i.section,
            row: i.row,
            column: i.column,
            card: isEdit ? cardIndex : -1
        };
        setCardModalError('');
        setCardModalValues(card);
        const title = cardModalNode.querySelector('[data-card-modal-title]');
        const saveLabel = cardModalNode.querySelector('[data-layout-card-save-label]');
        if (title) {
            title.textContent = isEdit ? 'Редагувати картку' : 'Нова картка';
        }
        if (saveLabel) {
            saveLabel.textContent = isEdit ? 'Оновити картку' : 'Додати картку';
        }
        cardModal.show();
        const titleField = cardModalField('title');
        if (titleField) {
            setTimeout(function () {
                titleField.focus();
            }, 180);
        }
    }

    if (cardModalNode) {
        const saveCardButton = cardModalNode.querySelector('[data-layout-card-save]');
        if (saveCardButton) {
            saveCardButton.addEventListener('click', function () {
                if (!cardModalState) {
                    return;
                }
                const card = readCardModalValues();
                if (card.title === '' || card.text === '') {
                    setCardModalError('Заповніть заголовок і текст картки.');
                    return;
                }

                const cards = sections[cardModalState.section].rows[cardModalState.row].columns[cardModalState.column].cards;
                if (cardModalState.card >= 0) {
                    cards[cardModalState.card] = card;
                } else {
                    cards.push(card);
                }
                cardModal.hide();
                cardModalState = null;
                render();
            });
        }
        cardModalNode.addEventListener('hidden.bs.modal', function () {
            cardModalState = null;
            setCardModalError('');
        });
    }

    builder.addEventListener('click', function (event) {
        const button = event.target.closest('button');
        if (!button) return;
        const i = indexes(button);
        if (button.matches('[data-layout-add-section]')) sections.push(emptySection());
        if (button.matches('[data-layout-template]')) sections.push(presetSection(button.dataset.layoutTemplate));
        if (button.matches('[data-layout-remove-section]')) sections.splice(i.section, 1);
        if (button.matches('[data-layout-add-row]')) sections[i.section].rows.push(rowFromPreset('col-md-12'));
        if (button.matches('[data-layout-remove-row]')) sections[i.section].rows.splice(i.row, 1);
        if (button.matches('[data-layout-add-card]')) {
            openCardModal(i, -1);
            return;
        }
        if (button.matches('[data-layout-edit-card]')) {
            openCardModal(i, i.card);
            return;
        }
        if (button.matches('[data-layout-remove-card]')) sections[i.section].rows[i.row].columns[i.column].cards.splice(i.card, 1);
        if (!sections.length) sections.push(emptySection());
        render();
    });

    let dragState = null;

    builder.addEventListener('dragstart', function (event) {
        const handle = event.target.closest('.layout-drag-handle');
        const item = handle ? handle.closest('[data-drag-kind]') : null;
        if (!item) {
            event.preventDefault();
            return;
        }
        const i = indexes(item);
        dragState = {kind: item.dataset.dragKind, section: i.section, row: i.row, column: i.column, card: i.card};
        item.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', dragState.kind);
    });

    builder.addEventListener('dragend', function () {
        builder.querySelectorAll('.is-dragging, .is-drag-over').forEach(function (node) {
            node.classList.remove('is-dragging', 'is-drag-over');
        });
        dragState = null;
    });

    builder.addEventListener('dragover', function (event) {
        const target = dragTarget(event.target);
        if (!target || !canDrop(target)) {
            return;
        }
        event.preventDefault();
        target.classList.add('is-drag-over');
    });

    builder.addEventListener('dragleave', function (event) {
        const target = dragTarget(event.target);
        if (target) {
            target.classList.remove('is-drag-over');
        }
    });

    builder.addEventListener('drop', function (event) {
        const target = dragTarget(event.target);
        if (!target || !canDrop(target)) {
            return;
        }
        event.preventDefault();
        moveDraggedItem(target);
        render();
    });

    function dragTarget(node) {
        if (dragState) {
            return node.closest('[data-drag-kind="' + dragState.kind + '"]');
        }

        return node.closest('[data-drag-kind]');
    }

    function canDrop(target) {
        if (!dragState || target.dataset.dragKind !== dragState.kind) {
            return false;
        }
        const i = indexes(target);
        if (dragState.kind === 'section') {
            return i.section !== dragState.section;
        }
        if (dragState.kind === 'row') {
            return i.section === dragState.section && i.row !== dragState.row;
        }
        if (dragState.kind === 'card') {
            return i.section === dragState.section && i.row === dragState.row && i.column === dragState.column && i.card !== dragState.card;
        }

        return false;
    }

    function moveInArray(items, from, to) {
        if (from === to || from < 0 || to < 0 || from >= items.length || to >= items.length) {
            return;
        }
        const moving = items.splice(from, 1)[0];
        items.splice(to, 0, moving);
    }

    function moveDraggedItem(target) {
        const i = indexes(target);
        if (dragState.kind === 'section') {
            moveInArray(sections, dragState.section, i.section);
            return;
        }
        if (dragState.kind === 'row') {
            moveInArray(sections[dragState.section].rows, dragState.row, i.row);
            return;
        }
        if (dragState.kind === 'card') {
            moveInArray(sections[dragState.section].rows[dragState.row].columns[dragState.column].cards, dragState.card, i.card);
        }
    }

    const form = builder.closest('form');
    if (form) {
        form.addEventListener('submit', sync);
    }
    render();
});
</script>
