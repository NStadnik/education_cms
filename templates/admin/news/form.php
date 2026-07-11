<?php
    $isEdit = !empty($item['id']);
    $body = (string) ($item['body'] ?? '');
    $currentCategory = (string) ($item['category'] ?? 'Загальні');
    $selectedCategoryIds = $selectedCategoryIds ?? [];
    $selectedCategoryTitles = [];
    foreach (($categories ?? []) as $category) {
        if (in_array((int) ($category['id'] ?? 0), $selectedCategoryIds, true)) {
            $selectedCategoryTitles[] = (string) ($category['label'] ?? $category['category']);
        }
    }
    if (!$selectedCategoryTitles) {
        $selectedCategoryTitles[] = $currentCategory;
    }
    $categorySummary = implode(', ', $selectedCategoryTitles);
    $currentStatus = (string) ($item['status'] ?? 'draft');
    $statusLabels = [
        'draft' => 'Чернетка',
        'pending_review' => 'Очікує модерації',
        'changes_requested' => 'Потрібне доопрацювання',
        'published' => 'Опубліковано',
    ];
    $statusLabel = $statusLabels[$currentStatus] ?? $currentStatus;
    $canEdit = $canEdit ?? true;
    $canReview = $canReview ?? false;
    $canPublish = $canPublish ?? false;
    $activeStep = $currentStatus === 'published' ? 3 : ($currentStatus === 'pending_review' ? 2 : 1);
    $statusTone = [
        'draft' => 'neutral',
        'pending_review' => 'waiting',
        'changes_requested' => 'attention',
        'published' => 'success',
    ][$currentStatus] ?? 'neutral';
    $statusMessages = [
        'draft' => ['Матеріал готується', 'Збережіть чернетку або надішліть готову новину на перевірку.'],
        'pending_review' => [$canReview || $canPublish ? 'Новина чекає вашого рішення' : 'Новину надіслано на перевірку', $canReview || $canPublish ? 'Перевірте текст, зображення та категорії, після чого опублікуйте або поверніть матеріал автору.' : 'Редагування тимчасово заблоковано. Після рішення модератора статус оновиться.'],
        'changes_requested' => ['Модератор просить внести зміни', 'Опрацюйте коментар, збережіть виправлення та повторно надішліть новину.'],
        'published' => ['Новина опублікована', 'Матеріал доступний читачам. Публікатор може відредагувати або зняти його з публікації.'],
    ];
    [$statusTitle, $statusMessage] = $statusMessages[$currentStatus] ?? [$statusLabel, ''];
    $imagePath = \App\Services\Files::normalize((string) ($item['image_path'] ?? ''));
    preg_match_all('/[\p{L}\p{N}]+/u', strip_tags($body), $wordMatches);
    $words = count($wordMatches[0] ?? []);
    $formatNewsDate = static function (?string $value): string {
        $timestamp = strtotime((string) $value);
        return $timestamp !== false ? date('d.m.Y H:i', $timestamp) : (string) $value;
    };
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Оголошення</p>
        <h1><?= $isEdit ? 'Редагувати новину' : 'Нова новина' ?></h1>
        <p class="page-subtitle">Підготуйте заголовок, текст і параметри публікації новини.</p>
    </div>
    <div class="form-actions">
        <a class="button secondary" href="<?= url('/admin/news') ?>"><span class="mdi mdi-arrow-left" aria-hidden="true"></span><span>До списку</span></a>
        <?php if (!empty($item['slug']) && ($item['status'] ?? '') === 'published'): ?>
            <a class="button secondary" target="_blank" href="<?= url('/news/' . $item['slug']) ?>"><span class="mdi mdi-eye-outline" aria-hidden="true"></span><span>Переглянути</span></a>
        <?php endif; ?>
    </div>
</div>

