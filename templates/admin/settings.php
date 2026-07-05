<div class="page-head">
    <div>
        <p class="eyebrow">Конфігурація</p>
        <h1>Налаштування закладу</h1>
    </div>
</div>
<form class="form-grid wide" method="post" action="<?= url('/admin/settings/save') ?>">
    <?= \App\Core\Csrf::field() ?>
    <section class="card admin-form-card">
        <div class="form-section-head">
            <div>
                <h2>Дані закладу</h2>
                <p class="meta">Ці дані використовуються в шапці, підвалі та публічних сторінках сайту.</p>
            </div>
        </div>
        <label>Назва<input name="institution_name" value="<?= e($settings['institution_name'] ?? '') ?>"></label>
        <div class="grid grid-3">
            <label>Тип<input name="institution_type" value="<?= e($settings['institution_type'] ?? '') ?>"></label>
            <label>ЄДРПОУ<input name="edrpou" value="<?= e($settings['edrpou'] ?? '') ?>"></label>
            <label>Email<input name="email" value="<?= e($settings['email'] ?? '') ?>"></label>
        </div>
        <label>Адреса<input name="address" value="<?= e($settings['address'] ?? '') ?>"></label>
        <label>Телефон<input name="phone" value="<?= e($settings['phone'] ?? '') ?>"></label>
    </section>

    <button type="submit"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span><span>Зберегти</span></button>
</form>
