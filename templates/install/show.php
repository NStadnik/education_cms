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
        <label>Тип бази
            <select name="driver">
                <option value="mysql" <?= selected($old['driver'] ?? 'mysql', 'mysql') ?>>MySQL / MariaDB для хостингу</option>
                <option value="sqlite" <?= selected($old['driver'] ?? 'mysql', 'sqlite') ?>>SQLite тільки якщо доступний pdo_sqlite</option>
            </select>
        </label>
        <div class="grid grid-3">
            <label>Host БД<input name="db_host" value="<?= e($old['db_host'] ?? '127.0.0.1') ?>"></label>
            <label>Port БД<input name="db_port" value="<?= e($old['db_port'] ?? '3306') ?>"></label>
            <label>Назва БД<input name="db_name" value="<?= e($old['db_name'] ?? '') ?>"></label>
        </div>
        <div class="grid grid-3">
            <label>Користувач БД<input name="db_user" value="<?= e($old['db_user'] ?? '') ?>"></label>
            <label>Пароль БД<input name="db_password" type="password"></label>
            <label>Тема<select name="theme"><option value="official">Official</option></select></label>
        </div>
        <label>Назва закладу<input name="institution_name" value="<?= e($old['institution_name'] ?? '') ?>" required></label>
        <div class="grid grid-3">
            <label>Тип закладу<input name="institution_type" value="<?= e($old['institution_type'] ?? '') ?>" placeholder="Ліцей, гімназія, ЗДО"></label>
            <label>ЄДРПОУ<input name="edrpou" value="<?= e($old['edrpou'] ?? '') ?>"></label>
            <label>Email закладу<input name="institution_email" type="email" value="<?= e($old['institution_email'] ?? '') ?>"></label>
        </div>
        <label>Адреса<input name="address" value="<?= e($old['address'] ?? '') ?>"></label>
        <label>Телефон<input name="phone" value="<?= e($old['phone'] ?? '') ?>"></label>
        <h2>Перший адміністратор</h2>
        <div class="grid grid-3">
            <label>Ім'я<input name="admin_name" value="<?= e($old['admin_name'] ?? '') ?>" required></label>
            <label>Email<input name="admin_email" type="email" value="<?= e($old['admin_email'] ?? '') ?>" required></label>
            <label>Пароль<input name="admin_password" type="password" required></label>
        </div>
        <button type="submit">Встановити</button>
    </form>
</div>
