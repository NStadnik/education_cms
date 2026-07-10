document.addEventListener('DOMContentLoaded', function () {
    const builder = document.querySelector('[data-layout-builder]');
    if (!builder) {
        return;
    }

    const list = builder.querySelector('[data-layout-sections]');
    const hidden = document.querySelector('[data-layout-json]');
    const simpleTextarea = document.querySelector('textarea[name="blocks_text"]');
    const blockCountNode = document.querySelector('[data-page-block-count]');
    const modeInput = document.querySelector('[data-editor-mode]');
    const simplePanel = document.querySelector('[data-simple-editor-panel]');
    const advancedPanel = document.querySelector('[data-advanced-editor-panel]');
    const modeButtons = document.querySelectorAll('[data-editor-mode-button]');
    const exportField = document.querySelector('[data-layout-export-field]');
    const importField = document.querySelector('[data-layout-import-field]');
    const exampleField = document.querySelector('[data-layout-example-field]');
    const importStatus = document.querySelector('[data-layout-import-status]');
    const sectionCountNode = builder.querySelector('[data-layout-section-count]');
    const rowCountNode = builder.querySelector('[data-layout-row-count]');
    const cardCountNode = builder.querySelector('[data-layout-card-count]');
    const importExportNode = document.getElementById('layoutImportExportModal');
    const importExportModal = importExportNode && window.bootstrap ? new window.bootstrap.Modal(importExportNode) : null;
    const cardModalNode = document.getElementById('layoutCardModal');
    const cardModal = cardModalNode && window.bootstrap ? new window.bootstrap.Modal(cardModalNode) : null;
    const cardModalError = cardModalNode ? cardModalNode.querySelector('[data-card-modal-error]') : null;
    const cardModalPreview = cardModalNode ? cardModalNode.querySelector('[data-card-modal-preview]') : null;
    const cardImagePreview = cardModalNode ? cardModalNode.querySelector('[data-card-image-preview]') : null;
    const sectionPickerNode = document.getElementById('layoutSectionPickerModal');
    const sectionPickerModal = sectionPickerNode && window.bootstrap ? new window.bootstrap.Modal(sectionPickerNode) : null;
    const imagePickerNode = document.getElementById('layoutCardImagePickerModal');
    const imagePickerModal = imagePickerNode && window.bootstrap ? new window.bootstrap.Modal(imagePickerNode) : null;
    const cardStyles = ['default', 'accent', 'plain', 'feature', 'media', 'cta', 'stat', 'quote', 'contact', 'form'];
    const cardTemplates = {
        feature: {
            style: 'feature',
            title: 'Ключова перевага',
            text: '<p>Коротко опишіть важливу перевагу, послугу або напрям роботи.</p>',
            image: '',
            button_text: '',
            button_url: ''
        },
        media: {
            style: 'media',
            title: 'Історія або подія',
            text: '<p>Додайте короткий опис матеріалу, новини або важливого розділу.</p>',
            image: '',
            button_text: 'Переглянути',
            button_url: '#'
        },
        cta: {
            style: 'cta',
            title: 'Готові дізнатися більше?',
            text: '<p>Додайте короткий заклик, який підводить відвідувача до наступної дії.</p>',
            image: '',
            button_text: 'Перейти',
            button_url: '#'
        },
        stat: {
            style: 'stat',
            title: '95%',
            text: '<p>Підпис до показника або коротке пояснення цифри.</p>',
            image: '',
            button_text: '',
            button_url: ''
        },
        quote: {
            style: 'quote',
            title: 'Цитата',
            text: '<blockquote>Додайте важливу думку, відгук або коротку цитату.</blockquote>',
            image: '',
            button_text: '',
            button_url: ''
        },
        contact: {
            style: 'contact',
            title: 'Контакти',
            text: '<p><strong>Телефон:</strong> +380 XX XXX XX XX<br><strong>Email:</strong> info@example.com</p>',
            image: '',
            button_text: 'Написати',
            button_url: 'mailto:info@example.com'
        },
        announcement: {
            style: 'accent',
            title: 'Важливе оголошення',
            text: '<p>Коротко опишіть важливу інформацію, терміни або умови, на які потрібно звернути увагу.</p>',
            image: '',
            button_text: 'Докладніше',
            button_url: '#'
        },
        document: {
            style: 'feature',
            title: 'Документ',
            text: '<p>Додайте назву документа, коротке пояснення та посилання на файл або сторінку з деталями.</p>',
            image: '',
            button_text: 'Відкрити документ',
            button_url: '#'
        },
        schedule: {
            style: 'plain',
            title: 'Розклад',
            text: '<p><strong>Понеділок:</strong> додайте час або подію<br><strong>Вівторок:</strong> додайте час або подію</p>',
            image: '',
            button_text: '',
            button_url: ''
        },
        faq: {
            style: 'default',
            title: 'Питання та відповідь',
            text: '<p><strong>Питання:</strong> сформулюйте часте питання.<br><strong>Відповідь:</strong> додайте коротку й зрозумілу відповідь.</p>',
            image: '',
            button_text: '',
            button_url: ''
        },
        step: {
            style: 'feature',
            title: 'Крок 1',
            text: '<p>Опишіть перший крок процесу: що потрібно зробити, підготувати або перевірити.</p>',
            image: '',
            button_text: '',
            button_url: ''
        },
        download: {
            style: 'media',
            title: 'Матеріал для завантаження',
            text: '<p>Опишіть файл або корисний матеріал, який відвідувач може відкрити чи завантажити.</p>',
            image: '',
            button_text: 'Завантажити',
            button_url: '#'
        },
        person: {
            style: 'contact',
            title: 'Відповідальна особа',
            text: '<p><strong>Імʼя Прізвище</strong><br>Посада або роль<br>Email: name@example.com</p>',
            image: '',
            button_text: 'Звʼязатися',
            button_url: 'mailto:name@example.com'
        },
        warning: {
            style: 'accent',
            title: 'Зверніть увагу',
            text: '<p>Додайте попередження, умову або коротку інструкцію, яку важливо прочитати перед дією.</p>',
            image: '',
            button_text: '',
            button_url: ''
        }
    };
    let cardModalState = null;
    let imagePickerItems = [];
    let imagePickerSearchTimer = null;
    const imagePickerState = {offset: 0, limit: 10, total: 0, hasMore: false, loading: false, token: 0};
    const layoutExample = [{
        type: 'layout',
        title: 'Hero та базова картка',
        background: 'accent',
        rows: [{
            columns: [{
                width: 'col-md-12',
                cards: [{
                    style: 'cta',
                    title: 'Головний заклик сторінки',
                    text: '<p>Стислий вступний текст із головною пропозицією, який може містити <strong>форматування</strong>.</p>',
                    image: '/uploads/example/hero.jpg',
                    button_text: 'Основна дія',
                    button_url: '/page/contact',
                    links: [
                        {label: 'Основна дія', url: '/page/contact'},
                        {label: 'Докладніше', url: '/page/about'}
                    ]
                }, {
                    style: 'default',
                    title: 'Звичайна картка',
                    text: '<p>Базовий стиль для універсального текстового блоку.</p>',
                    image: '',
                    button_text: '',
                    button_url: '',
                    links: []
                }]
            }]
        }]
    }, {
        type: 'layout',
        title: 'Інформаційна сітка',
        background: 'light',
        rows: [{
            columns: [{
                width: 'col-md-4',
                cards: [{
                    style: 'feature',
                    title: 'Перевага',
                    text: '<p>Короткий опис переваги або послуги.</p>',
                    image: '',
                    button_text: '',
                    button_url: '',
                    links: []
                }]
            }, {
                width: 'col-md-4',
                cards: [{
                    style: 'accent',
                    title: 'Акцент',
                    text: '<p>Важлива інформація, яку потрібно виділити.</p>',
                    image: '',
                    button_text: 'Перейти',
                    button_url: '/page/details',
                    links: [{label: 'Перейти', url: '/page/details'}]
                }]
            }, {
                width: 'col-md-4',
                cards: [{
                    style: 'plain',
                    title: 'Без рамки',
                    text: '<p>Легкий блок без візуальної рамки.</p>',
                    image: '',
                    button_text: '',
                    button_url: '',
                    links: []
                }]
            }]
        }]
    }, {
        type: 'layout',
        title: 'Медіа, статистика, цитата і контакти',
        background: 'default',
        rows: [{
            columns: [{
                width: 'col-md-8',
                cards: [{
                    style: 'media',
                    title: 'Медіа-картка',
                    text: '<p>Текст поруч або під зображенням. Підходить для новин, подій і послуг.</p>',
                    image: '/uploads/example/media.jpg',
                    button_text: 'Відкрити матеріал',
                    button_url: '/news/example',
                    links: [
                        {label: 'Відкрити матеріал', url: '/news/example'},
                        {label: 'Завантажити файл', url: '/uploads/example/document.pdf'}
                    ]
                }]
            }, {
                width: 'col-md-4',
                cards: [{
                    style: 'stat',
                    title: '120+',
                    text: '<p>Підпис до цифри або показника.</p>',
                    image: '',
                    button_text: '',
                    button_url: '',
                    links: []
                }]
            }]
        }, {
            columns: [{
                width: 'col-md-6',
                cards: [{
                    style: 'quote',
                    title: 'Цитата',
                    text: '<blockquote>Важлива думка, відгук або коротке повідомлення.</blockquote>',
                    image: '',
                    button_text: '',
                    button_url: '',
                    links: []
                }]
            }, {
                width: 'col-md-6',
                cards: [{
                    style: 'contact',
                    title: 'Контакти',
                    text: '<p><strong>Телефон:</strong> +380 XX XXX XX XX<br><strong>Email:</strong> info@example.com</p>',
                    image: '',
                    button_text: 'Написати',
                    button_url: 'mailto:info@example.com',
                    links: [
                        {label: 'Написати', url: 'mailto:info@example.com'},
                        {label: 'Карта', url: 'https://maps.google.com/'}
                    ]
                }]
            }]
        }]
    }];
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
        if (type === 'blank') {
            return emptySection();
        }
        if (type === 'hero') {
            return {
                type: 'layout',
                title: 'Перший екран',
                background: 'accent',
                rows: [{
                    columns: [{
                        width: 'col-md-12',
                        cards: [{
                            style: 'cta',
                            title: 'Заголовок сторінки',
                            text: '<p>Короткий вступний текст, який пояснює головну пропозицію сторінки.</p>',
                            image: '',
                            button_text: 'Дізнатися більше',
                            button_url: '#'
                        }]
                    }]
                }]
            };
        }
        if (type === 'media-story' || type === 'two-cards') {
            return {
                type: 'layout',
                title: 'Історія або послуга',
                background: 'default',
                rows: [{
                    columns: [{
                        width: 'col-md-8',
                        cards: [{
                            style: 'media',
                            title: 'Назва блоку',
                            text: '<p>Опишіть важливу подію, напрям роботи або послугу. Додайте зображення через медіафайли.</p>',
                            image: '',
                            button_text: 'Переглянути',
                            button_url: '#'
                        }]
                    }, {
                        width: 'col-md-4',
                        cards: [{
                            style: 'feature',
                            title: 'Ключовий акцент',
                            text: '<p>Коротке уточнення, яке підсилює основний матеріал.</p>',
                            image: '',
                            button_text: '',
                            button_url: ''
                        }]
                    }]
                }]
            };
        }
        if (type === 'feature-grid' || type === 'three-cards') {
            return {
                type: 'layout',
                title: 'Переваги',
                background: 'light',
                rows: [{
                    columns: [{
                        width: 'col-md-4',
                        cards: [{
                            style: 'feature',
                            title: 'Перевага 1',
                            text: '<p>Коротко опишіть першу перевагу або напрям.</p>',
                            image: '',
                            button_text: '',
                            button_url: ''
                        }]
                    }, {
                        width: 'col-md-4',
                        cards: [{
                            style: 'feature',
                            title: 'Перевага 2',
                            text: '<p>Коротко опишіть другу перевагу або напрям.</p>',
                            image: '',
                            button_text: '',
                            button_url: ''
                        }]
                    }, {
                        width: 'col-md-4',
                        cards: [{
                            style: 'feature',
                            title: 'Перевага 3',
                            text: '<p>Коротко опишіть третю перевагу або напрям.</p>',
                            image: '',
                            button_text: '',
                            button_url: ''
                        }]
                    }]
                }]
            };
        }
        if (type === 'stats') {
            return {
                type: 'layout',
                title: 'Показники',
                background: 'default',
                rows: [{
                    columns: [{
                        width: 'col-md-4',
                        cards: [{style: 'stat', title: '95%', text: '<p>Короткий підпис до показника.</p>', image: '', button_text: '', button_url: ''}]
                    }, {
                        width: 'col-md-4',
                        cards: [{style: 'stat', title: '120+', text: '<p>Короткий підпис до показника.</p>', image: '', button_text: '', button_url: ''}]
                    }, {
                        width: 'col-md-4',
                        cards: [{style: 'stat', title: '24/7', text: '<p>Короткий підпис до показника.</p>', image: '', button_text: '', button_url: ''}]
                    }]
                }]
            };
        }
        if (type === 'cta') {
            return {
                type: 'layout',
                title: '',
                background: 'default',
                rows: [{
                    columns: [{
                        width: 'col-md-12',
                        cards: [{
                            style: 'cta',
                            title: 'Готові перейти до наступного кроку?',
                            text: '<p>Додайте короткий заклик до дії для відвідувачів сторінки.</p>',
                            image: '',
                            button_text: 'Зв’язатися',
                            button_url: '#'
                        }]
                    }]
                }]
            };
        }
        if (type === 'contact') {
            return {
                type: 'layout',
                title: 'Контакти',
                background: 'light',
                rows: [{
                    columns: [{
                        width: 'col-md-6',
                        cards: [{
                            style: 'contact',
                            title: 'Зв’язок',
                            text: '<p><strong>Телефон:</strong> +380 XX XXX XX XX<br><strong>Email:</strong> info@example.com</p>',
                            image: '',
                            button_text: 'Написати',
                            button_url: 'mailto:info@example.com'
                        }]
                    }, {
                        width: 'col-md-6',
                        cards: [{
                            style: 'quote',
                            title: 'Графік роботи',
                            text: '<p>Пн-Пт: 09:00-18:00<br>Сб-Нд: вихідний</p>',
                            image: '',
                            button_text: '',
                            button_url: ''
                        }]
                    }]
                }]
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
        const links = normalizeCardLinks(card);
        const primaryLink = links[0] || null;
        return {
            style: cardStyles.includes(card.style) ? card.style : 'default',
            title: card.title || '',
            text: card.text || '',
            image: card.image || '',
            button_text: primaryLink ? primaryLink.label : (card.button_text || ''),
            button_url: primaryLink ? primaryLink.url : (card.button_url || ''),
            links: links,
            form_id: Number(card.form_id || 0)
        };
    }

    function emptyCard() {
        return {style: 'default', title: '', text: '', image: '', button_text: '', button_url: '', links: [], form_id: 0};
    }

    function normalizeCardLinks(card) {
        const links = [];
        if (card && Array.isArray(card.links)) {
            card.links.forEach(function (link) {
                const label = String(link && (link.label || link.text || link.title) || '').trim();
                const url = String(link && (link.url || link.href) || '').trim();
                if (label !== '' && url !== '') {
                    links.push({label: label, url: url});
                }
            });
        }
        const buttonText = String(card && card.button_text || '').trim();
        const buttonUrl = String(card && card.button_url || '').trim();
        if (buttonText !== '' && buttonUrl !== '' && !links.some(function (link) {
            return link.label === buttonText && link.url === buttonUrl;
        })) {
            links.unshift({label: buttonText, url: buttonUrl});
        }
        return links.slice(0, 12);
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

    function rowFromPresetPreservingCards(preset, currentRow) {
        const nextRow = rowFromPreset(preset);
        const currentColumns = Array.isArray(currentRow && currentRow.columns) ? currentRow.columns : [];
        currentColumns.forEach(function (currentColumn, columnIndex) {
            const cards = Array.isArray(currentColumn.cards) ? currentColumn.cards : [];
            if (!cards.length) {
                return;
            }
            const targetIndex = Math.min(columnIndex, nextRow.columns.length - 1);
            nextRow.columns[targetIndex].cards = nextRow.columns[targetIndex].cards.concat(cards);
        });
        return nextRow;
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (character) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character];
        });
    }

    function mediaThumbUrl(value, width, height) {
        let cleanPath = String(value || '').trim();
        if (!cleanPath) {
            return '';
        }

        if (/^https?:\/\//i.test(cleanPath)) {
            try {
                const parsed = new URL(cleanPath);
                if (parsed.origin !== window.location.origin) {
                    return cleanPath;
                }
                cleanPath = parsed.pathname;
            } catch (error) {
                return cleanPath;
            }
        }

        cleanPath = cleanPath.replace(/^\/+/, '');
        if (cleanPath.indexOf('uploads/') === 0) {
            cleanPath = cleanPath.slice(8);
        }
        if (cleanPath.indexOf('thumb/') === 0) {
            cleanPath = cleanPath.slice(6);
        }
        cleanPath = cleanPath.split('?')[0];

        if (!cleanPath || cleanPath.indexOf('/') === -1 && cleanPath.indexOf('.') === -1) {
            return String(value || '').trim();
        }

        return '/thumb/' + encodeURI(cleanPath) + '?w=' + Number(width || 360) + '&h=' + Number(height || 270) + '&fit=cover';
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

    function cardTextHtml(value) {
        const text = String(value || '').trim();
        if (text === '') {
            return '';
        }

        return hasHtml(text) ? text : '<p>' + escapeHtml(text).replace(/\n{2,}/g, '</p><p>').replace(/\n/g, '<br>') + '</p>';
    }

    function layoutToSimpleHtml() {
        return sections.map(function (section) {
            const sectionParts = [];
            const sectionTitle = String(section.title || '').trim();
            if (sectionTitle !== '') {
                sectionParts.push('<h2>' + escapeHtml(sectionTitle) + '</h2>');
            }

            section.rows.forEach(function (row) {
                row.columns.forEach(function (col) {
                    col.cards.forEach(function (card) {
                        const cardParts = [];
                        const image = String(card.image || '').trim();
                        const title = String(card.title || '').trim();
                        const text = cardTextHtml(card.text);
                        const links = normalizeCardLinks(card);

                        if (image !== '') {
                            cardParts.push('<p><img src="' + escapeHtml(image) + '" alt="' + escapeHtml(title) + '"></p>');
                        }
                        if (title !== '') {
                            cardParts.push('<h3>' + escapeHtml(title) + '</h3>');
                        }
                        if (text !== '') {
                            cardParts.push(text);
                        }
                        if (links.length) {
                            cardParts.push('<p>' + links.map(function (link) {
                                return '<a href="' + escapeHtml(link.url) + '">' + escapeHtml(link.label) + '</a>';
                            }).join('<br>') + '</p>');
                        }
                        if (cardParts.length) {
                            sectionParts.push(cardParts.join('\n'));
                        }
                    });
                });
            });

            return sectionParts.join('\n\n');
        }).filter(function (html) {
            return html.trim() !== '';
        }).join('\n\n');
    }

    function updateSimpleEditorFromLayout() {
        if (!simpleTextarea) {
            return;
        }

        const html = layoutToSimpleHtml();
        simpleTextarea.value = html;

        if (window.TiptapEditor) {
            window.TiptapEditor.setContent(simpleTextarea, html);
        }
    }

    function setImportStatus(message, isError) {
        if (!importStatus) {
            return;
        }
        importStatus.textContent = message || '';
        importStatus.classList.toggle('is-error', Boolean(isError));
        importStatus.classList.toggle('is-success', Boolean(message && !isError));
    }

    function exportLayoutJson() {
        return JSON.stringify(sections, null, 2);
    }

    function updateExportField() {
        if (exportField) {
            exportField.value = exportLayoutJson();
        }
    }

    function exampleLayoutJson() {
        return JSON.stringify(layoutExample, null, 2);
    }

    function updateExampleField() {
        if (exampleField) {
            exampleField.value = exampleLayoutJson();
        }
    }

    function parseImportedLayoutJson(value) {
        let data;
        try {
            data = JSON.parse(String(value || ''));
        } catch (error) {
            throw new Error('JSON має помилку синтаксису.');
        }

        if (data && !Array.isArray(data) && typeof data === 'object') {
            if (Array.isArray(data.sections)) {
                data = data.sections;
            } else if (Array.isArray(data.blocks)) {
                data = data.blocks;
            } else if (typeof data.blocks_json === 'string') {
                try {
                    data = JSON.parse(data.blocks_json);
                } catch (error) {
                    throw new Error('Поле blocks_json містить некоректний JSON.');
                }
            }
        }

        if (!Array.isArray(data)) {
            throw new Error('Очікується масив секцій або обʼєкт із sections/blocks.');
        }

        const normalized = normalizeInitial(data).filter(function (section) {
            return section && Array.isArray(section.rows);
        });
        if (!normalized.length) {
            throw new Error('У JSON немає секцій для імпорту.');
        }

        return normalized;
    }

    function applyImportedLayout() {
        if (!importField) {
            return;
        }
        const raw = importField.value.trim();
        if (raw === '') {
            setImportStatus('Вставте JSON для імпорту.', true);
            return;
        }

        try {
            sections = parseImportedLayoutJson(raw);
            expandedSections = new Set([0]);
            setEditorMode('advanced');
            setImportStatus('Імпортовано секцій: ' + sections.length + '.', false);
            render();
        } catch (error) {
            setImportStatus(error.message || 'Не вдалося імпортувати JSON.', true);
        }
    }

    function copyExportJson() {
        const json = exportLayoutJson();
        updateExportField();
        if (exportField) {
            exportField.focus();
            exportField.select();
        }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(json).then(function () {
                setImportStatus('JSON скопійовано в буфер.', false);
            }).catch(function () {
                setImportStatus('JSON готовий у полі експорту.', false);
            });
            return;
        }
        setImportStatus('JSON готовий у полі експорту.', false);
    }

    function copyExampleJson() {
        const json = exampleLayoutJson();
        updateExampleField();
        if (exampleField) {
            exampleField.focus();
            exampleField.select();
        }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(json).then(function () {
                setImportStatus('Приклад JSON скопійовано.', false);
            }).catch(function () {
                setImportStatus('Приклад готовий у полі для копіювання.', false);
            });
            return;
        }
        setImportStatus('Приклад готовий у полі для копіювання.', false);
    }

    function useExampleJson() {
        if (!importField) {
            return;
        }
        updateExampleField();
        importField.value = exampleLayoutJson();
        importField.focus();
        importField.select();
        setImportStatus('Приклад вставлено в імпорт. Натисніть “Імпортувати”, щоб застосувати.', false);
    }

    function openImportExportModal() {
        updateExportField();
        updateExampleField();
        setImportStatus('', false);
        if (importExportModal) {
            importExportModal.show();
            if (exportField) {
                importExportNode.addEventListener('shown.bs.modal', function () {
                    exportField.focus();
                    exportField.select();
                }, {once: true});
            }
            return;
        }
        if (exportField) {
            exportField.focus();
            exportField.select();
        }
    }

    function render() {
        const totalRows = sections.reduce(function (total, section) {
            return total + section.rows.length;
        }, 0);
        const totalCards = sections.reduce(function (total, section) {
            return total + countCards(section);
        }, 0);
        if (sectionCountNode) sectionCountNode.textContent = String(sections.length);
        if (rowCountNode) rowCountNode.textContent = String(totalRows);
        if (cardCountNode) cardCountNode.textContent = String(totalCards);

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
                        '<button class="button secondary compact" type="button" data-layout-add-row title="Додати ряд до секції"><span class="mdi mdi-plus"></span><span>Додати ряд</span></button>' +
                        '<button class="button danger compact" type="button" data-layout-remove-section title="' + (sections.length === 1 ? 'На сторінці має залишитися хоча б одна секція' : 'Видалити секцію') + '"' + (sections.length === 1 ? ' disabled' : '') + '><span class="mdi mdi-delete-outline"></span><span class="visually-hidden">Видалити секцію</span></button>' +
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
                '<button class="button danger compact" type="button" data-layout-remove-row title="' + (sections[sectionIndex].rows.length === 1 ? 'У секції має залишитися хоча б один ряд' : 'Видалити ряд') + '"' + (sections[sectionIndex].rows.length === 1 ? ' disabled' : '') + '><span class="mdi mdi-delete-outline"></span><span class="visually-hidden">Видалити ряд</span></button>' +
            '</div>' +
            '<div class="layout-editor-columns">' + row.columns.map(function (col, colIndex) {
                return renderColumn(sectionIndex, rowIndex, colIndex, col);
            }).join('') + '</div>' +
        '</div>';
    }

    function renderColumn(sectionIndex, rowIndex, colIndex, col) {
        return '<div class="layout-editor-column" data-column-index="' + colIndex + '" data-card-drop-zone>' +
            '<div class="layout-editor-column-head">' +
                '<strong>' + columnLabel(col.width) + '</strong>' +
                '<button class="button secondary compact" type="button" data-layout-add-card><span class="mdi mdi-card-plus-outline"></span><span>Картка</span></button>' +
            '</div>' +
            (col.cards.length ? col.cards.map(function (card, cardIndex) {
                return renderCard(cardIndex, card);
            }).join('') : '<button class="layout-editor-empty" type="button" data-layout-add-card><span class="mdi mdi-card-plus-outline" aria-hidden="true"></span><strong>Додати першу картку</strong><span>Заголовок, текст, фото або кнопка</span></button>') +
        '</div>';
    }

    function renderCard(cardIndex, card) {
        const title = String(card.title || '').trim();
        const image = String(card.image || '').trim();
        const links = normalizeCardLinks(card);
        const style = cardStyles.includes(card.style) ? card.style : 'default';

        return '<article class="layout-editor-card layout-editor-card-preview page-layout-card page-layout-card-' + escapeHtml(style) + '" data-card-index="' + cardIndex + '" data-drag-kind="card">' +
            '<div class="layout-editor-card-head">' +
                dragHandle('Перетягнути картку') +
                (image ? '<img class="layout-editor-card-thumb" src="' + escapeHtml(mediaThumbUrl(image, 96, 96)) + '" alt="' + escapeHtml(title) + '">' : '') +
                '<strong>' + escapeHtml(title || ('Картка ' + (cardIndex + 1))) + '</strong>' +
                '<div class="layout-editor-card-actions">' +
                    '<button class="button secondary compact" type="button" data-layout-edit-card title="Редагувати картку"><span class="mdi mdi-pencil-outline"></span></button>' +
                    '<button class="button danger compact" type="button" data-layout-remove-card title="Видалити картку"><span class="mdi mdi-delete-outline"></span></button>' +
                '</div>' +
            '</div>' +
            '<div class="layout-editor-card-preview-body">' +
                (image ? '<img src="' + escapeHtml(mediaThumbUrl(image, 640, 360)) + '" alt="' + escapeHtml(title) + '">' : '') +
                (title ? '<h3>' + escapeHtml(title) + '</h3>' : '') +
                '<div class="rich-content">' + cardTextPreview(card.text) + '</div>' +
                renderCardLinkActions(links, 'compact') +
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
        updateExportField();
        if (!modeInput || modeInput.value === 'advanced') {
            updateSimpleEditorFromLayout();
        }
        if (blockCountNode) {
            blockCountNode.textContent = String(sections.length);
        }
    }

    function setEditorMode(mode) {
        const isAdvanced = mode === 'advanced';
        if (!isAdvanced) {
            updateSimpleEditorFromLayout();
        }
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
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
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
            sections[i.section].rows[i.row] = rowFromPresetPreservingCards(preset.value, sections[i.section].rows[i.row]);
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
        ['style', 'title', 'text', 'image', 'button_text', 'button_url', 'form_id'].forEach(function (name) {
            const field = cardModalField(name);
            if (field) {
                field.value = card[name] || (name === 'style' ? 'default' : (name === 'form_id' ? '0' : ''));
                if (name === 'text' && window.TiptapEditor) {
                    window.TiptapEditor.setContent(field, field.value);
                }
            }
        });
        renderCardLinksEditor(normalizeCardLinks(card || {}));
    }

    function readCardModalValues() {
        const card = emptyCard();
        const textField = cardModalField('text');
        if (textField && window.TiptapEditor) {
            window.TiptapEditor.syncOne(textField);
        }
        ['style', 'title', 'text', 'image', 'button_text', 'button_url', 'form_id'].forEach(function (name) {
            const field = cardModalField(name);
            if (field) {
                card[name] = name === 'form_id' ? Number(field.value || 0) : field.value.trim();
            }
        });
        card.links = readCardLinksEditor();
        if (card.links.length) {
            card.button_text = card.links[0].label;
            card.button_url = card.links[0].url;
        }
        if (!cardStyles.includes(card.style)) {
            card.style = 'default';
        }

        return card;
    }

    function cardLinkList() {
        return cardModalNode ? cardModalNode.querySelector('[data-card-link-list]') : null;
    }

    function readCardLinksEditor() {
        const list = cardLinkList();
        if (!list) {
            return [];
        }
        return Array.from(list.querySelectorAll('[data-card-link-row]')).map(function (row) {
            const labelField = row.querySelector('[data-card-link-label]');
            const urlField = row.querySelector('[data-card-link-url]');
            return {
                label: String(labelField ? labelField.value : '').trim(),
                url: String(urlField ? urlField.value : '').trim()
            };
        }).filter(function (link) {
            return link.label !== '' && link.url !== '';
        }).slice(0, 12);
    }

    function renderCardLinksEditor(links) {
        const list = cardLinkList();
        const empty = cardModalNode ? cardModalNode.querySelector('[data-card-link-empty]') : null;
        if (!list) {
            return;
        }
        const normalized = (Array.isArray(links) ? links : []).slice(0, 12);
        list.innerHTML = normalized.map(function (link, index) {
            return '<div class="layout-card-link-row" data-card-link-row data-card-link-index="' + index + '">' +
                '<label>Текст<input data-card-link-label value="' + escapeHtml(link.label || '') + '" placeholder="Детальніше"></label>' +
                '<label>URL<input data-card-link-url value="' + escapeHtml(link.url || '') + '" placeholder="/page/about"></label>' +
                '<button class="button secondary compact" type="button" data-card-link-pick title="Обрати посилання"><span class="mdi mdi-link-plus" aria-hidden="true"></span></button>' +
                '<button class="button danger compact" type="button" data-card-link-remove title="Видалити покликання"><span class="mdi mdi-delete-outline" aria-hidden="true"></span></button>' +
            '</div>';
        }).join('');
        if (empty) {
            empty.hidden = normalized.length > 0;
        }
    }

    function setCardLinksEditor(links) {
        renderCardLinksEditor(links);
        setCardModalTemplate('');
        updateCardModalPreview();
    }

    function addCardLink(link) {
        const links = readCardLinksEditor();
        links.push({
            label: String(link && (link.cleanLabel || link.label) || '').trim() || 'Посилання',
            url: String(link && link.url || '').trim()
        });
        setCardLinksEditor(links);
    }

    function renderCardLinkActions(links, sizeClass) {
        const normalized = Array.isArray(links) ? links : [];
        if (!normalized.length) {
            return '';
        }
        return '<div class="layout-card-link-actions">' + normalized.map(function (link) {
            const classes = 'button read-more layout-card-preview-button' + (sizeClass ? ' ' + sizeClass : '');
            return '<span class="' + classes + '">' + escapeHtml(link.label || link.url) + '</span>';
        }).join('') + '</div>';
    }

    function setCardModalTemplate(value) {
        if (cardModalNode) {
            cardModalNode.querySelectorAll('[data-card-template-quick]').forEach(function (button) {
                button.classList.toggle('is-active', button.dataset.cardTemplateQuick === value);
            });
        }
    }

    function applyCardTemplate(name) {
        const template = cardTemplates[name];
        if (!template) {
            return;
        }

        setCardModalValues(template);
        setCardModalTemplate(name);
        setCardModalError('');
        updateCardModalPreview();
    }

    function updateCardModalPreview() {
        if (!cardModalPreview) {
            return;
        }

        const card = readCardModalValues();
        const title = String(card.title || '').trim();
        const text = String(card.text || '').trim();
        const image = String(card.image || '').trim();
        const links = normalizeCardLinks(card);
        const style = cardStyles.includes(card.style) ? card.style : 'default';
        const hasContent = title !== '' || text !== '' || image !== '' || links.length > 0;

        updateCardModalStats(card, style);
        updateCardImagePreview(image);
        cardModalPreview.className = 'card content-card page-layout-card page-layout-card-' + style;
        if (!hasContent) {
            cardModalPreview.innerHTML = '<div class="layout-card-modal-preview-empty">Заповніть картку</div>';
            return;
        }

        cardModalPreview.innerHTML =
            (image ? '<img src="' + escapeHtml(mediaThumbUrl(image, 640, 360)) + '" alt="' + escapeHtml(title) + '">' : '') +
            (title ? '<h3>' + escapeHtml(title) + '</h3>' : '') +
            (text ? '<div class="rich-content">' + cardTextPreview(text) + '</div>' : '') +
            renderCardLinkActions(links, '');
    }

    function updateCardModalStats(card, style) {
        if (!cardModalNode) {
            return;
        }

        const textLength = String(card.text || '').replace(/<[^>]*>/g, '').trim().length;
        const styleNode = cardModalNode.querySelector('[data-card-modal-stat-style]');
        const textNode = cardModalNode.querySelector('[data-card-modal-stat-text]');
        const imageNode = cardModalNode.querySelector('[data-card-modal-stat-image]');
        const buttonNode = cardModalNode.querySelector('[data-card-modal-stat-button]');
        const previewLabel = cardModalNode.querySelector('[data-card-modal-preview-label]');
        if (styleNode) {
            styleNode.textContent = cardStyleLabel(style);
        }
        if (textNode) {
            textNode.textContent = textLength + ' ' + plural(textLength, ['символ', 'символи', 'символів']);
        }
        if (imageNode) {
            imageNode.textContent = card.image ? 'Є зображення' : 'Без зображення';
        }
        if (buttonNode) {
            const linkCount = normalizeCardLinks(card).length;
            buttonNode.textContent = linkCount ? linkCount + ' ' + plural(linkCount, ['покликання', 'покликання', 'покликань']) : 'Без покликань';
        }
        if (previewLabel) {
            previewLabel.textContent = cardStyleLabel(style);
        }
    }

    function updateCardImagePreview(image) {
        if (!cardImagePreview) {
            return;
        }
        if (!image) {
            cardImagePreview.hidden = true;
            cardImagePreview.innerHTML = '';
            return;
        }

        cardImagePreview.hidden = false;
        cardImagePreview.innerHTML =
            '<img src="' + escapeHtml(mediaThumbUrl(image, 112, 112)) + '" alt="">' +
            '<span>' + escapeHtml(image) + '</span>';
    }

    function cardStyleLabel(style) {
        return {
            default: 'Картка',
            accent: 'Акцент',
            plain: 'Без рамки',
            feature: 'Сучасна інформаційна',
            media: 'Медіа',
            cta: 'CTA',
            stat: 'Показник',
            quote: 'Цитата',
            contact: 'Контакти'
        }[style] || 'Картка';
    }

    function plural(number, forms) {
        const n = Math.abs(number) % 100;
        const n1 = n % 10;
        if (n > 10 && n < 20) {
            return forms[2];
        }
        if (n1 > 1 && n1 < 5) {
            return forms[1];
        }
        if (n1 === 1) {
            return forms[0];
        }
        return forms[2];
    }

    function setCardImage(value) {
        const imageField = cardModalField('image');
        if (!imageField) {
            return;
        }

        imageField.value = value || '';
        setCardModalTemplate('');
        updateCardModalPreview();
    }

    function insertCardTextList(type) {
        const field = cardModalField('text');
        if (!field) {
            return;
        }

        if (type === 'documents') {
            if (!window.AdminLinkPicker) {
                return;
            }
            window.AdminLinkPicker.open({
                multiple: true,
                type: 'media',
                title: 'Обрати документи',
                hint: 'Вибрані медіафайли буде вставлено у текст картки як список покликань.',
                onSelect: function (items) {
                    const selected = Array.isArray(items) ? items : [items];
                    const links = selected.filter(function (item) {
                        return item && item.url;
                    });
                    if (!links.length) {
                        return;
                    }
                    const html = '<ul>' + links.map(function (item) {
                        const label = item.cleanLabel || item.label || item.url || 'Документ';
                        return '<li><a href="' + escapeHtml(item.url) + '">' + escapeHtml(label) + '</a></li>';
                    }).join('') + '</ul>';
                    insertCardTextHtml(html);
                }
            });
            return;
        }

        const templates = {
            ol: '<ol><li>Перший пункт</li><li>Другий пункт</li><li>Третій пункт</li></ol>',
            ul: '<ul><li>Перший пункт</li><li>Другий пункт</li><li>Третій пункт</li></ul>'
        };
        insertCardTextHtml(templates[type] || templates.ul);
    }

    function insertCardTextHtml(html) {
        const field = cardModalField('text');
        if (!field) {
            return;
        }
        if (window.TiptapEditor) {
            window.TiptapEditor.insertContent(field, html);
        } else if (window.TiptapEditor && window.TiptapEditor.insertContent) {
            window.TiptapEditor.insertContent(field, html);
        } else {
            const separator = String(field.value || '').trim() ? '\n\n' : '';
            field.value = String(field.value || '') + separator + html;
        }
        field.dispatchEvent(new Event('input', {bubbles: true}));
        setCardModalTemplate('');
        updateCardModalPreview();
    }

    function openCardImagePicker() {
        if (!imagePickerModal) {
            return;
        }

        imagePickerModal.show();
        const imagePickerBody = imagePickerNode.querySelector('.modal-body');
        if (imagePickerBody) {
            imagePickerBody.scrollTop = 0;
        }
        loadCardImageItems(false);
        queueCardImageMoreCheck();
        const search = imagePickerNode.querySelector('[data-card-image-search]');
        if (search) {
            setTimeout(function () {
                search.focus();
            }, 180);
        }
    }

    async function loadCardImageItems(append) {
        if (!imagePickerNode) {
            return;
        }
        if (imagePickerState.loading && append) {
            return;
        }

        const grid = imagePickerNode.querySelector('[data-card-image-grid]');
        const status = imagePickerNode.querySelector('[data-card-image-status]');
        const search = imagePickerNode.querySelector('[data-card-image-search]');
        const adminBody = document.body;
        const url = new URL(adminBody ? (adminBody.dataset.richMediaPickerUrl || '/admin/media/picker') : '/admin/media/picker', window.location.origin);
        const token = append ? imagePickerState.token : ++imagePickerState.token;
        url.searchParams.set('limit', String(imagePickerState.limit));
        url.searchParams.set('offset', append ? String(imagePickerState.offset) : '0');
        url.searchParams.set('images_only', '1');
        if (search && search.value.trim() !== '') {
            url.searchParams.set('q', search.value.trim());
        }

        imagePickerState.loading = true;
        if (status) {
            status.textContent = append ? 'Підвантаження...' : 'Завантаження...';
        }
        if (grid) {
            grid.classList.add('is-loading');
        }
        if (grid && !append) {
            grid.innerHTML = '<div class="layout-card-image-picker-loading"><span class="mdi mdi-loading mdi-spin" aria-hidden="true"></span><span>Завантаження зображень...</span></div>';
            imagePickerItems = [];
        }

        try {
            const response = await fetch(url.toString(), {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Не вдалося завантажити медіафайли.');
            }
            if (token !== imagePickerState.token) {
                return;
            }

            const images = (data.items || []).filter(function (item) {
                return item && item.is_image;
            });
            imagePickerState.offset = Number(data.next_offset || 0);
            imagePickerState.total = Number(data.total || 0);
            imagePickerState.hasMore = Boolean(data.has_more);

            if (!images.length && imagePickerState.hasMore) {
                imagePickerState.loading = false;
                await loadCardImageItems(true);
                return;
            }

            appendCardImageItems(images, imagePickerItems.length, append);
        } catch (error) {
            if (status) {
                status.textContent = error.message || 'Помилка завантаження.';
            }
            if (grid) {
                grid.classList.remove('is-loading');
                grid.innerHTML = '<div class="layout-card-image-picker-empty">Не вдалося завантажити зображення.</div>';
            }
        } finally {
            if (token === imagePickerState.token) {
                imagePickerState.loading = false;
                queueCardImageMoreCheck();
            }
        }
    }

    function maybeLoadMoreCardImages() {
        if (!imagePickerNode || !imagePickerState.hasMore || imagePickerState.loading) {
            return;
        }
        const imagePickerBody = imagePickerNode.querySelector('.modal-body');
        if (!imagePickerBody) {
            return;
        }
        const nearBottom = imagePickerBody.scrollTop + imagePickerBody.clientHeight >= imagePickerBody.scrollHeight - 160;
        if (nearBottom) {
            loadCardImageItems(true);
        }
    }

    function queueCardImageMoreCheck() {
        window.setTimeout(maybeLoadMoreCardImages, 80);
    }

    function appendCardImageItems(items, startIndex, append) {
        if (!imagePickerNode) {
            return;
        }

        const grid = imagePickerNode.querySelector('[data-card-image-grid]');
        const currentImage = cardModalField('image') ? cardModalField('image').value : '';
        if (!grid) {
            return;
        }

        const status = imagePickerNode.querySelector('[data-card-image-status]');
        if (!append || !imagePickerItems.length) {
            grid.innerHTML = '';
        }

        if (!items.length && !imagePickerItems.length) {
            grid.classList.remove('is-loading');
            grid.innerHTML = '<div class="layout-card-image-picker-empty">Немає зображень за цим запитом.</div>';
            if (status) {
                status.textContent = 'Зображень не знайдено.';
            }
            return;
        }

        imagePickerItems = imagePickerItems.concat(items);
        const html = items.map(function (item, itemIndex) {
            const index = startIndex + itemIndex;
            const selected = currentImage === item.url;
            const thumb = mediaThumbUrl(item.path || item.url, 360, 270);
            return '<button class="layout-card-image-option' + (selected ? ' is-selected' : '') + '" type="button" data-card-image-index="' + index + '">' +
                '<img src="' + escapeHtml(thumb) + '" alt="' + escapeHtml(item.name) + '" loading="lazy">' +
                '<span>' + escapeHtml(item.name) + '</span>' +
                '<small>' + escapeHtml(item.size_label || item.type || '') + '</small>' +
            '</button>';
        }).join('');
        grid.insertAdjacentHTML('beforeend', html);
        grid.classList.remove('is-loading');
        if (status) {
            status.textContent = imagePickerState.hasMore
                ? 'Показано зображень: ' + imagePickerItems.length + '. Прокрутіть нижче, щоб підвантажити ще.'
                : 'Знайдено зображень: ' + imagePickerItems.length + '.';
        }
        queueCardImageMoreCheck();
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
        setCardModalTemplate('');
        const title = cardModalNode.querySelector('[data-card-modal-title]');
        const saveLabel = cardModalNode.querySelector('[data-layout-card-save-label]');
        if (title) {
            title.textContent = isEdit ? 'Редагувати картку' : 'Нова картка';
        }
        if (saveLabel) {
            saveLabel.textContent = isEdit ? 'Оновити картку' : 'Додати картку';
        }
        updateCardModalPreview();
        if (window.TiptapEditor) {
            cardModalNode.addEventListener('shown.bs.modal', function () {
                const textField = cardModalField('text');
                window.TiptapEditor.init(cardModalNode);
                if (textField) {
                    window.TiptapEditor.setContent(textField, textField.value);
                }
            }, {once: true});
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
        cardModalNode.addEventListener('input', function (event) {
            if (event.target.closest('[data-card-modal-field]')) {
                setCardModalTemplate('');
                updateCardModalPreview();
            }
        });
        cardModalNode.addEventListener('change', function (event) {
            if (event.target.closest('[data-card-modal-field]')) {
                setCardModalTemplate('');
                updateCardModalPreview();
            }
        });
        cardModalNode.addEventListener('click', function (event) {
            const templateButton = event.target.closest('[data-card-template-quick]');
            if (!templateButton) {
                return;
            }
            applyCardTemplate(templateButton.dataset.cardTemplateQuick);
        });
        cardModalNode.addEventListener('click', function (event) {
            const listButton = event.target.closest('[data-card-text-list]');
            if (!listButton) {
                return;
            }
            insertCardTextList(listButton.dataset.cardTextList || 'ul');
        });
        const saveCardButton = cardModalNode.querySelector('[data-layout-card-save]');
        const openImagePickerButton = cardModalNode.querySelector('[data-card-image-picker-open]');
        const clearImageButton = cardModalNode.querySelector('[data-card-image-clear]');
        if (openImagePickerButton) {
            openImagePickerButton.addEventListener('click', openCardImagePicker);
        }
        if (clearImageButton) {
            clearImageButton.addEventListener('click', function () {
                setCardImage('');
            });
        }
        cardModalNode.addEventListener('input', function (event) {
            if (event.target.closest('[data-card-link-label], [data-card-link-url]')) {
                setCardModalTemplate('');
                updateCardModalPreview();
            }
        });
        cardModalNode.addEventListener('click', function (event) {
            const addButton = event.target.closest('[data-card-link-library-add]');
            if (addButton) {
                addCardLink({label: '', url: ''});
                const list = cardLinkList();
                const last = list ? list.querySelector('[data-card-link-row]:last-child [data-card-link-label]') : null;
                if (last) {
                    last.focus();
                }
                return;
            }

            const pickAllButton = event.target.closest('[data-card-link-library-pick]');
            if (pickAllButton) {
                if (!window.AdminLinkPicker) {
                    return;
                }
                window.AdminLinkPicker.open({
                    multiple: true,
                    title: 'Обрати посилання',
                    hint: 'Вибрані посилання буде додано до бібліотеки картки.',
                    onSelect: function (items) {
                        (Array.isArray(items) ? items : [items]).forEach(addCardLink);
                    }
                });
                return;
            }

            const pickRowButton = event.target.closest('[data-card-link-pick]');
            if (pickRowButton) {
                const row = pickRowButton.closest('[data-card-link-row]');
                if (!window.AdminLinkPicker || !row) {
                    return;
                }
                window.AdminLinkPicker.open({
                    title: 'Обрати посилання',
                    hint: 'Вибране посилання буде вставлено в цей рядок.',
                    onSelect: function (item) {
                        const label = row.querySelector('[data-card-link-label]');
                        const url = row.querySelector('[data-card-link-url]');
                        if (label && !String(label.value || '').trim()) {
                            label.value = item.cleanLabel || item.label || 'Посилання';
                        }
                        if (url) {
                            url.value = item.url || '';
                        }
                        setCardModalTemplate('');
                        updateCardModalPreview();
                    }
                });
                return;
            }

            const removeButton = event.target.closest('[data-card-link-remove]');
            if (removeButton) {
                const row = removeButton.closest('[data-card-link-row]');
                if (row) {
                    row.remove();
                    const empty = cardModalNode.querySelector('[data-card-link-empty]');
                    const list = cardLinkList();
                    if (empty && list) {
                        empty.hidden = Boolean(list.querySelector('[data-card-link-row]'));
                    }
                    setCardModalTemplate('');
                    updateCardModalPreview();
                }
            }
        });
        if (saveCardButton) {
            saveCardButton.addEventListener('click', function () {
                if (!cardModalState) {
                    return;
                }
                const card = readCardModalValues();
                if ((!card.form_id) && (card.title === '' || card.text === '')) {
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

    if (imagePickerNode) {
        const search = imagePickerNode.querySelector('[data-card-image-search]');
        if (search) {
            search.addEventListener('input', function () {
                window.clearTimeout(imagePickerSearchTimer);
                imagePickerSearchTimer = window.setTimeout(function () {
                    loadCardImageItems(false);
                }, 250);
            });
        }
        imagePickerNode.addEventListener('click', function (event) {
            const option = event.target.closest('[data-card-image-index]');
            if (!option) {
                return;
            }

            const item = imagePickerItems[Number(option.dataset.cardImageIndex)];
            if (!item) {
                return;
            }

            setCardImage(item.url);
            if (imagePickerModal) {
                imagePickerModal.hide();
            }
        });
        const imagePickerBody = imagePickerNode.querySelector('.modal-body');
        if (imagePickerBody) {
            imagePickerBody.addEventListener('scroll', maybeLoadMoreCardImages, {passive: true});
        }
        imagePickerNode.addEventListener('scroll', maybeLoadMoreCardImages, true);
    }

    function addSectionFromTemplate(template) {
        sections.push(presetSection(template || 'blank'));
        expandedSections.add(sections.length - 1);
        render();
    }

    if (sectionPickerNode) {
        sectionPickerNode.addEventListener('click', function (event) {
            const templateButton = event.target.closest('[data-layout-template]');
            if (!templateButton) {
                return;
            }

            addSectionFromTemplate(templateButton.dataset.layoutTemplate);
            if (sectionPickerModal) {
                sectionPickerModal.hide();
            }
        });
    }

    if (importExportNode) {
        importExportNode.addEventListener('click', function (event) {
            const button = event.target.closest('button');
            if (!button) {
                return;
            }
            if (button.matches('[data-layout-export-copy]')) {
                copyExportJson();
                return;
            }
            if (button.matches('[data-layout-example-copy]')) {
                copyExampleJson();
                return;
            }
            if (button.matches('[data-layout-example-use]')) {
                useExampleJson();
                return;
            }
            if (button.matches('[data-layout-import-apply]')) {
                applyImportedLayout();
                return;
            }
            if (button.matches('[data-layout-import-clear]')) {
                if (importField) {
                    importField.value = '';
                    importField.focus();
                }
                setImportStatus('', false);
            }
        });
    }

    builder.addEventListener('click', function (event) {
        const button = event.target.closest('button');
        if (!button) return;
        const i = indexes(button);
        if (button.matches('[data-layout-import-export-open]')) {
            openImportExportModal();
            return;
        }
        if (button.matches('[data-layout-open-section-picker]')) {
            if (sectionPickerModal) {
                sectionPickerModal.show();
            } else {
                addSectionFromTemplate('blank');
            }
            return;
        }
        if (button.matches('[data-layout-expand-all]')) {
            expandedSections = new Set(sections.map(function (_, index) { return index; }));
            render();
            return;
        }
        if (button.matches('[data-layout-collapse-all]')) {
            expandedSections = new Set();
            render();
            return;
        }
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
            addSectionFromTemplate(button.dataset.layoutTemplate);
            return;
        }
        if (button.matches('[data-layout-remove-section]')) {
            if (!window.confirm('Видалити секцію разом з усіма її рядами та картками?')) {
                return;
            }
            sections.splice(i.section, 1);
            expandedSections = new Set([Math.max(0, Math.min(i.section, sections.length - 1))]);
        }
        if (button.matches('[data-layout-add-row]')) {
            sections[i.section].rows.push(rowFromPreset('col-md-12'));
            expandedSections.add(i.section);
        }
        if (button.matches('[data-layout-remove-row]')) {
            if (!window.confirm('Видалити ряд разом з усіма картками в ньому?')) {
                return;
            }
            sections[i.section].rows.splice(i.row, 1);
        }
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
        if (dragState && dragState.kind === 'card') {
            return node.closest('[data-drag-kind="card"]') || node.closest('[data-card-drop-zone]');
        }
        if (dragState) {
            return node.closest('[data-drag-kind="' + dragState.kind + '"]');
        }

        return node.closest('[data-drag-kind]');
    }

    function canDrop(target) {
        if (!dragState) {
            return false;
        }
        const targetKind = target.dataset.dragKind || (target.hasAttribute('data-card-drop-zone') ? 'column' : '');
        const i = indexes(target);
        if (dragState.kind === 'section') {
            if (targetKind !== 'section') {
                return false;
            }
            return i.section !== dragState.section;
        }
        if (dragState.kind === 'row') {
            if (targetKind !== 'row') {
                return false;
            }
            return i.section === dragState.section && i.row !== dragState.row;
        }
        if (dragState.kind === 'card') {
            if (i.section !== dragState.section || i.row < 0 || i.column < 0) {
                return false;
            }
            if (targetKind === 'card') {
                return i.row !== dragState.row || i.column !== dragState.column || i.card !== dragState.card;
            }
            if (targetKind === 'column') {
                const targetCards = sections[i.section].rows[i.row].columns[i.column].cards || [];
                return i.row !== dragState.row || i.column !== dragState.column || dragState.card !== targetCards.length - 1;
            }
            return false;
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
            moveCardToTarget(target, i);
        }
    }

    function moveCardToTarget(target, targetIndexes) {
        const sourceCards = sections[dragState.section].rows[dragState.row].columns[dragState.column].cards;
        const targetCards = sections[targetIndexes.section].rows[targetIndexes.row].columns[targetIndexes.column].cards;
        const targetKind = target.dataset.dragKind || (target.hasAttribute('data-card-drop-zone') ? 'column' : '');
        if (sourceCards === targetCards && targetKind === 'card') {
            moveInArray(sourceCards, dragState.card, targetIndexes.card);
            return;
        }

        const moving = sourceCards.splice(dragState.card, 1)[0];
        if (!moving) {
            return;
        }
        if (targetKind === 'card') {
            targetCards.splice(Math.max(0, targetIndexes.card), 0, moving);
            return;
        }
        targetCards.push(moving);
    }

    const form = builder.closest('form');
    if (form) {
        form.addEventListener('submit', function () {
            if (window.TiptapEditor) {
                window.TiptapEditor.syncAll(document);
            }
            if (!modeInput || modeInput.value === 'advanced') {
                updateSimpleEditorFromLayout();
            }
            sync();
        }, true);
    }
    render();
});
