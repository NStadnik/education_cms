(function () {
    'use strict';

    let bundle = getBundle();
    let bundleLoading = false;
    const bundleFile = 'tiptap.bundle.20260709-7.js?v=20260709-13';
    const scriptUrl = document.currentScript ? document.currentScript.src : '';
    const assetsBase = scriptUrl ? scriptUrl.replace(/\/tiptap-editor\.js(?:\?.*)?$/, '') : '/assets';
    const editors = new WeakMap();
    const adminBody = document.body;
    const adminCsrfToken = adminBody ? (adminBody.dataset.adminCsrfToken || '') : '';
    const mediaState = {
        editor: null,
        edit: null,
        modal: null,
        items: [],
        selected: new Set(),
        selectedItems: new Map(),
        loading: false,
        searchTimer: null,
        bound: false,
        view: 'compact',
        offset: 0,
        limit: 10,
        total: 0,
        hasMore: false,
        token: 0
    };

    function init(root) {
        bundle = getBundle() || bundle || {};
        if (!bundle.Editor) {
            markPendingEditors(root || document, 'Завантаження Tiptap...');
            loadBundle(function () {
                init(root || document);
            });
            return;
        }

        (root || document).querySelectorAll('textarea[data-tiptap-editor]').forEach(function (textarea) {
            if (textarea.dataset.tiptapReady === '1') {
                return;
            }
            if (textarea.closest('.modal:not(.show)')) {
                return;
            }
            textarea.dataset.tiptapReady = '1';

            const shell = document.createElement('div');
            shell.className = 'tiptap-editor';
            const toolbar = document.createElement('div');
            toolbar.className = 'tiptap-editor-toolbar';
            const content = document.createElement('div');
            content.className = 'tiptap-editor-content rich-content';
            const context = document.createElement('div');
            context.className = 'tiptap-editor-context';
            context.hidden = true;
            const source = document.createElement('textarea');
            source.className = 'tiptap-editor-source';
            source.hidden = true;
            source.setAttribute('aria-label', 'HTML');
            const footer = document.createElement('div');
            footer.className = 'tiptap-editor-footer';
            footer.innerHTML = '<span class="tiptap-editor-hint"><span class="mdi mdi-lightbulb-outline" aria-hidden="true"></span> Виділіть текст або натисніть на медіа, щоб побачити доступні дії</span><span data-tiptap-count>0 слів · 0 символів</span>';
            shell.append(toolbar, context, content, source, footer);
            insertAfterField(textarea, shell);
            textarea._tiptapShell = shell;

            try {
                let editor;
                try {
                    editor = createEditor(textarea, content, toolbar, context, footer, fullExtensions(textarea));
                } catch (fullError) {
                    window.console && window.console.warn && window.console.warn('Full Tiptap setup failed, retrying with StarterKit only.', fullError);
                    editor = createEditor(textarea, content, toolbar, context, footer, [bundle.StarterKit].filter(Boolean));
                }
                editors.set(textarea, editor);
                toolbar.append.apply(toolbar, toolbarButtons(textarea, editor, content, source));
                updateEditorUi(textarea, toolbar, context, footer, editor);
                syncOne(textarea);
                textarea.hidden = true;
                clearStatus(textarea);
            } catch (error) {
                textarea.dataset.tiptapReady = '0';
                shell.remove();
                textarea.hidden = false;
                setStatus(textarea, 'Tiptap не запустився: ' + (error && error.message ? error.message : String(error)));
                window.console && window.console.error && window.console.error('Tiptap editor initialization failed.', error);
            }
        });
    }

    function getBundle() {
        if (window.TiptapBundle && window.TiptapBundle.Editor) {
            return window.TiptapBundle;
        }
        if (globalThis.TiptapBundle && globalThis.TiptapBundle.Editor) {
            return globalThis.TiptapBundle;
        }
        try {
            const globalBundle = (0, eval)('typeof TiptapBundle !== "undefined" ? TiptapBundle : null');
            if (globalBundle && globalBundle.Editor) {
                window.TiptapBundle = globalBundle;
                return globalBundle;
            }
        } catch (error) {
            return null;
        }
        return null;
    }

    function insertAfterField(textarea, node) {
        const label = textarea.closest('label');
        if (label && label.contains(textarea)) {
            label.insertAdjacentElement('afterend', node);
            return;
        }
        textarea.insertAdjacentElement('afterend', node);
    }

    function markPendingEditors(root, message) {
        (root || document).querySelectorAll('textarea[data-tiptap-editor]').forEach(function (textarea) {
            if (textarea.dataset.tiptapReady === '1') {
                return;
            }
            setStatus(textarea, message);
        });
    }

    function fullExtensions(textarea) {
        return [
            createRichMediaExtension(),
            createRichGalleryExtension(),
            createRichGalleryCaptionExtension(),
            bundle.StarterKit,
            bundle.Underline,
            bundle.Link && bundle.Link.configure({
                openOnClick: false,
                autolink: true,
                HTMLAttributes: {rel: 'noopener', target: null}
            }),
            bundle.Image && bundle.Image.configure({inline: false, allowBase64: false}),
            bundle.TextAlign && bundle.TextAlign.configure({types: ['heading', 'paragraph']}),
            bundle.Placeholder && bundle.Placeholder.configure({placeholder: textarea.getAttribute('placeholder') || ''})
        ].filter(Boolean);
    }

    function createRichMediaExtension() {
        if (!bundle.Node) {
            return null;
        }
        return bundle.Node.create({
            name: 'richMedia',
            group: 'block',
            atom: true,
            selectable: true,
            draggable: true,
            addAttributes: function () {
                return {
                    src: {default: ''},
                    alt: {default: ''},
                    href: {default: ''},
                    caption: {default: ''},
                    align: {default: 'center'}
                };
            },
            parseHTML: function () {
                return [{
                    tag: 'figure.rich-media-block',
                    getAttrs: function (element) {
                        const image = element.querySelector('img');
                        const link = image ? image.closest('a') : null;
                        const caption = element.querySelector('figcaption');
                        const className = element.getAttribute('class') || '';
                        const alignMatch = className.match(/media-align-(left|center|right|wide)/);
                        return {
                            src: image ? (image.getAttribute('src') || '') : '',
                            alt: image ? (image.getAttribute('alt') || '') : '',
                            href: link ? (link.getAttribute('href') || '') : '',
                            caption: caption ? caption.textContent : '',
                            align: alignMatch ? alignMatch[1] : 'center'
                        };
                    }
                }];
            },
            renderHTML: function (props) {
                const attrs = props.node.attrs || {};
                const image = ['img', {src: attrs.src || '', alt: attrs.alt || ''}];
                const media = attrs.href ? ['a', {href: attrs.href}, image] : image;
                const children = [media];
                if (attrs.caption) {
                    children.push(['figcaption', String(attrs.caption)]);
                }
                return ['figure', {class: 'rich-media-block media-align-' + normalizeAlign(attrs.align)}].concat(children);
            }
        });
    }

    function createRichGalleryExtension() {
        if (!bundle.Node) {
            return null;
        }
        return bundle.Node.create({
            name: 'richGallery',
            group: 'block',
            atom: true,
            selectable: true,
            draggable: true,
            addAttributes: function () {
                return {
                    columns: {default: '3'},
                    align: {default: 'center'},
                    images: {default: []}
                };
            },
            parseHTML: function () {
                return [{
                    tag: 'div.rich-gallery',
                    getAttrs: function (element) {
                        const className = element.getAttribute('class') || '';
                        const columnsMatch = className.match(/rich-gallery-cols-(2|3|4)/);
                        const alignMatch = className.match(/media-align-(left|center|right|wide)/);
                        const images = Array.prototype.slice.call(element.querySelectorAll('img')).map(function (image) {
                            const link = image.closest('a');
                            return {
                                src: image.getAttribute('src') || '',
                                alt: image.getAttribute('alt') || '',
                                href: link && element.contains(link) ? (link.getAttribute('href') || '') : ''
                            };
                        }).filter(function (image) {
                            return image.src;
                        });
                        return {
                            columns: columnsMatch ? columnsMatch[1] : '3',
                            align: alignMatch ? alignMatch[1] : 'center',
                            images: images
                        };
                    }
                }];
            },
            renderHTML: function (props) {
                const attrs = props.node.attrs || {};
                const columns = normalizeColumns(attrs.columns);
                const align = normalizeAlign(attrs.align);
                const figures = (Array.isArray(attrs.images) ? attrs.images : []).filter(function (image) {
                    return image && image.src;
                }).map(function (image) {
                    const imageNode = ['img', {src: image.src, alt: image.alt || ''}];
                    return ['figure', ['a', {href: image.href || image.src}, imageNode]];
                });
                return ['div', {class: 'rich-gallery rich-gallery-cols-' + columns + ' media-align-' + align}].concat(figures);
            }
        });
    }

    function createRichGalleryCaptionExtension() {
        if (!bundle.Node) {
            return null;
        }
        return bundle.Node.create({
            name: 'richGalleryCaption',
            group: 'block',
            content: 'text*',
            marks: '',
            addAttributes: function () {
                return {
                    align: {default: 'center'}
                };
            },
            parseHTML: function () {
                return [{
                    tag: 'p.rich-gallery-caption',
                    getAttrs: function (element) {
                        const className = element.getAttribute('class') || '';
                        const alignMatch = className.match(/media-align-(left|center|right|wide)/);
                        return {align: alignMatch ? alignMatch[1] : 'center'};
                    }
                }];
            },
            renderHTML: function (props) {
                const align = normalizeAlign(props.node.attrs && props.node.attrs.align);
                return ['p', {class: 'rich-gallery-caption media-align-' + align}, 0];
            }
        });
    }

    function createEditor(textarea, content, toolbar, context, footer, extensions) {
        let editor;
        editor = new bundle.Editor({
            element: content,
            content: textarea.value || '',
            editable: !textarea.disabled,
            extensions: extensions,
            editorProps: {
                handleDOMEvents: {
                    click: preventEditorMediaLinkOpen
                }
            },
            onUpdate: function () {
                syncOne(textarea);
                textarea.dispatchEvent(new Event('input', {bubbles: true}));
                updateEditorUi(textarea, toolbar, context, footer, editor);
            },
            onSelectionUpdate: function () {
                updateEditorUi(textarea, toolbar, context, footer, editor);
            }
        });
        return editor;
    }

    function preventEditorMediaLinkOpen(view, event) {
        const target = event.target && event.target.nodeType === 1 ? event.target : null;
        const link = target && target.closest ? target.closest('.rich-gallery a, .rich-media-block a') : null;
        if (!link || !view.dom.contains(link)) {
            return false;
        }
        event.preventDefault();
        return false;
    }

    function setStatus(textarea, message) {
        clearStatus(textarea);
        const node = document.createElement('div');
        node.className = message.indexOf('...') === message.length - 3 ? 'tiptap-editor-status' : 'tiptap-editor-status is-error';
        node.textContent = message;
        insertAfterField(textarea, node);
    }

    function clearStatus(textarea) {
        const label = textarea.closest('label');
        const candidates = [
            textarea.nextElementSibling,
            label ? label.nextElementSibling : null
        ];
        candidates.forEach(function (node) {
            if (node && node.classList.contains('tiptap-editor-status')) {
                node.remove();
            }
        });
    }

    function setBundleError(message) {
        document.querySelectorAll('textarea[data-tiptap-editor]').forEach(function (textarea) {
            if (textarea.dataset.tiptapReady !== '1') {
                setStatus(textarea, message);
            }
        });
    }

    function clearBundleStatus(root) {
        (root || document).querySelectorAll('textarea[data-tiptap-editor]').forEach(function (textarea) {
            if (textarea.dataset.tiptapReady !== '1') {
                clearStatus(textarea);
            }
        });
    }

    function loadBundle(callback) {
        const readyBundle = getBundle();
        if (readyBundle && readyBundle.Editor) {
            bundle = readyBundle;
            callback();
            return;
        }
        if (bundleLoading) {
            document.addEventListener('tiptap-bundle-ready', callback, {once: true});
            return;
        }

        bundleLoading = true;
        document.addEventListener('tiptap-bundle-ready', callback, {once: true});

        const script = document.createElement('script');
        script.src = assetsBase + '/' + bundleFile;
        script.async = false;
        script.onload = function () {
            bundle = getBundle() || {};
            bundleLoading = false;
            if (bundle.Editor) {
                clearBundleStatus(document);
                document.dispatchEvent(new Event('tiptap-bundle-ready'));
            } else {
                setBundleError('Tiptap bundle завантажився, але window.TiptapBundle.Editor недоступний. URL: ' + script.src);
            }
        };
        script.onerror = function () {
            bundleLoading = false;
            setBundleError('Не вдалося завантажити Tiptap bundle: ' + script.src);
            window.console && window.console.error && window.console.error('Tiptap bundle failed to load: ' + script.src);
        };
        document.head.appendChild(script);
    }

    function toolbarButtons(textarea, editor, content, source) {
        const block = document.createElement('select');
        block.title = 'Формат';
        block.setAttribute('aria-label', 'Формат тексту');
        [
            ['paragraph', 'Абзац'],
            ['h2', 'Заголовок 2'],
            ['h3', 'Заголовок 3'],
            ['blockquote', 'Цитата']
        ].forEach(function (item) {
            const option = document.createElement('option');
            option.value = item[0];
            option.textContent = item[1];
            block.appendChild(option);
        });
        block.addEventListener('change', function () {
            if (block.value === 'h2') {
                editor.chain().focus().toggleHeading({level: 2}).run();
            } else if (block.value === 'h3') {
                editor.chain().focus().toggleHeading({level: 3}).run();
            } else if (block.value === 'blockquote') {
                editor.chain().focus().toggleBlockquote().run();
            } else {
                editor.chain().focus().setParagraph().run();
            }
        });

        return [
            toolbarGroup('Історія', [
                button('mdi-undo', 'Скасувати (Ctrl+Z)', function () { editor.chain().focus().undo().run(); }, '', 'Скасувати'),
                button('mdi-redo', 'Повторити (Ctrl+Shift+Z)', function () { editor.chain().focus().redo().run(); }, '', 'Повторити')
            ]),
            toolbarGroup('Стиль тексту', [
                block,
                button('mdi-format-bold', 'Жирний (Ctrl+B)', function () { editor.chain().focus().toggleBold().run(); }, 'bold'),
                button('mdi-format-italic', 'Курсив (Ctrl+I)', function () { editor.chain().focus().toggleItalic().run(); }, 'italic'),
                button('mdi-format-underline', 'Підкреслення (Ctrl+U)', function () { editor.chain().focus().toggleUnderline().run(); }, 'underline'),
                button('mdi-format-clear', 'Очистити форматування виділеного тексту', function () { editor.chain().focus().unsetAllMarks().clearNodes().run(); })
            ]),
            toolbarGroup('Структура', [
                button('mdi-format-list-bulleted', 'Маркований список', function () { editor.chain().focus().toggleBulletList().run(); }, 'bulletList'),
                button('mdi-format-list-numbered', 'Нумерований список', function () { editor.chain().focus().toggleOrderedList().run(); }, 'orderedList')
            ]),
            toolbarGroup('Вирівнювання', [
                button('mdi-format-align-left', 'Ліворуч', function () { editor.chain().focus().setTextAlign('left').run(); }, 'align-left'),
                button('mdi-format-align-center', 'По центру', function () { editor.chain().focus().setTextAlign('center').run(); }, 'align-center'),
                button('mdi-format-align-right', 'Праворуч', function () { editor.chain().focus().setTextAlign('right').run(); }, 'align-right'),
                button('mdi-format-align-justify', 'По ширині', function () { editor.chain().focus().setTextAlign('justify').run(); }, 'align-justify')
            ]),
            toolbarGroup('Вставка', [
                button('mdi-link-variant', 'Додати або змінити покликання', function () { showLinkEditor(textarea, editor); }, 'link'),
                button('mdi-link-off', 'Прибрати покликання', function () { editor.chain().focus().unsetLink().run(); }),
                button('mdi-image-multiple-outline', 'Додати фото, файл або галерею', function () { openMediaPicker(textarea); }, '', 'Медіа', 'is-primary')
            ]),
            toolbarGroup('Код', [
                button('mdi-code-tags', 'Редагувати HTML', function (event) { toggleHtmlMode(textarea, editor, content, source, event.currentTarget); }, 'source')
            ])
        ];
    }

    function toolbarGroup(label, children) {
        const group = document.createElement('div');
        group.className = 'tiptap-toolbar-group';
        group.setAttribute('role', 'group');
        group.setAttribute('aria-label', label);
        children.forEach(function (child) { group.appendChild(child); });
        return group;
    }

    function button(icon, title, action, activeName, textLabel, extraClass) {
        const node = document.createElement('button');
        node.type = 'button';
        node.title = title;
        node.setAttribute('aria-label', title);
        node.dataset.tiptapActive = activeName || '';
        node.className = extraClass || '';
        node.innerHTML = '<span class="mdi ' + icon + '" aria-hidden="true"></span>' + (textLabel ? '<span>' + escapeHtml(textLabel) + '</span>' : '');
        node.addEventListener('click', action);
        return node;
    }

    function updateEditorUi(textarea, toolbar, context, footer, editor) {
        updateToolbar(toolbar, editor);
        updateMediaContext(textarea, context, editor);
        const count = footer ? footer.querySelector('[data-tiptap-count]') : null;
        if (count) {
            const text = editor.getText().trim();
            const words = text ? text.split(/\s+/u).filter(Boolean).length : 0;
            count.textContent = words + ' ' + pluralize(words, ['слово', 'слова', 'слів']) + ' · ' + text.length + ' символів';
        }
    }

    function pluralize(number, forms) {
        const value = Math.abs(number) % 100;
        const last = value % 10;
        if (value > 10 && value < 20) { return forms[2]; }
        if (last === 1) { return forms[0]; }
        if (last > 1 && last < 5) { return forms[1]; }
        return forms[2];
    }

    function updateToolbar(toolbar, editor) {
        toolbar.querySelectorAll('[data-tiptap-active]').forEach(function (node) {
            const name = node.dataset.tiptapActive;
            let active = false;
            if (name === 'align-left') {
                active = editor.isActive({textAlign: 'left'});
            } else if (name === 'align-center') {
                active = editor.isActive({textAlign: 'center'});
            } else if (name === 'align-right') {
                active = editor.isActive({textAlign: 'right'});
            } else if (name === 'align-justify') {
                active = editor.isActive({textAlign: 'justify'});
            } else if (name === 'source') {
                active = node.getAttribute('aria-pressed') === 'true';
            } else if (name) {
                active = editor.isActive(name);
            }
            node.classList.toggle('is-active', active);
        });

        const block = toolbar.querySelector('select');
        if (block) {
            block.value = editor.isActive('heading', {level: 2}) ? 'h2'
                : editor.isActive('heading', {level: 3}) ? 'h3'
                : editor.isActive('blockquote') ? 'blockquote'
                : 'paragraph';
        }
    }

    function selectedMediaNode(editor) {
        const selection = editor && editor.state ? editor.state.selection : null;
        const node = selection && selection.node ? selection.node : null;
        if (!node || ['richMedia', 'richGallery', 'image'].indexOf(node.type.name) === -1) {
            return null;
        }
        const result = {node: node, pos: selection.from, type: node.type.name, caption: '', nodeSize: node.nodeSize};
        if (node.type.name === 'richGallery') {
            const next = editor.state.doc.nodeAt(selection.from + node.nodeSize);
            if (next && next.type.name === 'richGalleryCaption') {
                result.caption = next.textContent || '';
                result.nodeSize += next.nodeSize;
            }
        }
        return result;
    }

    function updateMediaContext(textarea, context, editor) {
        if (!context) {
            return;
        }
        const selected = selectedMediaNode(editor);
        context.innerHTML = '';
        context.hidden = !selected;
        if (!selected) {
            return;
        }

        const attrs = selected.node.attrs || {};
        const title = document.createElement('div');
        title.className = 'tiptap-context-title';
        title.innerHTML = '<span class="mdi ' + (selected.type === 'richGallery' ? 'mdi-image-multiple-outline' : 'mdi-image-outline') + '" aria-hidden="true"></span><span><strong>' + (selected.type === 'richGallery' ? 'Галерея' : 'Зображення') + '</strong><small>Зміни застосовуються до вибраного блоку</small></span>';
        context.appendChild(title);

        const alignGroup = document.createElement('div');
        alignGroup.className = 'tiptap-context-actions';
        alignGroup.setAttribute('aria-label', 'Розташування медіа');
        [['left', 'mdi-align-horizontal-left', 'Ліворуч'], ['center', 'mdi-align-horizontal-center', 'По центру'], ['right', 'mdi-align-horizontal-right', 'Праворуч'], ['wide', 'mdi-arrow-expand-horizontal', 'На всю ширину']].forEach(function (item) {
            const control = contextButton(item[1], item[2], function () {
                if (selected.type === 'image') {
                    return;
                }
                updateSelectedMediaAttributes(editor, selected, {align: item[0]});
            });
            control.classList.toggle('is-active', normalizeAlign(attrs.align) === item[0]);
            control.disabled = selected.type === 'image';
            alignGroup.appendChild(control);
        });
        context.appendChild(alignGroup);

        if (selected.type === 'richGallery') {
            const columnsGroup = document.createElement('div');
            columnsGroup.className = 'tiptap-context-actions tiptap-context-columns';
            const label = document.createElement('span');
            label.textContent = 'Колонки:';
            columnsGroup.appendChild(label);
            ['2', '3', '4'].forEach(function (columns) {
                const control = contextTextButton(columns, columns + ' колонки', function () {
                    updateSelectedMediaAttributes(editor, selected, {columns: columns});
                });
                control.classList.toggle('is-active', normalizeColumns(attrs.columns) === columns);
                columnsGroup.appendChild(control);
            });
            context.appendChild(columnsGroup);
        }

        const mainActions = document.createElement('div');
        mainActions.className = 'tiptap-context-actions tiptap-context-main-actions';
        mainActions.appendChild(contextTextButton('Змінити', 'Змінити фото, порядок або підпис', function () {
            openMediaPicker(textarea, selected);
        }, 'mdi-pencil-outline'));
        mainActions.appendChild(contextButton('mdi-delete-outline', 'Видалити блок', function () {
            editor.chain().focus().deleteRange({from: selected.pos, to: selected.pos + selected.nodeSize}).run();
        }, 'is-danger'));
        context.appendChild(mainActions);
    }

    function updateSelectedMediaAttributes(editor, selected, attributes) {
        const transaction = editor.state.tr;
        transaction.setNodeMarkup(selected.pos, null, Object.assign({}, selected.node.attrs, attributes));
        if (selected.type === 'richGallery' && Object.prototype.hasOwnProperty.call(attributes, 'align')) {
            const captionPos = selected.pos + selected.node.nodeSize;
            const caption = editor.state.doc.nodeAt(captionPos);
            if (caption && caption.type.name === 'richGalleryCaption') {
                transaction.setNodeMarkup(captionPos, null, Object.assign({}, caption.attrs, {align: attributes.align}));
            }
        }
        editor.view.dispatch(transaction);
        editor.commands.focus();
    }

    function contextButton(icon, title, action, extraClass) {
        const control = document.createElement('button');
        control.type = 'button';
        control.className = extraClass || '';
        control.title = title;
        control.setAttribute('aria-label', title);
        control.innerHTML = '<span class="mdi ' + icon + '" aria-hidden="true"></span>';
        control.addEventListener('click', action);
        return control;
    }

    function contextTextButton(text, title, action, icon) {
        const control = contextButton(icon || '', title, action);
        control.classList.add('has-label');
        control.innerHTML = (icon ? '<span class="mdi ' + icon + '" aria-hidden="true"></span>' : '') + '<span>' + escapeHtml(text) + '</span>';
        return control;
    }

    function showLinkEditor(textarea, editor) {
        const shell = textarea && textarea._tiptapShell;
        const context = shell ? shell.querySelector('.tiptap-editor-context') : null;
        if (!context) { return; }
        const selection = {from: editor.state.selection.from, to: editor.state.selection.to};
        const previous = editor.getAttributes('link').href || '';
        context.hidden = false;
        context.innerHTML = '<div class="tiptap-context-title"><span class="mdi mdi-link-variant" aria-hidden="true"></span><span><strong>Покликання</strong><small>Вставте адресу або оберіть матеріал сайту</small></span></div><div class="tiptap-link-editor"><input type="url" data-tiptap-link-url placeholder="https://… або /storinka" aria-label="Адреса покликання"><button type="button" class="has-label" data-tiptap-link-library><span class="mdi mdi-folder-search-outline" aria-hidden="true"></span><span>Обрати зі сайту</span></button><button type="button" class="has-label is-primary" data-tiptap-link-apply><span class="mdi mdi-check" aria-hidden="true"></span><span>Застосувати</span></button><button type="button" data-tiptap-link-cancel aria-label="Скасувати"><span class="mdi mdi-close" aria-hidden="true"></span></button></div>';
        const input = context.querySelector('[data-tiptap-link-url]');
        input.value = previous;

        function applyLink(href) {
            const chain = editor.chain().focus().setTextSelection(selection).extendMarkRange('link');
            if (String(href || '').trim()) {
                chain.setLink({href: String(href).trim()}).run();
            } else {
                chain.unsetLink().run();
            }
        }
        context.querySelector('[data-tiptap-link-apply]').addEventListener('click', function () { applyLink(input.value); });
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') { event.preventDefault(); applyLink(input.value); }
            if (event.key === 'Escape') { event.preventDefault(); editor.commands.focus(); }
        });
        context.querySelector('[data-tiptap-link-cancel]').addEventListener('click', function () { editor.commands.focus(); });
        context.querySelector('[data-tiptap-link-library]').addEventListener('click', function () {
            if (!window.AdminLinkPicker || !window.AdminLinkPicker.open) { input.focus(); return; }
            window.AdminLinkPicker.open({
                type: 'pages',
                title: 'Обрати покликання',
                hint: 'Оберіть сторінку, категорію, новину або медіафайл.',
                onSelect: function (item) {
                    if (item && item.url) { applyLink(item.url); }
                }
            });
        });
        window.setTimeout(function () { input.focus(); input.select(); }, 0);
    }

    function toggleHtmlMode(textarea, editor, content, source, buttonNode) {
        const shell = buttonNode.closest('.tiptap-editor');
        const context = shell ? shell.querySelector('.tiptap-editor-context') : null;
        const footer = shell ? shell.querySelector('.tiptap-editor-footer') : null;
        const sourceVisible = !source.hidden;
        if (sourceVisible) {
            setContent(textarea, source.value);
            source.hidden = true;
            content.hidden = false;
            buttonNode.setAttribute('aria-pressed', 'false');
            editor.commands.focus();
            updateEditorUi(textarea, buttonNode.closest('.tiptap-editor-toolbar'), context, footer, editor);
            return;
        }

        syncOne(textarea);
        source.value = formatHtml(textarea.value || '');
        content.hidden = true;
        source.hidden = false;
        buttonNode.setAttribute('aria-pressed', 'true');
        if (context) { context.hidden = true; }
        source.focus();
        updateToolbar(buttonNode.closest('.tiptap-editor-toolbar'), editor);
    }

    function formatHtml(html) {
        return String(html || '')
            .replace(/></g, '>\n<')
            .replace(/(<\/(?:p|h[1-6]|ul|ol|li|blockquote|figure|figcaption|div)>)/gi, '$1\n')
            .replace(/\n{3,}/g, '\n\n')
            .trim();
    }

    function syncAll(root) {
        (root || document).querySelectorAll('textarea[data-tiptap-editor]').forEach(syncOne);
    }

    function syncOne(textarea) {
        const editor = textarea ? editors.get(textarea) : null;
        if (editor) {
            const shell = textarea._tiptapShell;
            const source = shell ? shell.querySelector('.tiptap-editor-source') : null;
            textarea.value = source && !source.hidden ? source.value : editor.getHTML();
        }
    }

    function setContent(textarea, html) {
        if (!textarea) {
            return;
        }
        textarea.value = html || '';
        const editor = editors.get(textarea);
        if (editor) {
            editor.commands.setContent(html || '', false);
            const shell = textarea._tiptapShell;
            const source = shell ? shell.querySelector('.tiptap-editor-source') : null;
            if (source && !source.hidden) {
                source.value = formatHtml(html || '');
            }
        }
    }

    function insertContent(textarea, html) {
        if (!textarea) {
            return;
        }
        const editor = editors.get(textarea);
        if (editor) {
            editor.chain().focus().insertContent(html || '').run();
            syncOne(textarea);
        } else {
            const separator = String(textarea.value || '').trim() ? '\n\n' : '';
            textarea.value = String(textarea.value || '') + separator + (html || '');
        }
        textarea.dispatchEvent(new Event('input', {bubbles: true}));
    }

    function openMediaPicker(textarea, editSelection) {
        const modalNode = document.getElementById('richMediaModal');
        if (!modalNode || !window.bootstrap) {
            return;
        }
        bindMediaPicker(modalNode);
        mediaState.editor = textarea;
        mediaState.edit = editSelection ? {pos: editSelection.pos, type: editSelection.type, nodeSize: editSelection.nodeSize || editSelection.node.nodeSize} : null;
        const editItems = editSelection ? mediaItemsFromNode(editSelection.node) : [];
        mediaState.selected = new Set(editItems.map(function (item) { return item.path; }));
        mediaState.selectedItems = new Map(editItems.map(function (item) { return [item.path, item]; }));
        modalNode.querySelector('[data-rich-media-caption]').value = editSelection ? String(editSelection.caption || editSelection.node.attrs.caption || '') : '';
        modalNode.querySelector('[data-rich-media-mode]').value = editSelection && editSelection.type === 'richGallery' ? 'gallery' : 'single';
        modalNode.querySelector('[data-rich-media-align]').value = editSelection ? normalizeAlign(editSelection.node.attrs.align) : 'center';
        modalNode.querySelector('[data-rich-media-columns]').value = editSelection && editSelection.type === 'richGallery' ? normalizeColumns(editSelection.node.attrs.columns) : '3';
        modalNode.querySelector('[data-rich-media-search]').value = '';
        updateMediaOptionsState();
        const title = modalNode.querySelector('#richMediaTitle');
        const insertLabel = modalNode.querySelector('[data-rich-media-insert] span:last-child');
        if (title) { title.textContent = editSelection ? 'Редагувати медіаблок' : 'Додати медіа'; }
        if (insertLabel) { insertLabel.textContent = editSelection ? 'Застосувати зміни' : 'Вставити'; }
        mediaState.offset = 0;
        mediaState.total = 0;
        mediaState.hasMore = false;
        setRichMediaView(localStorage.getItem('richMediaViewMode') || 'compact', false);

        mediaState.modal = window.bootstrap.Modal.getOrCreateInstance(modalNode);
        mediaState.modal.show();
        const modalBody = modalNode.querySelector('.modal-body');
        if (modalBody) {
            modalBody.scrollTop = 0;
        }
        loadMediaItems(false);
    }

    function mediaItemsFromNode(node) {
        const attrs = node.attrs || {};
        const images = node.type.name === 'richGallery' ? (Array.isArray(attrs.images) ? attrs.images : []) : [{src: attrs.src || '', alt: attrs.alt || '', href: attrs.href || ''}];
        return images.filter(function (image) { return image && image.src; }).map(function (image) {
            const path = mediaPathFromUrl(image.src);
            const name = path.split('/').pop() || 'Зображення';
            return {path: path, name: name, url: image.src, thumb_url: image.src, alt_text: image.alt || '', title: '', caption: '', size_label: '', type: 'image', is_image: true};
        });
    }

    function mediaPathFromUrl(value) {
        try {
            const path = new URL(value, window.location.origin).pathname;
            return decodeURIComponent(path.replace(/^.*\/uploads\//, ''));
        } catch (error) {
            return String(value || '').replace(/^.*\/uploads\//, '');
        }
    }

    function bindMediaPicker(modalNode) {
        if (mediaState.bound) {
            return;
        }
        mediaState.bound = true;

        modalNode.querySelector('[data-rich-media-search]').addEventListener('input', function () {
            window.clearTimeout(mediaState.searchTimer);
            mediaState.searchTimer = window.setTimeout(function () { loadMediaItems(false); }, 250);
        });
        modalNode.querySelector('[data-rich-media-mode]').addEventListener('change', function () {
            if (this.value === 'single' && mediaState.selected.size > 1) {
                const first = Array.from(mediaState.selected)[0];
                mediaState.selected = new Set(first ? [first] : []);
                mediaState.selectedItems = new Map(first && mediaState.selectedItems.has(first) ? [[first, mediaState.selectedItems.get(first)]] : []);
            }
            renderMediaItems();
            updateMediaOptionsState();
        });
        modalNode.querySelector('[data-rich-media-align]').addEventListener('change', updateSelectionPreview);
        modalNode.querySelector('[data-rich-media-columns]').addEventListener('change', updateSelectionPreview);
        modalNode.querySelector('[data-rich-media-caption]').addEventListener('input', updateSelectionPreview);
        modalNode.querySelector('[data-rich-media-clear]').addEventListener('click', function () {
            mediaState.selected = new Set();
            mediaState.selectedItems = new Map();
            updateMediaCardSelection();
            updateSelectionPreview();
        });
        modalNode.querySelectorAll('[data-rich-media-view]').forEach(function (viewButton) {
            viewButton.addEventListener('click', function () {
                setRichMediaView(viewButton.dataset.richMediaView || 'compact', true);
            });
        });
        modalNode.querySelector('[data-rich-media-insert]').addEventListener('click', insertSelectedMedia);
        modalNode.querySelector('[data-rich-media-upload]').addEventListener('change', uploadMediaFile);
        const modalBody = modalNode.querySelector('.modal-body');
        if (modalBody) {
            modalBody.addEventListener('scroll', maybeLoadMoreMediaItems, {passive: true});
        }
        updateMediaOptionsState();
    }

    function updateMediaOptionsState() {
        const modalNode = document.getElementById('richMediaModal');
        if (!modalNode) { return; }
        const gallery = modalNode.querySelector('[data-rich-media-mode]').value === 'gallery';
        const columns = modalNode.querySelector('[data-rich-media-columns]');
        if (columns) {
            columns.disabled = !gallery;
            const label = columns.closest('label');
            if (label) { label.classList.toggle('is-muted', !gallery); }
        }
    }

    async function loadMediaItems(append) {
        if (mediaState.loading && append) {
            return;
        }
        const modalNode = document.getElementById('richMediaModal');
        const grid = modalNode.querySelector('[data-rich-media-grid]');
        const status = modalNode.querySelector('[data-rich-media-status]');
        const search = modalNode.querySelector('[data-rich-media-search]').value.trim();
        const url = new URL(adminBody ? (adminBody.dataset.richMediaPickerUrl || '/admin/media/picker') : '/admin/media/picker', window.location.origin);
        const token = append ? mediaState.token : ++mediaState.token;
        url.searchParams.set('limit', String(mediaState.limit));
        url.searchParams.set('offset', append ? String(mediaState.offset) : '0');
        if (search !== '') {
            url.searchParams.set('q', search);
        }

        mediaState.loading = true;
        status.textContent = append ? 'Підвантаження...' : 'Завантаження...';
        if (!append) {
            mediaState.items = [];
            mediaState.offset = 0;
            mediaState.total = 0;
            mediaState.hasMore = false;
            grid.innerHTML = '';
            updateSelectionPreview();
        }

        try {
            const response = await fetch(url.toString(), {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Не вдалося завантажити медіафайли.');
            }
            if (token !== mediaState.token) {
                return;
            }
            const items = data.items || [];
            const existingPaths = new Set(mediaState.items.map(function (item) { return item.path; }));
            const newItems = append ? items.filter(function (item) {
                return item && item.path && !existingPaths.has(item.path);
            }) : items;
            mediaState.items = append ? mediaState.items.concat(newItems) : newItems;
            mediaState.offset = Number(data.next_offset || 0);
            mediaState.total = Number(data.total || 0);
            mediaState.hasMore = Boolean(data.has_more);
            mediaState.items.forEach(function (item) {
                if (mediaState.selected.has(item.path)) {
                    mediaState.selectedItems.set(item.path, item);
                }
            });
            renderMediaItems(append ? newItems : null);
            status.textContent = mediaState.items.length
                ? (mediaState.hasMore ? 'Показано файлів: ' + mediaState.items.length + ' з ' + mediaState.total + '.' : 'Знайдено файлів: ' + mediaState.items.length + '.')
                : 'Файлів не знайдено.';
        } catch (error) {
            if (token === mediaState.token) {
                status.textContent = error.message || 'Помилка завантаження.';
            }
        } finally {
            if (token === mediaState.token) {
                mediaState.loading = false;
                window.setTimeout(maybeLoadMoreMediaItems, 80);
            }
        }
    }

    function maybeLoadMoreMediaItems() {
        if (!mediaState.hasMore || mediaState.loading) {
            return;
        }
        const modalNode = document.getElementById('richMediaModal');
        const modalBody = modalNode ? modalNode.querySelector('.modal-body') : null;
        if (modalBody && modalBody.scrollTop + modalBody.clientHeight >= modalBody.scrollHeight - 160) {
            loadMediaItems(true);
        }
    }

    function renderMediaItems(appendedItems) {
        const modalNode = document.getElementById('richMediaModal');
        const grid = modalNode.querySelector('[data-rich-media-grid]');
        const append = Array.isArray(appendedItems);
        if (!append) {
            grid.innerHTML = '';
        }
        grid.classList.toggle('is-large', mediaState.view === 'large');
        (append ? appendedItems : mediaState.items).forEach(function (item) {
            const selectedIndex = Array.from(mediaState.selected).indexOf(item.path);
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'rich-media-card' + (mediaState.selected.has(item.path) ? ' is-selected' : '');
            card.dataset.richMediaCardPath = item.path;
            card.setAttribute('aria-pressed', mediaState.selected.has(item.path) ? 'true' : 'false');
            card.innerHTML = (item.is_image ? '<img src="' + escapeHtml(item.thumb_url || item.url) + '" alt="">' : '<span class="mdi mdi-file-outline rich-media-file-icon" aria-hidden="true"></span>') +
                (selectedIndex >= 0 ? '<span class="rich-media-card-check">' + (selectedIndex + 1) + '</span>' : '') +
                '<span class="rich-media-card-name">' + escapeHtml(item.name) + '</span>' +
                '<small>' + escapeHtml(item.type) + ' · ' + escapeHtml(item.size_label) + '</small>';
            card.addEventListener('click', function () {
                const mode = modalNode.querySelector('[data-rich-media-mode]').value;
                if (mode === 'single') {
                    mediaState.selected = new Set([item.path]);
                    mediaState.selectedItems = new Map([[item.path, item]]);
                } else {
                    if (!item.is_image) {
                        return;
                    }
                    if (mediaState.selected.has(item.path)) {
                        mediaState.selected.delete(item.path);
                        mediaState.selectedItems.delete(item.path);
                    } else {
                        mediaState.selected.add(item.path);
                        mediaState.selectedItems.set(item.path, item);
                    }
                }
                updateMediaCardSelection();
                updateSelectionPreview();
            });
            grid.appendChild(card);
        });
        updateSelectionPreview();
    }

    function updateMediaCardSelection() {
        const modalNode = document.getElementById('richMediaModal');
        if (!modalNode) { return; }
        const selectedPaths = Array.from(mediaState.selected);
        modalNode.querySelectorAll('[data-rich-media-card-path]').forEach(function (card) {
            const path = card.dataset.richMediaCardPath || '';
            const selectedIndex = selectedPaths.indexOf(path);
            const selected = selectedIndex >= 0;
            card.classList.toggle('is-selected', selected);
            card.setAttribute('aria-pressed', selected ? 'true' : 'false');
            const currentCheck = card.querySelector('.rich-media-card-check');
            if (selected) {
                const check = currentCheck || document.createElement('span');
                check.className = 'rich-media-card-check';
                check.textContent = String(selectedIndex + 1);
                if (!currentCheck) {
                    const image = card.querySelector('img, .rich-media-file-icon');
                    if (image) { image.insertAdjacentElement('afterend', check); } else { card.prepend(check); }
                }
            } else if (currentCheck) {
                currentCheck.remove();
            }
        });
    }

    function setRichMediaView(mode, persist) {
        const modalNode = document.getElementById('richMediaModal');
        const normalized = mode === 'large' ? 'large' : 'compact';
        mediaState.view = normalized;
        if (modalNode) {
            const grid = modalNode.querySelector('[data-rich-media-grid]');
            if (grid) {
                grid.classList.toggle('is-large', normalized === 'large');
            }
            modalNode.querySelectorAll('[data-rich-media-view]').forEach(function (viewButton) {
                const active = viewButton.dataset.richMediaView === normalized;
                viewButton.classList.toggle('secondary', !active);
                viewButton.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        }
        if (persist) {
            localStorage.setItem('richMediaViewMode', normalized);
        }
    }

    function selectedMediaItems() {
        return Array.from(mediaState.selected).map(function (path) {
            return mediaState.selectedItems.get(path);
        }).filter(Boolean);
    }

    function updateSelectionPreview() {
        const modalNode = document.getElementById('richMediaModal');
        if (!modalNode) {
            return;
        }
        const selected = selectedMediaItems();
        const mode = modalNode.querySelector('[data-rich-media-mode]').value;
        const align = modalNode.querySelector('[data-rich-media-align]').value;
        const columns = modalNode.querySelector('[data-rich-media-columns]').value;
        const caption = modalNode.querySelector('[data-rich-media-caption]').value.trim();
        const count = modalNode.querySelector('[data-rich-media-selection-count]');
        const help = modalNode.querySelector('[data-rich-media-selection-help]');
        const clear = modalNode.querySelector('[data-rich-media-clear]');
        const list = modalNode.querySelector('[data-rich-media-selected-list]');
        const preview = modalNode.querySelector('[data-rich-media-preview]');
        const galleryItems = selected.filter(function (item) { return item.is_image; });

        count.textContent = selected.length ? 'Вибрано: ' + selected.length : 'Нічого не вибрано';
        help.textContent = selected.length ? (mode === 'gallery' ? 'Порядок можна змінити стрілками біля вибраних фото.' : 'Буде вставлено вибраний файл.') : 'Оберіть файл у списку.';
        clear.hidden = selected.length === 0;
        list.innerHTML = selected.map(function (item, index) {
            return '<div class="rich-media-selected-item"><span class="rich-media-selected-order">' + (index + 1) + '</span>' +
                (item.is_image ? '<img src="' + escapeHtml(item.url) + '" alt="">' : '<span class="mdi mdi-file-outline rich-media-selected-file" aria-hidden="true"></span>') +
                '<span><strong>' + escapeHtml(item.name) + '</strong><small>' + escapeHtml(item.size_label || item.type || '') + '</small></span>' +
                (mode === 'gallery' ? '<span class="rich-media-order-actions"><button type="button" data-rich-media-move="up" data-rich-media-path="' + escapeHtml(item.path) + '" title="Перемістити ліворуч"' + (index === 0 ? ' disabled' : '') + '><span class="mdi mdi-chevron-left" aria-hidden="true"></span></button><button type="button" data-rich-media-move="down" data-rich-media-path="' + escapeHtml(item.path) + '" title="Перемістити праворуч"' + (index === selected.length - 1 ? ' disabled' : '') + '><span class="mdi mdi-chevron-right" aria-hidden="true"></span></button></span>' : '') + '</div>';
        }).join('');
        list.querySelectorAll('[data-rich-media-move]').forEach(function (control) {
            control.addEventListener('click', function () {
                moveSelectedMedia(control.dataset.richMediaPath, control.dataset.richMediaMove === 'up' ? -1 : 1);
            });
        });

        if (!selected.length) {
            preview.innerHTML = '<div class="rich-media-preview-empty">Попередній перегляд зʼявиться після вибору медіафайлів.</div>';
        } else if (mode === 'gallery') {
            preview.innerHTML = galleryItems.length ? '<div class="rich-media-preview-frame">' + buildGalleryHtml(galleryItems, align, columns, caption) + '</div>' : '<div class="rich-media-preview-empty">Для галереї потрібні зображення.</div>';
        } else {
            preview.innerHTML = '<div class="rich-media-preview-frame">' + buildMediaHtml(selected[0], align, caption) + '</div>';
        }
    }

    function moveSelectedMedia(path, delta) {
        const paths = Array.from(mediaState.selected);
        const index = paths.indexOf(path);
        const target = index + delta;
        if (index < 0 || target < 0 || target >= paths.length) { return; }
        const swap = paths[target];
        paths[target] = paths[index];
        paths[index] = swap;
        mediaState.selected = new Set(paths);
        updateMediaCardSelection();
        updateSelectionPreview();
    }

    async function uploadMediaFile(event) {
        const input = event.target;
        const file = input.files && input.files[0] ? input.files[0] : null;
        if (!file) {
            return;
        }
        const modalNode = document.getElementById('richMediaModal');
        const status = modalNode.querySelector('[data-rich-media-status]');
        const formData = new FormData();
        formData.append('_csrf', adminCsrfToken);
        formData.append('file', file);
        status.textContent = 'Завантаження файлу...';
        try {
            const response = await fetch(adminBody ? (adminBody.dataset.richMediaUploadUrl || '/admin/media/upload') : '/admin/media/upload', {
                method: 'POST',
                body: formData,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Не вдалося завантажити файл.');
            }
            mediaState.selected = new Set(data.uploaded_path ? [data.uploaded_path] : []);
            input.value = '';
            await loadMediaItems(false);
            status.textContent = 'Файл завантажено.';
        } catch (error) {
            status.textContent = error.message || 'Помилка завантаження.';
        }
    }

    function insertSelectedMedia() {
        const modalNode = document.getElementById('richMediaModal');
        const selected = selectedMediaItems();
        if (!selected.length || !mediaState.editor) {
            modalNode.querySelector('[data-rich-media-status]').textContent = 'Оберіть файл для вставки.';
            return;
        }
        const mode = modalNode.querySelector('[data-rich-media-mode]').value;
        const align = modalNode.querySelector('[data-rich-media-align]').value;
        const columns = modalNode.querySelector('[data-rich-media-columns]').value;
        const caption = modalNode.querySelector('[data-rich-media-caption]').value.trim();
        const galleryItems = selected.filter(function (item) { return item.is_image; });
        const html = mode === 'gallery' ? buildGalleryHtml(galleryItems, align, columns, caption) : buildMediaHtml(selected[0], align, caption);
        if (html === '') {
            modalNode.querySelector('[data-rich-media-status]').textContent = 'Для галереї оберіть зображення.';
            return;
        }
        if (mediaState.edit && replaceMediaContent(mediaState.editor, mode, selected, galleryItems, align, columns, caption)) {
            mediaState.modal.hide();
            return;
        }
        if (mode === 'gallery' && insertGalleryContent(mediaState.editor, galleryItems, align, columns, caption)) {
            mediaState.modal.hide();
            return;
        }
        if (mode === 'single' && insertRichMediaContent(mediaState.editor, selected[0], align, caption)) {
            mediaState.modal.hide();
            return;
        }
        insertContent(mediaState.editor, html);
        mediaState.modal.hide();
    }

    function richMediaJson(item, align, caption) {
        const mediaCaption = caption || item.caption || '';
        return {
            type: 'richMedia',
            attrs: {
                src: item.url,
                alt: item.alt_text || mediaCaption || item.title || item.name || '',
                href: item.url,
                caption: mediaCaption,
                align: normalizeAlign(align)
            }
        };
    }

    function galleryJson(items, align, columns, caption, editor) {
        const content = [{
            type: 'richGallery',
            attrs: {
                columns: normalizeColumns(columns),
                align: normalizeAlign(align),
                images: items.map(function (item) {
                    return {src: item.url, alt: item.alt_text || item.title || item.name || '', href: item.url};
                })
            }
        }];
        if (caption && editor.schema.nodes.richGalleryCaption) {
            content.push({type: 'richGalleryCaption', attrs: {align: normalizeAlign(align)}, content: [{type: 'text', text: caption}]});
        }
        return content;
    }

    function insertRichMediaContent(textarea, item, align, caption) {
        const editor = textarea ? editors.get(textarea) : null;
        if (!editor || !editor.schema.nodes.richMedia || !item || !item.is_image) { return false; }
        editor.chain().focus().insertContent(richMediaJson(item, align, caption)).run();
        syncOne(textarea);
        textarea.dispatchEvent(new Event('input', {bubbles: true}));
        return true;
    }

    function replaceMediaContent(textarea, mode, selected, galleryItems, align, columns, caption) {
        const editor = textarea ? editors.get(textarea) : null;
        if (!editor || !mediaState.edit) { return false; }
        let content;
        if (mode === 'gallery') {
            if (!galleryItems.length || !editor.schema.nodes.richGallery) { return false; }
            content = galleryJson(galleryItems, align, columns, caption, editor);
        } else if (selected[0] && selected[0].is_image && editor.schema.nodes.richMedia) {
            content = richMediaJson(selected[0], align, caption);
        } else if (selected[0]) {
            content = buildMediaHtml(selected[0], align, caption);
        } else {
            return false;
        }
        editor.chain().focus().insertContentAt({from: mediaState.edit.pos, to: mediaState.edit.pos + mediaState.edit.nodeSize}, content).run();
        syncOne(textarea);
        textarea.dispatchEvent(new Event('input', {bubbles: true}));
        mediaState.edit = null;
        return true;
    }

    function insertGalleryContent(textarea, items, align, columns, caption) {
        const editor = textarea ? editors.get(textarea) : null;
        if (!editor || !editor.schema.nodes.richGallery) {
            return false;
        }
        const content = galleryJson(items, align, columns, caption, editor);
        editor.chain().focus().insertContent(content).run();
        syncOne(textarea);
        textarea.dispatchEvent(new Event('input', {bubbles: true}));
        return true;
    }

    function buildMediaHtml(item, align, caption) {
        const mediaCaption = caption || item.caption || '';
        const mediaAlt = item.alt_text || mediaCaption || item.title || item.name;
        if (item.is_image) {
            return '<figure class="rich-media-block media-align-' + escapeHtml(normalizeAlign(align)) + '"><a href="' + escapeHtml(item.url) + '"><img src="' + escapeHtml(item.url) + '" alt="' + escapeHtml(mediaAlt) + '"></a>' +
                (mediaCaption ? '<figcaption>' + escapeHtml(mediaCaption) + '</figcaption>' : '') + '</figure>';
        }
        return '<p class="rich-file-link media-align-' + escapeHtml(align) + '"><a href="' + escapeHtml(item.url) + '" target="_blank" rel="noopener">' + escapeHtml(mediaCaption || item.title || item.name) + '</a></p>';
    }

    function buildGalleryHtml(items, align, columns, caption) {
        if (!items.length) {
            return '';
        }
        const safeAlign = normalizeAlign(align);
        const safeColumns = normalizeColumns(columns);
        const images = items.map(function (item) {
            return '<figure><a href="' + escapeHtml(item.url) + '"><img src="' + escapeHtml(item.url) + '" alt="' + escapeHtml(item.alt_text || item.title || item.name) + '"></a></figure>';
        }).join('');
        return '<div class="rich-gallery rich-gallery-cols-' + escapeHtml(safeColumns) + ' media-align-' + escapeHtml(safeAlign) + '">' + images + '</div>' +
            (caption ? '<p class="rich-gallery-caption media-align-' + escapeHtml(safeAlign) + '">' + escapeHtml(caption) + '</p>' : '');
    }

    function normalizeAlign(value) {
        return ['left', 'center', 'right', 'wide'].indexOf(value) === -1 ? 'center' : value;
    }

    function normalizeColumns(value) {
        return ['2', '3', '4'].indexOf(String(value)) === -1 ? '3' : String(value);
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (character) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character];
        });
    }

    const api = {
        init: init,
        syncAll: syncAll,
        syncOne: syncOne,
        setContent: setContent,
        insertContent: insertContent
    };

    window.TiptapEditor = api;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            init(document);
        }, {once: true});
    } else {
        init(document);
    }
})();
