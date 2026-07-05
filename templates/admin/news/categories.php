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
                <thead><tr><th>Назва</th><th>Порядок</th><th>Новин</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td>
                                <form id="categoryForm<?= e((string) $category['id']) ?>" method="post" action="<?= url('/admin/news/categories/save') ?>" data-after-success-url="<?= url('/admin/news/categories') ?>">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $category['id']) ?>">
                                    <input name="title" value="<?= e($category['title']) ?>" required aria-label="Назва категорії">
                                </form>
                            </td>
                            <td><input form="categoryForm<?= e((string) $category['id']) ?>" type="number" name="sort_order" value="<?= e((string) $category['sort_order']) ?>" aria-label="Порядок"></td>
                            <td><span class="status"><?= e((string) $category['news_count']) ?></span></td>
                            <td>
                                <div class="form-actions">
                                    <button class="button secondary compact" type="submit" form="categoryForm<?= e((string) $category['id']) ?>"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span>Зберегти</span></button>
                                    <?php if ((int) $category['news_count'] === 0): ?>
                                        <form method="post" action="<?= url('/admin/news/categories/delete') ?>" data-after-success-url="<?= url('/admin/news/categories') ?>" onsubmit="return confirm('Видалити цю категорію?')">
                                            <?= \App\Core\Csrf::field() ?>
                                            <input type="hidden" name="id" value="<?= e((string) $category['id']) ?>">
                                            <button class="button danger compact" type="submit"><span class="mdi mdi-delete-outline" aria-hidden="true"></span><span>Видалити</span></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$categories): ?><tr><td colspan="4" class="empty-state">Категорії ще не додані.</td></tr><?php endif; ?>
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
        <form method="post" action="<?= url('/admin/news/categories/save') ?>" data-after-success-url="<?= url('/admin/news/categories') ?>">
            <?= \App\Core\Csrf::field() ?>
            <div class="form-grid">
                <label>Назва<input name="title" required></label>
                <label>Порядок<input type="number" name="sort_order" value="100"></label>
            </div>
            <div class="form-actions stacked">
                <button type="submit"><span class="mdi mdi-plus" aria-hidden="true"></span><span>Додати категорію</span></button>
            </div>
        </form>
    </aside>
</div>
