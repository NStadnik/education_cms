<?php
    $isEdit = !empty($item['id']);
    $blocks = $item ? json_decode($item['blocks_json'], true) : [];
    $blockCount = count($blocks ?: []);
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Контент сайту</p>
        <h1><?= $isEdit ? 'Редагувати сторінку' : 'Нова сторінка' ?></h1>
        <p class="page-subtitle">Налаштуйте назву, короткий опис і візуальні секції сторінки на Bootstrap-сітці.</p>
    </div>
    <div class="form-actions">
        <a class="button secondary" href="<?= url('/admin/pages') ?>"><span class="mdi mdi-arrow-left" aria-hidden="true"></span><span>До списку</span></a>
        <?php if (!empty($item['slug']) && ($item['status'] ?? '') === 'published'): ?>
            <a class="button secondary" href="<?= url($item['slug'] === 'home' ? '/' : '/page/' . $item['slug']) ?>"><span class="mdi mdi-eye-outline" aria-hidden="true"></span><span>Переглянути</span></a>
        <?php endif; ?>
    </div>
</div>

<div class="metrics">
    <div class="metric"><div><span>Статус</span><strong><?= e($item['status'] ?? 'draft') ?></strong></div><span class="mdi mdi-circle-edit-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Блоків</span><strong><?= e((string) $blockCount) ?></strong></div><span class="mdi mdi-view-grid-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Порядок</span><strong><?= e((string) ($item['sort_order'] ?? 0)) ?></strong></div><span class="mdi mdi-sort-numeric-ascending metric-icon" aria-hidden="true"></span></div>
</div>

