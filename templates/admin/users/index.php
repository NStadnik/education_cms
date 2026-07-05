<h1>Користувачі</h1>
<div class="card">
    <form class="form-grid" method="post" action="<?= url('/admin/users/save') ?>">
        <?= \App\Core\Csrf::field() ?>
        <div class="grid grid-3">
            <label>Ім'я<input name="name" required></label>
            <label>Email<input type="email" name="email" required></label>
            <label>Пароль<input type="password" name="password" required></label>
        </div>
        <label>Роль<select name="role">
            <option value="admin">admin</option>
            <option value="editor">editor</option>
            <option value="publisher">publisher</option>
            <option value="finance_editor">finance_editor</option>
            <option value="viewer">viewer</option>
        </select></label>
        <button type="submit">Додати користувача</button>
    </form>
</div>
<div class="list-panel" data-infinite-list data-list-url="<?= url('/admin/users') ?>" data-list-target="#usersRows" data-list-offset="<?= e((string) count($items)) ?>" data-list-limit="<?= e((string) $limit) ?>" data-list-has-more="<?= count($items) < $total ? '1' : '0' ?>">
    <div class="list-tools">
        <input data-filter-input type="search" placeholder="Пошук користувачів" aria-label="Пошук користувачів">
        <span class="meta"><span data-filter-count><?= e((string) $total) ?></span> записів</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>Ім'я</th><th>Email</th><th>Роль</th><th>Активний</th></tr></thead>
            <tbody id="usersRows"><?= $this->partial('admin/users/rows', ['items' => $items]) ?></tbody>
        </table>
    </div>
    <div class="empty-state <?= $items ? 'd-none' : '' ?>" data-list-empty>Користувачі не знайдені.</div>
    <div class="list-sentinel" data-list-sentinel></div>
    <p class="meta list-status" data-list-status><?= count($items) < $total ? 'Прокрутіть нижче, щоб завантажити ще.' : 'Усі записи завантажено.' ?></p>
</div>
