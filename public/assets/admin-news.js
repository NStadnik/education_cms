document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('submit', function (event) {
        const form = event.target.closest('[data-moderation-confirm]');
        if (form && !window.confirm(form.dataset.moderationConfirm || 'Виконати дію?')) {
            event.preventDefault();
        }
    }, true);

    document.addEventListener('admin:form-saved', async function (event) {
        const form = event.detail && event.detail.form;
        const data = event.detail && event.detail.data ? event.detail.data : {};
        const isDecision = form && form.matches('.news-decision-form');
        const isSubmittedForReview = data.moderation_transition === 'submit';
        if (!form || (!isDecision && !isSubmittedForReview) || !data.redirect_url) {
            return;
        }
        const content = document.querySelector('.admin-content');
        if (!content) { return; }
        try {
            const response = await fetch(data.redirect_url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            if (!response.ok) { throw new Error('Не вдалося оновити редактор новини.'); }
            const documentHtml = new DOMParser().parseFromString(await response.text(), 'text/html');
            const freshContent = documentHtml.querySelector('.admin-content');
            if (!freshContent) { throw new Error('Сервер повернув некоректну сторінку редактора.'); }
            content.innerHTML = freshContent.innerHTML;
            if (window.history && window.history.replaceState) { window.history.replaceState(null, '', data.redirect_url); }
            document.dispatchEvent(new CustomEvent('admin:content-replaced', {detail: {target: content}}));
        } catch (error) {
            const message = document.createElement('div');
            message.className = 'alert alert-danger';
            message.textContent = error.message || 'Не вдалося оновити статус новини.';
            content.prepend(message);
        }
    });

    function initNewsImagePickers(root) {
    (root || document).querySelectorAll('[data-news-image-picker]').forEach(function (picker) {
        if (picker.dataset.newsImagePickerReady === '1') { return; }
        picker.dataset.newsImagePickerReady = '1';
        const input = picker.querySelector('[data-news-image-input]');
        const removeInput = picker.querySelector('[data-news-image-remove]');
        const preview = picker.querySelector('[data-news-image-preview]');
        const name = picker.querySelector('[data-news-image-name]');
        const fileInput = picker.querySelector('[data-news-image-file]');
        const openButton = picker.querySelector('[data-news-image-open]');
        const clearButton = picker.querySelector('[data-news-image-clear]');
        const modalNode = document.getElementById('newsImagePickerModal');
        const modal = modalNode && window.bootstrap ? new window.bootstrap.Modal(modalNode) : null;
        const grid = modalNode ? modalNode.querySelector('[data-news-image-grid]') : null;
        const search = modalNode ? modalNode.querySelector('[data-news-image-search]') : null;
        const status = modalNode ? modalNode.querySelector('[data-news-image-status]') : null;
        const more = modalNode ? modalNode.querySelector('[data-news-image-more]') : null;
        const state = {offset: 0, limit: 10, hasMore: false, loading: false, timer: null};

        if (!input || !removeInput || !preview || !name || !openButton || !modal || !grid) {
            return;
        }

        function escapeHtml(value) {
            return String(value || '').replace(/[&<>"']/g, function (character) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character];
            });
        }

        function thumbUrl(path, width, height) {
            const base = picker.dataset.thumbBase || '/thumb/';
            return base + String(path || '').replace(/^\/+/, '') + '?w=' + width + '&h=' + height + '&fit=crop';
        }

        function setStatus(text) {
            if (status) {
                status.textContent = text;
            }
        }

        function setImage(path) {
            input.value = path || '';
            removeInput.value = path ? '0' : '1';
            if (fileInput) {
                fileInput.value = '';
            }
            if (path) {
                preview.innerHTML = '<img src="' + escapeHtml(thumbUrl(path, 320, 180)) + '" alt="">';
                name.textContent = path;
                if (clearButton) {
                    clearButton.hidden = false;
                }
            } else {
                preview.innerHTML = '<span class="mdi mdi-image-outline" aria-hidden="true"></span>';
                name.textContent = 'Зображення не вибрано';
                if (clearButton) {
                    clearButton.hidden = true;
                }
            }
        }

        async function loadItems(append) {
            if (state.loading) {
                return;
            }
            state.loading = true;
            if (!append) {
                state.offset = 0;
                grid.innerHTML = '';
            }
            if (more) {
                more.hidden = true;
            }
            setStatus('Завантаження...');

            const url = new URL(picker.dataset.pickerUrl || '/admin/media/picker', window.location.origin);
            url.searchParams.set('limit', String(state.limit));
            url.searchParams.set('offset', append ? String(state.offset) : '0');
            url.searchParams.set('images_only', '1');
            if (search && search.value.trim() !== '') {
                url.searchParams.set('q', search.value.trim());
            }

            try {
                const response = await fetch(url.toString(), {headers: {'X-Requested-With': 'XMLHttpRequest'}});
                const data = await response.json();
                if (!response.ok || !data.ok) {
                    throw new Error(data.message || 'Не вдалося завантажити медіафайли.');
                }
                state.offset = Number(data.next_offset || 0);
                state.hasMore = Boolean(data.has_more);
                renderItems((Array.isArray(data.items) ? data.items : []).filter(function (item) {
                    return item.is_image;
                }), append);
                setStatus(grid.querySelector('[data-news-image-item]') ? 'Оберіть зображення.' : 'Зображень не знайдено.');
                if (more) {
                    more.hidden = !state.hasMore;
                }
            } catch (error) {
                setStatus(error.message || 'Помилка завантаження.');
            } finally {
                state.loading = false;
            }
        }

        function renderItems(items, append) {
            if (!append) {
                grid.innerHTML = '';
            }
            items.forEach(function (item) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'settings-logo-item';
                button.dataset.newsImageItem = '';
                button.dataset.path = item.path || '';
                button.innerHTML =
                    '<img src="' + escapeHtml(thumbUrl(item.path || '', 180, 135)) + '" alt="">' +
                    '<span>' + escapeHtml(item.name || item.path || 'Зображення') + '</span>';
                grid.appendChild(button);
            });
        }

        openButton.addEventListener('click', function () {
            modal.show();
            loadItems(false);
            if (search) {
                setTimeout(function () {
                    search.focus();
                }, 180);
            }
        });

        if (clearButton) {
            clearButton.addEventListener('click', function () {
                setImage('');
            });
        }

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                if (fileInput.files && fileInput.files.length) {
                    input.value = '';
                    removeInput.value = '0';
                    name.textContent = fileInput.files[0].name;
                    if (clearButton) {
                        clearButton.hidden = false;
                    }
                }
            });
        }

        modalNode.addEventListener('click', function (event) {
            const item = event.target.closest('[data-news-image-item]');
            if (item) {
                setImage(item.dataset.path || '');
                modal.hide();
                return;
            }
            if (event.target.closest('[data-news-image-more]')) {
                loadItems(true);
            }
        });

        if (search) {
            search.addEventListener('input', function () {
                window.clearTimeout(state.timer);
                state.timer = window.setTimeout(function () {
                    loadItems(false);
                }, 250);
            });
        }
    });
    }

    initNewsImagePickers(document);
    document.addEventListener('admin:content-replaced', function (event) {
        const target = event.detail && event.detail.target ? event.detail.target : document;
        initNewsImagePickers(target);
    });
});
