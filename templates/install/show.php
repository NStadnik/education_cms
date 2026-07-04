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
        <?= App\Core\Csrf::field() ?>
        <label>Тип бази
            <select name="driver">
                <option value="sqlite">SQLite для демо або простого хостингу</option>
                <option value="mysql">MySQL / MariaDB</option>
            </select>
        </label>
        <div class="grid grid-3">
            <label>Host БД<input name="db_host" value="127.0.0.1"></label>
            <label>Port БД<input name="db_port" value="3306"></label>
            <label>Назва БД<input name="db_name"></label>
        </div>
        <div class="grid grid-3">
            <label>Користувач БД<input name="db_user"></label>
            <label>Пароль БД<input name="db_password" type="password"></label>
            <label>Тема<select name="theme"><option value="official">Official</option></select></label>
        </div>
        <label>Назва закладу<input name="institution_name" required></label>
        <div class="grid grid-3">
            <label>Тип закладу<input name="institution_type" placeholder="Ліцей, гімназія, ЗДО"></label>
            <label>ЄДРПОУ<input name="edrpou"></label>
            <label>Email закладу<input name="institution_email" type="email"></label>
        </div>
        <label>Адреса<input name="address"></label>
        <label>Телефон<input name="phone"></label>
        <h2>Перший адміністратор</h2>
        <div class="grid grid-3">
            <label>Ім'я<input name="admin_name" required></label>
            <label>Email<input name="admin_email" type="email" required></label>
            <label>Пароль<input name="admin_password" type="password" required></label>
        </div>
        <button type="submit">Встановити</button>
    </form>
</div>
