<div class="page-head">
    <div>
        <p class="eyebrow">Доступ</p>
        <h1>Користувачі</h1>
        <p class="page-subtitle">Керуйте обліковими записами, ролями та доступом до адмінпанелі.</p>
    </div>
    <a class="button" href="<?= url('/admin/users/edit') ?>">
        <span class="mdi mdi-account-plus-outline" aria-hidden="true"></span>
        <span>Додати користувача</span>
    </a>
</div>
<div class="list-panel" data-infinite-list data-list-url="<?= url('/admin/users') ?>" data-list-target="#usersRows" data-list-offset="<?= e((string) count($items)) ?>" data-list-limit="<?= e((string) $limit) ?>" data-list-has-more="<?= count($items) < $total ? '1' : '0' ?>">
    <div class="list-tools">
        <input data-filter-input type="search" placeholder="Пошук користувачів" aria-label="Пошук користувачів">
        <span class="meta"><span data-filter-count><?= e((string) $total) ?></span> записів</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>Ім'я</th><th>Email</th><th>Роль</th><th>Активний</th><th></th></tr></thead>
            <tbody id="usersRows"><?= $this->partial('admin/users/rows', ['items' => $items]) ?></tbody>
        </table>
    </div>
    <div class="empty-state <?= $items ? 'd-none' : '' ?>" data-list-empty>Користувачі не знайдені.</div>
    <div class="list-sentinel" data-list-sentinel></div>
    <p class="meta list-status" data-list-status><?= count($items) < $total ? 'Прокрутіть нижче, щоб завантажити ще.' : 'Усі записи завантажено.' ?></p>
</div>