<section class="news-workflow news-workflow--<?= e($statusTone) ?>">
    <div class="news-workflow-summary">
        <span class="news-workflow-icon mdi <?= e(['draft' => 'mdi-pencil-outline', 'pending_review' => 'mdi-clock-outline', 'changes_requested' => 'mdi-message-alert-outline', 'published' => 'mdi-check-circle-outline'][$currentStatus] ?? 'mdi-circle-outline') ?>" aria-hidden="true"></span>
        <div><span class="news-status-badge"><?= e($statusLabel) ?></span><h2><?= e($statusTitle) ?></h2><p><?= e($statusMessage) ?></p></div>
        <div class="news-workflow-meta"><span><span class="mdi mdi-account-outline" aria-hidden="true"></span><?= e((string) ($item['author_name'] ?? ($user['name'] ?? 'Ви'))) ?></span><span><span class="mdi mdi-format-text" aria-hidden="true"></span><?= e((string) $words) ?> слів</span><span><span class="mdi mdi-shape-outline" aria-hidden="true"></span><?= e($categorySummary) ?></span></div>
    </div>
    <ol class="news-workflow-steps" aria-label="Етапи публікації">
        <?php foreach ([1 => ['Чернетка', 'Підготовка матеріалу'], 2 => ['Модерація', 'Перевірка редактором'], 3 => ['Публікація', 'Доступно на сайті']] as $step => [$stepTitle, $stepHint]): ?>
            <li class="<?= $step < $activeStep ? 'is-complete' : ($step === $activeStep ? 'is-current' : '') ?><?= $currentStatus === 'changes_requested' && $step === 1 ? ' is-attention' : '' ?>">
                <span class="news-workflow-step-number"><?= $step < $activeStep ? '<span class="mdi mdi-check" aria-hidden="true"></span>' : e((string) $step) ?></span><div><strong><?= e($stepTitle) ?></strong><small><?= e($stepHint) ?></small></div>
            </li>
        <?php endforeach; ?>
    </ol>

    <?php if ($isEdit && (($currentStatus === 'pending_review' && ($canReview || $canPublish)) || ($currentStatus === 'published' && $canPublish))): ?>
        <div class="news-decision-panel">
            <?php if ($currentStatus === 'pending_review' && $canPublish): ?>
                <form method="post" action="<?= url('/admin/news/publish') ?>" class="news-decision-form news-decision-form--publish">
                    <?= \App\Core\Csrf::field() ?><input type="hidden" name="id" value="<?= e((string) $item['id']) ?>"><input type="hidden" name="version" value="<?= e((string) ($item['version'] ?? 1)) ?>">
                    <div><strong>Готово до публікації?</strong><span>Дата необов’язкова — без неї буде використано поточний час.</span></div>
                    <label><span>Дата публікації</span><input name="published_at" type="datetime-local" max="<?= e(date('Y-m-d\\TH:i')) ?>"></label>
                    <button type="submit"><span class="mdi mdi-check-circle-outline" aria-hidden="true"></span><span>Схвалити й опублікувати</span></button>
                </form>
            <?php endif; ?>
            <?php if ($currentStatus === 'pending_review' && $canReview): ?>
                <form method="post" action="<?= url('/admin/news/request-changes') ?>" class="news-decision-form news-decision-form--return">
                    <?= \App\Core\Csrf::field() ?><input type="hidden" name="id" value="<?= e((string) $item['id']) ?>"><input type="hidden" name="version" value="<?= e((string) ($item['version'] ?? 1)) ?>">
                    <label><span>Що потрібно виправити</span><textarea name="review_comment" required placeholder="Наприклад: уточніть дату події та додайте головне зображення"></textarea></label>
                    <button class="button secondary" type="submit"><span class="mdi mdi-undo-variant" aria-hidden="true"></span><span>Повернути автору</span></button>
                </form>
            <?php endif; ?>
            <?php if ($currentStatus === 'published' && $canPublish): ?>
                <form method="post" action="<?= url('/admin/news/unpublish') ?>" class="news-decision-form news-decision-form--unpublish" data-moderation-confirm="Зняти новину з публікації? Вона одразу зникне з публічного сайту.">
                    <?= \App\Core\Csrf::field() ?><input type="hidden" name="id" value="<?= e((string) $item['id']) ?>"><input type="hidden" name="version" value="<?= e((string) ($item['version'] ?? 1)) ?>">
                    <div><strong>Керування публікацією</strong><span>Після зняття новина стане чернеткою і зникне з публічного сайту.</span></div>
                    <button class="button secondary" type="submit"><span class="mdi mdi-eye-off-outline" aria-hidden="true"></span><span>Зняти з публікації</span></button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($moderationEvents ?? []): ?>
        <details class="moderation-history"><summary>Історія модерації <span><?= e((string) count($moderationEvents)) ?></span></summary><div class="moderation-history-list"><?php foreach ($moderationEvents as $event): ?><article><span class="mdi mdi-swap-horizontal" aria-hidden="true"></span><div><strong><?= e((string) ($event['user_name'] ?? 'Система')) ?></strong><p><?= e((string) ($statusLabels[$event['from_status']] ?? $event['from_status'])) ?> → <?= e((string) ($statusLabels[$event['to_status']] ?? $event['to_status'])) ?></p><?php if (!empty($event['comment'])): ?><blockquote><?= nl2br(e((string) $event['comment'])) ?></blockquote><?php endif; ?><time><?= e($formatNewsDate((string) $event['created_at'])) ?></time></div></article><?php endforeach; ?></div></details>
    <?php endif; ?>
