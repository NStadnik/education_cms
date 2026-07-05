<?php $isEdit = !empty($item['id']); ?>
<div class="page-head">
    <div>
        <p class="eyebrow">Доступ</p>
        <h1><?= $isEdit ? 'Редагувати користувача' : 'Новий користувач' ?></h1>
        <p class="page-subtitle">Налаштуйте обліковий запис, роль і активність користувача.</p>
    </div>
    <a class="button secondary" href="<?= url('/admin/users') ?>">До списку</a>
</div>

<form method="post" action="<?= url('/admin/users/save') ?>">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= e((string) ($item['id'] ?? '')) ?>">

    <div class="editor-layout">
        <section class="card admin-form-card">
            <div class="form-section-head">
                <div>
                    <h2>Профіль</h2>
                    <p class="meta">Email використовується для входу в адмінпанель.</p>
                </div>
            </div>
            <div class="form-grid wide">
                <label>Ім'я<input name="name" value="<?= e($item['name'] ?? '') ?>" required></label>
                <label>Email<input type="email" name="email" value="<?= e($item['email'] ?? '') ?>" required></label>
                <label>Пароль<input type="password" name="password" <?= $isEdit ? '' : 'required' ?>></label>
                <?php if ($isEdit): ?><div class="hint-box">Залиште пароль порожнім, щоб не змінювати його.</div><?php endif; ?>
            </div>
        </section>

        <aside class="card admin-form-card editor-sidebar">
            <div class="form-section-head">
                <div>
                    <h2>Права</h2>
                    <p class="meta">Роль визначає доступні розділи адмінпанелі.</p>
                </div>
            </div>
            <div class="form-grid">
                <label>Роль
                    <select name="role">
                        <?php foreach (['admin', 'editor', 'publisher', 'finance_editor', 'viewer'] as $role): ?>
                            <option value="<?= e($role) ?>" <?= selected($item['role'] ?? 'editor', $role) ?>><?= e($role) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="check-row"><input type="checkbox" name="is_active" value="1" <?= checked($item['is_active'] ?? 1) ?>> Активний</label>
            </div>
            <div class="form-actions stacked">
                <button type="submit">Зберегти користувача</button>
                <a class="button secondary" href="<?= url('/admin/users') ?>">Скасувати</a>
            </div>
        </aside>
    </div>
</form>
