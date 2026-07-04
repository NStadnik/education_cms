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
<table style="margin-top:16px">
    <tr><th>Ім'я</th><th>Email</th><th>Роль</th><th>Активний</th></tr>
    <?php foreach ($items as $item): ?>
        <tr><td><?= e($item['name']) ?></td><td><?= e($item['email']) ?></td><td><?= e($item['role']) ?></td><td><?= $item['is_active'] ? 'так' : 'ні' ?></td></tr>
    <?php endforeach; ?>
</table>