</section>

<form method="post" action="<?= url('/admin/news/save') ?>" enctype="multipart/form-data">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= e((string) ($item['id'] ?? '')) ?>">
    <input type="hidden" name="version" value="<?= e((string) ($item['version'] ?? 1)) ?>">

    <?php if (!$canEdit): ?>
        <div class="alert"><?= $currentStatus === 'pending_review' && ($canReview || $canPublish) ? 'Увімкнено режим перегляду: оцініть матеріал і скористайтеся діями модерації вище.' : 'Матеріал заблоковано для редагування в поточному статусі. Дочекайтеся рішення модератора.' ?></div>
    <?php endif; ?>

    <fieldset class="editor-layout moderation-fieldset" <?= !$canEdit ? 'disabled' : '' ?>>
        <section class="card admin-form-card">
            <div class="form-section-head">
                <div>
                    <h2>Матеріал</h2>
                    <p class="meta">Основний текст новини буде показаний у списку та на окремій сторінці.</p>
                </div>
            </div>

            <div class="form-grid wide">
                <label>Назва<input name="title" value="<?= e($item['title'] ?? '') ?>" required></label>
                <label>Текст<textarea class="textarea-large" name="body" data-tiptap-editor required><?= e($item['body'] ?? '') ?></textarea></label>
            </div>
        </section>

        <aside class="card admin-form-card editor-sidebar">
            <div class="sidebar-section">
                <div class="form-section-head">
                    <div>
                        <h2>Публікація</h2>
                        <p class="meta">Дата заповниться автоматично для опублікованої новини.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-field">
                        <div class="field-label">Головне зображення</div>
                        <div class="news-image-picker" data-news-image-picker data-picker-url="<?= url('/admin/media/picker') ?>" data-thumb-base="<?= url('/thumb/') ?>">
                            <input type="hidden" name="image_path" value="<?= e($imagePath) ?>" data-news-image-input>
                            <input type="hidden" name="remove_image" value="0" data-news-image-remove>
                            <div class="news-image-preview" data-news-image-preview>
                                <?php if ($imagePath !== ''): ?>
                                    <img src="<?= url('/thumb/' . $imagePath . '?w=320&h=180&fit=crop') ?>" alt="">
                                <?php else: ?>
                                    <span class="mdi mdi-image-outline" aria-hidden="true"></span>
                                <?php endif; ?>
                            </div>
                            <div class="news-image-name" data-news-image-name><?= $imagePath !== '' ? e($imagePath) : 'Зображення не вибрано' ?></div>
                            <div class="settings-logo-actions">
                                <button class="button secondary compact" type="button" data-news-image-open>
                                    <span class="mdi mdi-image-search-outline" aria-hidden="true"></span><span>Обрати з медіа</span>
                                </button>
                                <button class="button secondary compact" type="button" data-news-image-clear <?= $imagePath === '' ? 'hidden' : '' ?>>
                                    <span class="mdi mdi-close" aria-hidden="true"></span><span>Очистити</span>
                                </button>
                            </div>
                            <label>Завантажити зображення
                                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-news-image-file>
                            </label>
                            <p class="meta">Оберіть зображення з медіатеки або завантажте нове JPG, PNG чи WebP.</p>
                        </div>
                    </div>
                    <div class="form-field"><div class="field-label">Статус</div><strong><?= e($statusLabel) ?></strong></div>
                    <?php if (!empty($item['review_comment'])): ?><div class="news-review-comment"><span class="mdi mdi-message-alert-outline" aria-hidden="true"></span><div><strong>Коментар модератора</strong><p><?= nl2br(e((string) $item['review_comment'])) ?></p></div></div><?php endif; ?>
                    <div class="form-field">
                        <div class="field-label">Категорії</div>
                        <div class="category-picker" data-category-picker>
                            <div class="category-picker-head">
                                <div class="category-picker-summary">
                                    <strong><span data-category-count><?= e((string) count($selectedCategoryTitles)) ?></span> вибрано</strong>
                                    <small data-category-summary><?= e($categorySummary) ?></small>
                                </div>
                                <?php if ($canManageCategories ?? false): ?><a class="button secondary icon-button" href="<?= url('/admin/news/categories') ?>" title="Редагувати категорії" aria-label="Редагувати категорії"><span class="mdi mdi-shape-outline" aria-hidden="true"></span></a><?php endif; ?>
                            </div>
                            <label class="category-picker-search">
                                <span class="mdi mdi-magnify" aria-hidden="true"></span>
                                <input type="search" data-category-filter placeholder="Шукати категорію">
                            </label>
                            <div class="category-picker-list" data-category-list>
                                <?php foreach (($categories ?? []) as $category): ?>
                                    <?php
                                        $categoryTitle = (string) ($category['label'] ?? $category['category']);
                                        $isSelectedCategory = in_array((int) ($category['id'] ?? 0), $selectedCategoryIds, true) || (!$selectedCategoryIds && ($category['category'] ?? '') === $currentCategory);
                                    ?>
                                    <label class="category-option" data-category-item data-category-title="<?= e($categoryTitle) ?>">
                                        <input type="checkbox" name="category_ids[]" value="<?= e((string) $category['id']) ?>" <?= checked($isSelectedCategory) ?>>
                                        <span class="category-option-box"><span class="mdi mdi-check" aria-hidden="true"></span></span>
                                        <span class="category-option-title"><?= e($categoryTitle) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="category-picker-empty meta" data-category-empty hidden>Категорій не знайдено.</div>
                        </div>
                    </div>
                    <?php if (!empty($item['published_at'])): ?>
                        <div class="form-field"><div class="field-label">Дата публікації</div><span><?= e($formatNewsDate((string) $item['published_at'])) ?></span></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sidebar-section">
                <div class="form-section-head">
                    <div>
                        <h2>SEO</h2>
                        <p class="meta">Адреса формується автоматично, але її можна змінити.</p>
                    </div>
                </div>
                <div class="form-grid">
                    <label>Slug<input name="slug" value="<?= e($item['slug'] ?? '') ?>" placeholder="nazva-novyny"></label>
                </div>
            </div>

            <div class="form-actions stacked">
                <?php if ($canEdit): ?>
                    <?php if (in_array($currentStatus, ['draft', 'changes_requested'], true)): ?>
                        <button type="submit" name="submit_for_review" value="1"><span class="mdi mdi-send-outline" aria-hidden="true"></span><span><?= $currentStatus === 'changes_requested' ? 'Зберегти виправлення й надіслати' : 'Зберегти й надіслати на модерацію' ?></span></button>
                    <?php endif; ?>
                    <button class="button secondary" type="submit"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span>Зберегти без надсилання</span></button>
                <?php endif; ?>
                <?php if ($isEdit && $canEdit && !in_array($currentStatus, ['pending_review', 'published'], true)): ?>
                    <button class="button danger" type="submit" form="newsDeleteForm"><span class="mdi mdi-delete-outline" aria-hidden="true"></span><span>Видалити</span></button>
                <?php endif; ?>
                <a class="button secondary" href="<?= url('/admin/news') ?>"><span class="mdi mdi-close" aria-hidden="true"></span><span>Скасувати</span></a>
            </div>
        </aside>
    </fieldset>
