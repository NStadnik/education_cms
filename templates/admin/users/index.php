<div class="page-head">
    <div>
        <p class="eyebrow">Доступ</p>
        <h1>Користувачі</h1>
        <p class="page-subtitle">Керуйте обліковими записами, ролями та доступом до адмінпанелі.</p>
    </div>
    <div class="form-actions">
        <a class="button secondary" href="<?= url('/admin/users/roles') ?>">
            <span class="mdi mdi-shield-account-outline" aria-hidden="true"></span>
            <span>Ролі</span>
        </a>
        <a class="button" href="<?= url('/admin/users/edit') ?>">
            <span class="mdi mdi-account-plus-outline" aria-hidden="true"></span>
            <span>Додати користувача</span>
        </a>
    </div>
</div>
<form method="post" action="<?= url('/admin/users/bulk') ?>" data-no-ajax>
<?= \App\Core\Csrf::field() ?>
<div class="list-panel" data-infinite-list data-list-url="<?= url('/admin/users') ?>" data-list-target="#usersRows" data-list-offset="<?= e((string) count($items)) ?>" data-list-limit="<?= e((string) $limit) ?>" data-list-has-more="<?= count($items) < $total ? '1' : '0' ?>">
    <div class="list-tools">
        <input data-filter-input type="search" placeholder="Пошук користувачів" aria-label="Пошук користувачів">
        <div class="bulk-actions">
            <select name="bulk_action" aria-label="Групова дія">
                <option value="">Групова дія</option>
                <option value="activate">Активувати</option>
                <option value="deactivate">Деактивувати</option>
                <option value="delete">Видалити</option>
            </select>
            <button class="button secondary compact" type="submit"><span class="mdi mdi-check" aria-hidden="true"></span><span>Застосувати</span></button>
            <span class="meta"><span data-filter-count><?= e((string) $total) ?></span> записів</span>
        </div>
    </div>
    <div class="table-scroll">
        <table>
            <thead><tr><th><input type="checkbox" data-bulk-check-all aria-label="Вибрати всі"></th><th>Ім'я</th><th>Email</th><th>Роль</th><th>Активний</th><th></th></tr></thead>
            <tbody id="usersRows"><?= $this->partial('admin/users/rows', ['items' => $items, 'roleLabels' => $roleLabels ?? []]) ?></tbody>
        </table>
    </div>
    <div class="empty-state <?= $items ? 'd-none' : '' ?>" data-list-empty>Користувачі не знайдені.</div>
    <div class="list-sentinel" data-list-sentinel></div>
    <p class="meta list-status" data-list-status><?= count($items) < $total ? 'Прокрутіть нижче, щоб завантажити ще.' : 'Усі записи завантажено.' ?></p>
</div>
</form>
