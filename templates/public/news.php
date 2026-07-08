<section class="section">
    <div class="container">
        <div class="page-head public-head">
            <div>
                <p class="eyebrow">Останні події</p>
                <h1>Новини</h1>
                <p class="page-subtitle">Актуальні повідомлення, оголошення та події закладу.</p>
            </div>
            <span class="status" data-news-total-count><?= e((string) ($total ?? count($items))) ?> записів</span>
        </div>
        <?php
            $total = (int) ($total ?? count($items));
            $limit = max(1, (int) ($limit ?? 9));
            $page = max(1, (int) ($page ?? 1));
            $pages = max(1, (int) ceil($total / $limit));
            $currentPage = min($page, $pages);
            $loaded = min($total, (($currentPage - 1) * $limit) + count($items));
            $hasMore = $loaded < $total;
            $activeCategory = (string) ($activeCategory ?? '');
            $activeQuery = (string) ($activeQuery ?? '');
            $newsUrl = static function (?string $category = null, string $search = '', int $targetPage = 1): string {
                $params = [];
                if ($category !== null && $category !== '') {
                    $params['category'] = $category;
                }
                if ($search !== '') {
                    $params['q'] = $search;
                }
                if ($targetPage > 1) {
                    $params['page'] = $targetPage;
                }
                return url('/news' . ($params ? '?' . http_build_query($params) : ''));
            };
            $pageUrl = static fn (int $targetPage): string => $newsUrl($activeCategory, $activeQuery, $targetPage);
        ?>
        <form class="news-filter-panel" method="get" action="<?= url('/news') ?>" data-news-filter-form>
            <label class="news-search-field">
                <span class="mdi mdi-magnify" aria-hidden="true"></span>
                <input type="search" name="q" value="<?= e($activeQuery) ?>" placeholder="Пошук за назвою, текстом або категорією" aria-label="Пошук новин">
            </label>
            <label class="news-category-select">
                <span class="mdi mdi-shape-outline" aria-hidden="true"></span>
                <select name="category" aria-label="Категорія новин">
                    <option value="">Усі категорії</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e($category['category']) ?>" <?= selected($activeCategory, (string) $category['category']) ?>>
                            <?= e($category['category']) ?> · <?= e((string) $category['items_count']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="button" type="submit"><span class="mdi mdi-filter-check-outline" aria-hidden="true"></span><span>Знайти</span></button>
            <a class="button secondary <?= ($activeQuery === '' && $activeCategory === '') ? 'd-none' : '' ?>" href="<?= url('/news') ?>" data-news-filter-reset><span class="mdi mdi-close" aria-hidden="true"></span><span>Скинути</span></a>
            <span class="news-filter-count" data-news-filter-count><?= e((string) $total) ?> знайдено</span>
        </form>
        <div data-news-category-slot>
            <?= $this->partial('public/partials/news-categories', [
                'categories' => $categories,
                'activeCategory' => $activeCategory,
                'activeQuery' => $activeQuery,
                'newsUrl' => $newsUrl,
            ]) ?>
        </div>
        <div class="news-pager-sticky" data-news-pager-slot>
            <?= $this->partial('public/partials/pager', [
                'currentPage' => $currentPage,
                'pages' => $pages,
                'urlFactory' => $pageUrl,
                'label' => 'Навігація сторінками новин',
                'jumpLabel' => 'Сторінка',
                'class' => 'news-pager',
            ]) ?>
        </div>
        <div class="news-list" data-public-news-list data-list-url="<?= url('/news') ?>" data-list-offset="<?= e((string) $loaded) ?>" data-list-limit="<?= e((string) $limit) ?>" data-list-has-more="<?= $hasMore ? '1' : '0' ?>" data-list-category="<?= e($activeCategory) ?>" data-list-query="<?= e($activeQuery) ?>">
            <div class="grid grid-3 news-grid" data-news-grid>
                <?= $this->partial('public/partials/news-cards', ['items' => $items]) ?>
            </div>
            <div class="empty-state <?= $items ? 'd-none' : '' ?>" data-news-empty>За цими фільтрами новин не знайдено.</div>
            <div class="news-list-sentinel" data-news-sentinel></div>
            <p class="meta news-list-status" data-news-status><?= $hasMore ? 'Прокрутіть нижче, щоб завантажити ще.' : 'Усі новини завантажено.' ?></p>
        </div>
    </div>
</section>
