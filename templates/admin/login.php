<div class="card" style="max-width:460px;margin:40px auto">
    <h1>Вхід в адмінку</h1>
    <?php if (!empty($error)): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>
    <form class="form-grid" method="post" action="<?= url('/admin/login') ?>">
        <?= \App\Core\Csrf::field() ?>
        <label>Email<input type="email" name="email" required></label>
        <label>Пароль<input type="password" name="password" required></label>
        <button type="submit">Увійти</button>
    </form>
</div>
