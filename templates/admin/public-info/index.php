<div class="page-head">
    <div>
        <p class="eyebrow">Відкритість</p>
        <h1>Публічна інформація</h1>
        <p class="page-subtitle">Чекліст розділів і документи, які публікуються на сайті.</p>
    </div>
</div>

<div class="metrics">
    <div class="metric"><span>Розділів</span><strong><?= e((string) $stats['total']) ?></strong></div>
    <div class="metric"><span>Заповнено</span><strong><?= e((string) $stats['filled']) ?></strong></div>
    <div class="metric"><span>Обов'язкові</span><strong><?= e((string) $stats['required']) ?></strong></div>
</div>

<div class="card bootstrap-card mb-4 admin-form-card">
    <div class="card-header bg-white">
        <strong>Додати розділ до переліку</strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= url('/admin/public-info/sections/save') ?>" data-section-save data-new-section>
            <?= \App\Core\Csrf::field() ?>
            <div class="row g-3">
                <div class="col-12 col-lg-5">
                    <label class="form-label">Назва розділу</label>
                    <input class="form-control" name="title" required>
                </div>
                <div class="col-12 col-lg-3">
                    <label class="form-label">Slug</label>
                    <input class="form-control" name="slug" placeholder="napryklad-rozdil">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label">Порядок</label>
                    <input class="form-control" type="number" name="sort_order" value="100">
                </div>
                <div class="col-6 col-lg-2 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" id="new-is-required" type="checkbox" name="is_required" value="1" checked>
                        <label class="form-check-label" for="new-is-required">Обов'язковий</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Опис</label>
                    <textarea class="form-control" name="description" rows="3"></textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Додати розділ</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="list-panel" data-infinite-list data-list-url="<?= url('/admin/public-info') ?>" data-list-target="#publicInfoAccordion" data-list-offset="<?= e((string) count($sections)) ?>" data-list-limit="<?= e((string) $limit) ?>" data-list-has-more="<?= count($sections) < $total ? '1' : '0' ?>">
    <div class="list-tools">
        <input data-filter-input type="search" placeholder="Пошук розділів або документів" aria-label="Пошук публічної інформації">
        <span class="meta"><span data-filter-count><?= e((string) $total) ?></span> розділів</span>
    </div>
    <div class="accordion admin-accordion" id="publicInfoAccordion">
        <?= $this->partial('admin/public-info/rows', ['sections' => $sections, 'documents' => $documents]) ?>
    </div>
    <div class="empty-state <?= $sections ? 'd-none' : '' ?>" data-list-empty>Розділи публічної інформації не знайдені.</div>
    <div class="list-sentinel" data-list-sentinel></div>
    <p class="meta list-status" data-list-status><?= count($sections) < $total ? 'Прокрутіть нижче, щоб завантажити ще.' : 'Усі записи завантажено.' ?></p>
</div>

<script>
document.addEventListener('submit', async function (event) {
    const saveForm = event.target.closest('[data-section-save]');
    const deleteForm = event.target.closest('[data-section-delete]');
    if (!saveForm && !deleteForm) {
        return;
    }

    event.preventDefault();
    const form = saveForm || deleteForm;
    const button = form.querySelector('button[type="submit"]');
    const originalText = button ? button.textContent : '';
    setFormMessage(form, 'Збереження...', false);
    if (button) {
        button.disabled = true;
    }

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        });
        const data = await response.json();
        if (!data.ok) {
            throw new Error(data.message || 'Помилка запиту.');
        }

        if (saveForm) {
            if (form.hasAttribute('data-new-section')) {
                setFormMessage(form, data.message || 'Розділ додано.', false);
                window.setTimeout(function () { window.location.reload(); }, 500);
                return;
            }

            const card = form.closest('[data-section-card]');
            if (card && data.section) {
                const title = card.querySelector('[data-section-title]');
                if (title) {
                    title.textContent = data.section.title;
                }
                form.querySelector('[name="slug"]').value = data.section.slug;
                form.querySelector('[name="sort_order"]').value = data.section.sort_order;
            }
        }

        if (deleteForm) {
            const card = form.closest('[data-section-card]');
            if (card) {
                card.remove();
            }
            return;
        }

        setFormMessage(form, data.message || 'Збережено.', false);
    } catch (error) {
        setFormMessage(form, error.message, true);
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = originalText;
        }
    }
});

function setFormMessage(form, message, isError) {
    let node = form.querySelector('[data-ajax-message]');
    if (!node) {
        node = document.createElement('div');
        node.setAttribute('data-ajax-message', '');
        form.appendChild(node);
    }
    node.className = isError ? 'alert alert-warning mt-3 mb-0' : 'small text-muted mt-3';
    node.textContent = message;
}
</script>
