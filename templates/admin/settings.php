<h1>Налаштування закладу</h1>
<form class="form-grid" method="post" action="<?= url('/admin/settings/save') ?>">
    <?= App\Core\Csrf::field() ?>
    <label>Назва<input name="institution_name" value="<?= e($settings['institution_name'] ?? '') ?>"></label>
    <div class="grid grid-3">
        <label>Тип<input name="institution_type" value="<?= e($settings['institution_type'] ?? '') ?>"></label>
        <label>ЄДРПОУ<input name="edrpou" value="<?= e($settings['edrpou'] ?? '') ?>"></label>
        <label>Email<input name="email" value="<?= e($settings['email'] ?? '') ?>"></label>
    </div>
    <label>Адреса<input name="address" value="<?= e($settings['address'] ?? '') ?>"></label>
    <label>Телефон<input name="phone" value="<?= e($settings['phone'] ?? '') ?>"></label>
    <button type="submit">Зберегти</button>
</form>