</form>
<div class="modal fade" id="newsImagePickerModal" tabindex="-1" aria-labelledby="newsImagePickerTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1">Медіафайли</p>
                    <h2 class="modal-title h5" id="newsImagePickerTitle">Обрати головне зображення</h2>
                    <p class="meta mb-0">Показуються лише зображення з медіатеки.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
            </div>
            <div class="modal-body">
                <div class="settings-logo-modal-tools">
                    <label class="list-search-field">
                        <span class="mdi mdi-magnify" aria-hidden="true"></span>
                        <input type="search" data-news-image-search placeholder="Пошук зображення">
                    </label>
                    <span class="list-count-pill" data-news-image-status>Готово</span>
                </div>
                <div class="settings-logo-grid" data-news-image-grid></div>
                <button class="button secondary compact settings-logo-more" type="button" data-news-image-more hidden>
                    <span class="mdi mdi-chevron-down" aria-hidden="true"></span><span>Показати ще</span>
                </button>
            </div>
            <div class="modal-footer">
                <button type="button" class="button secondary" data-bs-dismiss="modal"><span class="mdi mdi-close" aria-hidden="true"></span><span>Закрити</span></button>
            </div>
        </div>
    </div>
</div>
<?php if ($isEdit): ?>
    <form id="newsDeleteForm" method="post" action="<?= url('/admin/news/bulk') ?>" data-no-ajax data-delete-confirm="Видалити цю новину?" data-after-success-url="<?= url('/admin/news') ?>">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="bulk_action" value="delete">
        <input type="hidden" name="ids[]" value="<?= e((string) $item['id']) ?>">
    </form>
<?php endif; ?>
<script src="<?= url('/assets/admin-news.js?v=20260711-3') ?>"></script>
