<div class="card admin-login-card">
    <div class="admin-login-head">
        <h1><img src="<?= url('/assets/images/education_cms_logo_for_white.png') ?>" alt="Education CMS" class="admin-logo"></h1>
        <p>Вхід в адмінку</p>
    </div>
    <?php if (!empty($error)): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>
    <form class="form-grid" method="post" action="<?= url('/admin/login') ?>">
        <?= \App\Core\Csrf::field() ?>
        <label>Email<input type="email" name="email" required></label>
        <label>Пароль<input type="password" name="password" required></label>
        <button type="submit">Увійти</button>
    </form>
    <a class="admin-login-brand" href="https://lcloud.in.ua" target="_blank" >
        Навчальна хмара «ЛКЛАУД»
    </a>
</div>
