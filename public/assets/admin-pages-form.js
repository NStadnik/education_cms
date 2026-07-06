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
    let expandedSections = new Set([0]);

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
            const isExpanded = expandedSections.has(sectionIndex);
            const rowCount = section.rows.length;
            const cardCount = countCards(section);
            const sectionTitle = String(section.title || '').trim() || 'Секція ' + (sectionIndex + 1);
            return '<section class="layout-editor-section" data-section-index="' + sectionIndex + '" data-drag-kind="section">' +
                '<div class="layout-editor-section-head">' +
                    dragHandle('Перетягнути секцію') +
                    '<button class="layout-editor-toggle" type="button" data-layout-toggle-section aria-expanded="' + (isExpanded ? 'true' : 'false') + '" title="' + (isExpanded ? 'Згорнути секцію' : 'Розгорнути секцію') + '">' +
                        '<span class="mdi ' + (isExpanded ? 'mdi-chevron-up' : 'mdi-chevron-down') + '" aria-hidden="true"></span>' +
                    '</button>' +
                    '<button class="layout-editor-section-summary" type="button" data-layout-toggle-section aria-expanded="' + (isExpanded ? 'true' : 'false') + '">' +
                        '<strong>' + escapeHtml(sectionTitle) + '</strong>' +
                        '<span>' + backgroundLabel(section.background) + ' фон</span>' +
                    '</button>' +
                    '<div class="layout-editor-counts">' +
                        '<span class="layout-editor-count">' + rowCount + ' ряд.</span>' +
                        '<span class="layout-editor-count">' + cardCount + ' карт.</span>' +
                    '</div>' +
                    '<div class="layout-editor-section-actions">' +
                        '<button class="button secondary compact" type="button" data-layout-add-row><span class="mdi mdi-view-column-outline"></span><span>Ряд</span></button>' +
                        '<button class="button danger compact" type="button" data-layout-remove-section title="Видалити секцію"><span class="mdi mdi-delete-outline"></span></button>' +
                    '</div>' +
                '</div>' +
                '<div class="layout-editor-section-body"' + (isExpanded ? '' : ' hidden') + '>' +
                    '<div class="layout-editor-section-settings">' +
                        '<label>Назва секції<input data-layout-field="section.title" value="' + escapeHtml(section.title) + '" placeholder="Необов’язково"></label>' +
                        '<label>Фон<select data-layout-field="section.background">' +
                            option('default', 'Звичайний', section.background) +
                            option('light', 'Світлий', section.background) +
                            option('accent', 'Акцентний', section.background) +
                        '</select></label>' +
                    '</div>' +
                    '<div class="layout-editor-rows">' + section.rows.map(function (row, rowIndex) {
                        return renderRow(sectionIndex, rowIndex, row);
                    }).join('') + '</div>' +
                '</div>' +
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

    function countCards(section) {
        return section.rows.reduce(function (total, row) {
            return total + row.columns.reduce(function (rowTotal, col) {
                return rowTotal + col.cards.length;
            }, 0);
        }, 0);
    }

    function backgroundLabel(background) {
        return {
            default: 'Звичайний',
            light: 'Світлий',
            accent: 'Акцентний'
        }[background] || 'Звичайний';
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
        if (button.matches('[data-layout-toggle-section]')) {
            if (expandedSections.has(i.section)) {
                expandedSections.delete(i.section);
            } else {
                expandedSections.add(i.section);
            }
            render();
            return;
        }
        if (button.matches('[data-layout-add-section]')) {
            sections.push(emptySection());
            expandedSections.add(sections.length - 1);
        }
        if (button.matches('[data-layout-template]')) {
            sections.push(presetSection(button.dataset.layoutTemplate));
            expandedSections.add(sections.length - 1);
        }
        if (button.matches('[data-layout-remove-section]')) {
            sections.splice(i.section, 1);
            expandedSections = new Set([Math.max(0, Math.min(i.section, sections.length - 1))]);
        }
        if (button.matches('[data-layout-add-row]')) {
            sections[i.section].rows.push(rowFromPreset('col-md-12'));
            expandedSections.add(i.section);
        }
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
        if (!sections.length) {
            sections.push(emptySection());
            expandedSections = new Set([0]);
        }
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
