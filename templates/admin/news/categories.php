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

<div class="editor-layout">
    <section class="card admin-form-card">
        <div class="form-section-head">
            <div>
                <h2>Перелік</h2>
                <p class="meta">Категорії з новинами не видаляються, щоб матеріали не втратили рубрику.</p>
            </div>
        </div>

        <div class="table-scroll">
            <table>
                <thead><tr><th>Назва</th><th>Батьківська</th><th>Порядок</th><th>Новин</th><th></th></tr></thead>
                <tbody id="newsCategoryRows">
                    <?= $this->partial('admin/news/category-rows', ['categories' => $categories, 'parentOptions' => $parentOptions]) ?>
                </tbody>
            </table>
        </div>
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
    </aside>
</div>