<form method="post" action="<?= url('/admin/pages/save') ?>">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= e((string) ($item['id'] ?? '')) ?>">

    <div class="editor-layout">
        <section class="card admin-form-card">
            <div class="form-section-head">
                <div>
                    <h2>Основний вміст</h2>
                    <p class="meta">Ці дані відображаються на публічній сторінці та в меню.</p>
                </div>
            </div>

            <div class="form-grid wide">
                <label>Назва<input name="title" value="<?= e($item['title'] ?? '') ?>" required></label>
                <label>Короткий опис<textarea class="textarea-small" name="excerpt"><?= e($item['excerpt'] ?? '') ?></textarea></label>
                <input type="hidden" name="layout_json" data-layout-json>
                <div class="layout-builder" data-layout-builder data-initial="<?= e(json_encode($blocks ?: [], JSON_UNESCAPED_UNICODE) ?: '[]') ?>">
                    <div class="layout-builder-head">
                        <div>
                            <strong>Редактор секцій</strong>
                            <p class="meta mb-0">Створюйте секції, ряди, колонки та картки. На сайті вони рендеряться через Bootstrap grid.</p>
                        </div>
                        <button class="button secondary compact" type="button" data-layout-add-section>
                            <span class="mdi mdi-plus" aria-hidden="true"></span><span>Секція</span>
                        </button>
                    </div>
                    <div class="layout-section-list" data-layout-sections></div>
                </div>
                <details class="hint-box">
                    <summary>Резервний текстовий режим</summary>
                    <label class="mt-2">Текст сторінки
                        <textarea class="textarea-small" name="blocks_text" placeholder="Використовується, якщо редактор секцій порожній."><?php foreach (($blocks ?: []) as $block): ?><?= e($block['text'] ?? '') ?><?php endforeach; ?></textarea>
                    </label>
                </details>
            </div>
        </section>

        <aside class="card admin-form-card editor-sidebar">
            <div class="form-section-head">
                <div>
                    <h2>Публікація</h2>
                    <p class="meta">Керує видимістю та позицією сторінки.</p>
                </div>
            </div>

            <div class="form-grid">
                <label>Статус
                    <select name="status">
                        <option value="draft" <?= selected($item['status'] ?? '', 'draft') ?>>draft</option>
                        <option value="published" <?= selected($item['status'] ?? '', 'published') ?>>published</option>
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

            <div class="form-section-head mt-4">
                <div>
                    <h2>SEO</h2>
                    <p class="meta">Адреса сторінки формується автоматично, але її можна змінити вручну.</p>
                </div>
            </div>
            <div class="form-grid">
                <label>Slug<input name="slug" value="<?= e($item['slug'] ?? '') ?>" placeholder="storinka"></label>
            </div>

            <div class="form-actions stacked">
                <button type="submit"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span>Зберегти сторінку</span></button>
                <?php if ($isEdit): ?>
                    <button class="button danger" type="submit" form="pageDeleteForm"><span class="mdi mdi-delete-outline" aria-hidden="true"></span><span>Видалити</span></button>
                <?php endif; ?>
                <a class="button secondary" href="<?= url('/admin/pages') ?>"><span class="mdi mdi-close" aria-hidden="true"></span><span>Скасувати</span></a>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const builder = document.querySelector('[data-layout-builder]');
    if (!builder) {
        return;
    }

    const list = builder.querySelector('[data-layout-sections]');
    const hidden = document.querySelector('[data-layout-json]');
    const addSectionButton = builder.querySelector('[data-layout-add-section]');
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
            cards: Array.isArray(item.cards) && item.cards.length ? item.cards.map(normalizeCard) : [emptyCard()]
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
        return {width: width, cards: [emptyCard()]};
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

    function render() {
        list.innerHTML = sections.map(function (section, sectionIndex) {
            return '<section class="layout-editor-section" data-section-index="' + sectionIndex + '">' +
                '<div class="layout-editor-section-head">' +
                    '<label>Назва секції<input data-layout-field="section.title" value="' + escapeHtml(section.title) + '" placeholder="Необов’язково"></label>' +
                    '<label>Фон<select data-layout-field="section.background">' +
                        option('default', 'Звичайний', section.background) +
                        option('light', 'Світлий', section.background) +
                        option('accent', 'Акцентний', section.background) +
                    '</select></label>' +
                    '<button class="button secondary compact" type="button" data-layout-add-row><span class="mdi mdi-view-column-outline"></span><span>Ряд</span></button>' +
                    '<button class="button danger compact" type="button" data-layout-remove-section><span class="mdi mdi-delete-outline"></span></button>' +
                '</div>' +
                '<div class="layout-editor-rows">' + section.rows.map(function (row, rowIndex) {
                    return renderRow(sectionIndex, rowIndex, row);
                }).join('') + '</div>' +
            '</section>';
        }).join('');
        sync();
    }

    function renderRow(sectionIndex, rowIndex, row) {
        return '<div class="layout-editor-row" data-row-index="' + rowIndex + '">' +
            '<div class="layout-editor-row-head">' +
                '<strong>Ряд ' + (rowIndex + 1) + '</strong>' +
                '<select data-layout-row-preset>' +
                    '<option value="">Змінити сітку</option>' +
                    '<option value="col-md-12">1 колонка</option>' +
                    '<option value="6-6">2 колонки</option>' +
                    '<option value="4-4-4">3 колонки</option>' +
                    '<option value="8-4">8 / 4</option>' +
                '</select>' +
                '<button class="button danger compact" type="button" data-layout-remove-row><span class="mdi mdi-delete-outline"></span></button>' +
            '</div>' +
            '<div class="layout-editor-columns">' + row.columns.map(function (col, colIndex) {
                return renderColumn(sectionIndex, rowIndex, colIndex, col);
            }).join('') + '</div>' +
        '</div>';
    }

    function renderColumn(sectionIndex, rowIndex, colIndex, col) {
        return '<div class="layout-editor-column" data-column-index="' + colIndex + '">' +
            '<div class="layout-editor-column-head">' +
                '<strong>' + escapeHtml(col.width) + '</strong>' +
                '<button class="button secondary compact" type="button" data-layout-add-card><span class="mdi mdi-card-plus-outline"></span><span>Картка</span></button>' +
            '</div>' +
            col.cards.map(function (card, cardIndex) {
                return renderCard(cardIndex, card);
            }).join('') +
        '</div>';
    }

    function renderCard(cardIndex, card) {
        return '<article class="layout-editor-card" data-card-index="' + cardIndex + '">' +
            '<div class="layout-editor-card-head"><strong>Картка ' + (cardIndex + 1) + '</strong><button class="button danger compact" type="button" data-layout-remove-card><span class="mdi mdi-delete-outline"></span></button></div>' +
            '<label>Стиль<select data-layout-field="card.style">' +
                option('default', 'Картка', card.style) +
                option('accent', 'Акцент', card.style) +
                option('plain', 'Без рамки', card.style) +
            '</select></label>' +
            '<label>Заголовок<input data-layout-field="card.title" value="' + escapeHtml(card.title) + '"></label>' +
            '<label>Текст<textarea data-layout-field="card.text" rows="5">' + escapeHtml(card.text) + '</textarea></label>' +
            '<label>Зображення URL<input data-layout-field="card.image" value="' + escapeHtml(card.image) + '" placeholder="/uploads/..."></label>' +
            '<div class="layout-editor-two">' +
                '<label>Текст кнопки<input data-layout-field="card.button_text" value="' + escapeHtml(card.button_text) + '"></label>' +
                '<label>URL кнопки<input data-layout-field="card.button_url" value="' + escapeHtml(card.button_url) + '"></label>' +
            '</div>' +
        '</article>';
    }

    function option(value, label, current) {
        return '<option value="' + value + '"' + (value === current ? ' selected' : '') + '>' + label + '</option>';
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
    }

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

    builder.addEventListener('click', function (event) {
        const button = event.target.closest('button');
        if (!button) return;
        const i = indexes(button);
        if (button.matches('[data-layout-add-section]')) sections.push(emptySection());
        if (button.matches('[data-layout-remove-section]')) sections.splice(i.section, 1);
        if (button.matches('[data-layout-add-row]')) sections[i.section].rows.push(rowFromPreset('col-md-12'));
        if (button.matches('[data-layout-remove-row]')) sections[i.section].rows.splice(i.row, 1);
        if (button.matches('[data-layout-add-card]')) sections[i.section].rows[i.row].columns[i.column].cards.push(emptyCard());
        if (button.matches('[data-layout-remove-card]')) sections[i.section].rows[i.row].columns[i.column].cards.splice(i.card, 1);
        if (!sections.length) sections.push(emptySection());
        render();
    });

    const form = builder.closest('form');
    if (form) {
        form.addEventListener('submit', sync);
    }
    render();
});
</script>
