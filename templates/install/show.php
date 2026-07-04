<div class="row justify-content-center">
    <div class="col-12 col-lg-9 col-xl-8">
        <div class="card bootstrap-card shadow-sm">
            <div class="card-header bg-white">
                <h1 class="h4 mb-0">Встановлення сайту закладу освіти</h1>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?><div class="alert alert-warning"><?= e($error) ?></div><?php endif; ?>

                <h2 class="h5">Перевірка середовища</h2>
                <div class="table-responsive mb-4">
                    <table class="table table-sm align-middle">
                        <?php foreach ($requirements as $name => $ok): ?>
                            <tr>
                                <td><?= e($name) ?></td>
                                <td><span class="badge <?= $ok ? 'text-bg-success' : 'text-bg-warning' ?>"><?= $ok ? 'OK' : 'Потрібно виправити' ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

                <form method="post" action="<?= url('/install') ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="driver" value="mysql">
                    <p class="text-muted small">Підтримується MySQL / MariaDB.</p>

                    <h2 class="h5 mt-4">База даних</h2>
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label">Host БД</label>
                            <input class="form-control" name="db_host" value="<?= e($old['db_host'] ?? '127.0.0.1') ?>">
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label">Port</label>
                            <input class="form-control" name="db_port" value="<?= e($old['db_port'] ?? '3306') ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Назва БД</label>
                            <input class="form-control" name="db_name" value="<?= e($old['db_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Користувач БД</label>
                            <input class="form-control" name="db_user" value="<?= e($old['db_user'] ?? '') ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Пароль БД</label>
                            <input class="form-control" name="db_password" type="password">
                        </div>
                    </div>

                    <h2 class="h5 mt-4">Сайт</h2>
                    <div class="mb-3">
                        <label class="form-label">Назва закладу</label>
                        <input class="form-control" name="institution_name" value="<?= e($old['institution_name'] ?? '') ?>" required>
                    </div>

                    <h2 class="h5 mt-4">Перший адміністратор</h2>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Email</label>
                            <input class="form-control" name="admin_email" type="email" value="<?= e($old['admin_email'] ?? '') ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Пароль</label>
                            <input class="form-control" name="admin_password" type="password" required>
                        </div>
                    </div>

                    <button class="btn btn-primary mt-4" type="submit">Встановити</button>
                </form>
            </div>
        </div>
    </div>
</div>
