<?php
    $categoryCount = count($categories ?? []);
    $usedCount = 0;
    $childCount = 0;
    $totalNewsLinks = 0;
    foreach (($categories ?? []) as $category) {
        $newsCount = (int) ($category['news_count'] ?? 0);
        $childrenCount = (int) ($category['children_count'] ?? 0);
        $usedCount += $newsCount > 0 ? 1 : 0;
        $childCount += $childrenCount > 0 ? 1 : 0;
        $totalNewsLinks += $newsCount;
    }
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Оголошення</p>
        <h1>Категорії новин</h1>
        <p class="page-subtitle">Редагуйте перелік категорій, які доступні під час створення новин.</p>
    </div>
    <div class="form-actions">
        <a class="button secondary" href="<?= url('/admin/news') ?>"><span class="mdi mdi-arrow-left" aria-hidden="true"></span><span>До новин</span></a>
        <a class="button" href="<?= url('/admin/news/edit') ?>"><span class="mdi mdi-plus" aria-hidden="true"></span><span>Додати новину</span></a>
    </div>
</div>

<section class="news-category-summary" data-news-category-summary>
    <article class="news-category-summary-item">
        <span class="mdi mdi-shape-outline" aria-hidden="true"></span>
        <div>
            <small>Категорій</small>
            <strong data-category-total><?= e((string) $categoryCount) ?></strong>
        </div>
    </article>
    <article class="news-category-summary-item">
        <span class="mdi mdi-newspaper-variant-outline" aria-hidden="true"></span>
        <div>
            <small>З новинами</small>
            <strong data-category-used><?= e((string) $usedCount) ?></strong>
        </div>
    </article>
    <article class="news-category-summary-item">
        <span class="mdi mdi-file-tree-outline" aria-hidden="true"></span>
        <div>
            <small>Мають підкатегорії</small>
            <strong data-category-parents><?= e((string) $childCount) ?></strong>
        </div>
    </article>
    <article class="news-category-summary-item">
        <span class="mdi mdi-link-variant" aria-hidden="true"></span>
        <div>
            <small>Привʼязок новин</small>
            <strong data-category-links><?= e((string) $totalNewsLinks) ?></strong>
        </div>
    </article>
</section>

<div class="editor-layout">
    <section class="card admin-form-card">
        <div class="form-section-head">
            <div>
                <h2>Перелік</h2>
                <p class="meta">Категорії з новинами не видаляються, щоб матеріали не втратили рубрику.</p>
            </div>
        </div>

        <div class="news-category-tools">
            <label class="list-search-field news-category-search">
                <span class="mdi mdi-magnify" aria-hidden="true"></span>
                <input type="search" data-news-category-filter placeholder="Пошук категорії, slug або id">
            </label>
            <span class="list-count-pill" data-news-category-visible><?= e((string) $categoryCount) ?> показано</span>
        </div>

        <div class="table-scroll">
            <table class="news-category-table">
                <thead><tr><th>Категорія</th><th>Батьківська</th><th>Порядок</th><th>Використання</th><th></th></tr></thead>
                <tbody id="newsCategoryRows" data-news-category-rows>
                    <?= $this->partial('admin/news/category-rows', ['categories' => $categories, 'parentOptions' => $parentOptions]) ?>
                </tbody>
            </table>
        </div>
        <div class="category-picker-empty meta" data-news-category-empty hidden>За цим пошуком категорій не знайдено.</div>
    </section>

    <aside class="card admin-form-card editor-sidebar">
        <div class="form-section-head">
            <div>
                <h2>Нова категорія</h2>
                <p class="meta">Після збереження вона з'явиться у формі новини.</p>
            </div>
        </div>
        <form method="post" action="<?= url('/admin/news/categories/save') ?>" data-replace-target="#newsCategoryRows" data-options-target="#newCategoryParent">
            <?= \App\Core\Csrf::field() ?>
            <div class="form-grid">
                <label>Назва<input name="title" required></label>
                <label>Батьківська категорія
                    <select id="newCategoryParent" name="parent_id">
                        <?= $this->partial('admin/news/category-parent-options', ['parentOptions' => $parentOptions]) ?>
                    </select>
                </label>
                <label>Порядок<input type="number" name="sort_order" value="100"></label>
            </div>
            <div class="form-actions stacked">
                <button type="submit"><span class="mdi mdi-plus" aria-hidden="true"></span><span>Додати категорію</span></button>
            </div>
        </form>
        <div class="news-category-help">
            <span class="mdi mdi-information-outline" aria-hidden="true"></span>
            <p>Категорію можна видалити лише тоді, коли до неї не привʼязані новини і вона не має підкатегорій.</p>
        </div>
    </aside>
</div>
