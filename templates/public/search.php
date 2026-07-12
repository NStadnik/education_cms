<?php
    $query = (string) ($query ?? '');
    $minimumQueryLength = (int) ($minimumQueryLength ?? 3);
    $queryLength = function_exists('mb_strlen') ? mb_strlen($query) : strlen($query);
    $total = array_sum($totals ?? []);
    $groups = [
        ['type' => 'pages', 'id' => 'search-pages', 'title' => 'Сторінки', 'icon' => 'mdi-file-document-outline', 'items' => $pages ?? []],
        ['type' => 'news', 'id' => 'search-news', 'title' => 'Новини', 'icon' => 'mdi-newspaper-variant-outline', 'items' => $news ?? []],
        ['type' => 'categories', 'id' => 'search-categories', 'title' => 'Категорії новин', 'icon' => 'mdi-shape-outline', 'items' => $categories ?? []],
        ['type' => 'media', 'id' => 'search-media', 'title' => 'Медіафайли', 'icon' => 'mdi-file-image-outline', 'items' => $media ?? []],
    ];
?>
<main class="public-search-page">
    <section class="public-search-hero">
        <div class="container">
            <p class="eyebrow">Пошук</p>
            <h1>Знайдіть потрібну інформацію</h1>
            <p>Шукайте серед сторінок, новин, категорій та медіафайлів сайту.</p>
            <form class="public-search-form" method="get" action="<?= url('/search') ?>" role="search" data-public-search-form>
                <label class="visually-hidden" for="publicSearchQuery">Пошуковий запит</label>
                <span class="mdi mdi-magnify public-search-form-icon" aria-hidden="true"></span>
                <input id="publicSearchQuery" type="search" name="q" value="<?= e($query) ?>" placeholder="Введіть щонайменше <?= e((string) $minimumQueryLength) ?> символи" minlength="<?= e((string) $minimumQueryLength) ?>" required autofocus>
                <button class="button" type="submit"><span>Знайти</span></button>
            </form>
        </div>
    </section>

    <div class="container public-search-content" data-public-search-content>

    <?php if ($queryLength < $minimumQueryLength): ?>
        <div class="public-search-empty">
            <span class="mdi mdi-text-search" aria-hidden="true"></span>
            <h2>Почніть пошук</h2>
            <p>Введіть щонайменше <?= e((string) $minimumQueryLength) ?> символи — назву, ключове слово або частину тексту.</p>
        </div>
    <?php else: ?>
        <div class="public-search-summary">
            <div><span>Результати для</span><strong>«<?= e($query) ?>»</strong></div>
            <span class="public-search-total"><?= e((string) $total) ?> знайдено</span>
        </div>
        <?php if ($total === 0): ?>
            <div class="public-search-empty">
                <span class="mdi mdi-magnify-close" aria-hidden="true"></span>
                <h2>Нічого не знайдено</h2>
                <p>Перевірте написання, скоротіть запит або спробуйте інші ключові слова.</p>
            </div>
        <?php else: ?>
            <div class="public-search-filters" role="group" aria-label="Фільтр за типом результату" data-search-filters>
                <button class="is-active" type="button" data-search-filter="all" aria-pressed="true">
                    <span class="mdi mdi-view-grid-outline" aria-hidden="true"></span>Усі <strong><?= e((string) $total) ?></strong>
                </button>
                <?php foreach ($groups as $group): ?>
                    <?php if (empty(($totals ?? [])[$group['type']])) { continue; } ?>
                    <button type="button" data-search-filter="<?= e($group['type']) ?>" aria-pressed="false"><span class="mdi <?= e($group['icon']) ?>" aria-hidden="true"></span><?= e($group['title']) ?><strong><?= e((string) $totals[$group['type']]) ?></strong></button>
                <?php endforeach; ?>
            </div>
            <section class="public-search-section public-search-unified" data-search-group="all">
                <div class="public-search-section-head">
                    <span class="mdi mdi-text-box-search-outline" aria-hidden="true"></span>
                    <h2 data-search-results-title>Усі результати</h2>
                    <span data-search-visible-count><?= e((string) $total) ?></span>
                </div>
                <div class="public-search-results" data-search-results="all">
                <?php foreach ($groups as $group): ?>
                    <?php if (empty(($totals ?? [])[$group['type']])) { continue; } ?>
                    <?= $this->partial('public/partials/search-items', ['type' => $group['type'], 'items' => $group['items']]) ?>
                <?php endforeach; ?>
                </div>
                <div class="public-search-group-status" data-search-group-status="all"<?= in_array(true, $hasMore ?? [], true) ? '' : ' hidden' ?>>
                    <span class="mdi mdi-chevron-down" aria-hidden="true"></span><span>Прокрутіть список, щоб показати ще</span>
                </div>
            </section>
            <div class="public-search-load" data-search-loader hidden
                 data-search-url="<?= url('/search') ?>"
                 data-search-query="<?= e($query) ?>"
                 data-search-offsets="<?= e(json_encode($nextOffsets ?? [], JSON_UNESCAPED_UNICODE) ?: '{}') ?>"
                 data-search-more="<?= e(json_encode($hasMore ?? [], JSON_UNESCAPED_UNICODE) ?: '{}') ?>">
                <span class="mdi mdi-loading mdi-spin" aria-hidden="true"></span><span data-search-loader-text>Завантаження результатів…</span>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</main>

<div class="modal fade public-media-modal" id="publicSearchMediaModal" tabindex="-1" aria-labelledby="publicSearchMediaTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1">Медіафайл</p>
                    <h2 class="modal-title h5" id="publicSearchMediaTitle" data-public-media-title>Перегляд файлу</h2>
                    <p class="meta mb-0" data-public-media-meta></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
            </div>
            <div class="modal-body">
                <div class="public-media-viewer" data-public-media-viewer></div>
            </div>
            <div class="modal-footer">
                <a class="button" href="#" target="_blank" rel="noopener" data-public-media-open><span class="mdi mdi-open-in-new" aria-hidden="true"></span><span>Відкрити файл</span></a>
                <button type="button" class="button secondary" data-bs-dismiss="modal"><span class="mdi mdi-close" aria-hidden="true"></span><span>Закрити</span></button>
            </div>
        </div>
    </div>
</div>
