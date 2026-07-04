<div class="card">
    <h1>Встановлення сайту закладу освіти</h1>
    <?php if (!empty($error)): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>
    <h2>Перевірка середовища</h2>
    <table>
        <?php foreach ($requirements as $name => $ok): ?>
            <tr>
                <td><?= e($name) ?></td>
                <td><span class="status <?= $ok ? 'ok' : 'warn' ?>"><?= $ok ? 'OK' : 'Потрібно виправити' ?></span></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <form class="form-grid" method="post" action="<?= url('/install') ?>" style="margin-top:24px">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="driver" value="mysql">
        <p class="meta">Підтримується MySQL / MariaDB.</p>
        <div class="grid grid-3">
            <label>Host БД<input name="db_host" value="<?= e($old['db_host'] ?? '127.0.0.1') ?>"></label>
            <label>Port БД<input name="db_port" value="<?= e($old['db_port'] ?? '3306') ?>"></label>
            <label>Назва БД<input name="db_name" value="<?= e($old['db_name'] ?? '') ?>" required></label>
        </div>
        <div class="grid grid-3">
            <label>Користувач БД<input name="db_user" value="<?= e($old['db_user'] ?? '') ?>" required></label>
            <label>Пароль БД<input name="db_password" type="password"></label>
        </div>
        <label>Назва закладу<input name="institution_name" value="<?= e($old['institution_name'] ?? '') ?>" required></label>
        <h2>Перший адміністратор</h2>
        <div class="grid grid-3">
            <label>Email<input name="admin_email" type="email" value="<?= e($old['admin_email'] ?? '') ?>" required></label>
            <label>Пароль<input name="admin_password" type="password" required></label>
        </div>
        <button type="submit">Встановити</button>
    </form>
</div>
