<?php
$fields = $item ? (json_decode((string) $item['fields_json'], true) ?: []) : [
    ['id'=>'name','type'=>'text','label'=>'Ім’я','required'=>true,'options'=>[]],
    ['id'=>'email','type'=>'email','label'=>'Email','required'=>true,'options'=>[]],
];
$settings = $item ? (json_decode((string) $item['settings_json'], true) ?: []) : [];
?>
<div class="page-head forms-page-head">
    <div><p class="eyebrow">Конструктор</p><h1><?= e($title) ?></h1><p class="page-subtitle">Налаштуйте поля, повідомлення та публікацію без редагування коду.</p></div>
    <div class="form-actions"><a class="button secondary" href="<?= url('/admin/forms') ?>"><span class="mdi mdi-arrow-left"></span><span>До списку</span></a><?php if (!empty($item['id'])): ?><a class="button secondary" href="<?= url('/admin/forms/submissions?form_id='.$item['id']) ?>"><span class="mdi mdi-inbox-outline"></span><span>Відповіді</span></a><?php endif; ?></div>
</div>
<form class="forms-editor" method="post" action="<?= url('/admin/forms/save') ?>" data-no-ajax data-form-builder>
<?= \App\Core\Csrf::field() ?><input type="hidden" name="id" value="<?= e((string) ($item['id'] ?? 0)) ?>">
<input type="hidden" name="fields_json" value="<?= e(json_encode($fields, JSON_UNESCAPED_UNICODE) ?: '[]') ?>" data-form-fields-json>
<div class="forms-editor-layout">
    <main class="forms-editor-main">
        <section class="card form-editor-section">
            <div class="form-section-head"><div><p class="eyebrow">Основне</p><h2>Про форму</h2><p class="meta">Цю інформацію побачить відвідувач над полями.</p></div></div>
            <div class="form-grid forms-basics"><label>Назва форми<input name="title" required value="<?= e($item['title'] ?? '') ?>" placeholder="Наприклад, Реєстрація на подію"></label><label>Опис<textarea name="description" rows="3" placeholder="Коротко поясніть мету форми"><?= e($item['description'] ?? '') ?></textarea></label></div>
        </section>
        <section class="card form-editor-section">
            <div class="form-builder-head"><div><p class="eyebrow">Поля</p><h2>Конструктор форми</h2><p class="meta"><strong data-form-field-count><?= count($fields) ?></strong> полів · перетягуйте картки для зміни порядку.</p></div><button class="button" type="button" data-form-add-field><span class="mdi mdi-plus"></span><span>Додати поле</span></button></div>
            <div class="form-field-list" data-form-field-list></div>
            <button class="form-add-field-empty" type="button" data-form-add-field><span class="mdi mdi-form-textbox"></span><strong>Додати перше поле</strong><small>Текст, email, вибір зі списку та інші типи</small></button>
        </section>
    </main>
    <aside class="forms-editor-sidebar">
        <section class="card form-editor-section"><div class="form-section-head"><div><p class="eyebrow">Публікація</p><h2>Стан форми</h2></div></div><div class="form-grid">
            <label>Тип<select name="type"><?php foreach (['generic'=>'Звичайна','survey'=>'Опитування','registration'=>'Реєстрація','feedback'=>'Зворотний зв’язок'] as $v=>$l): ?><option value="<?= e($v) ?>" <?= selected($item['type'] ?? 'generic',$v) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
            <label>Статус<select name="status"><option value="draft" <?= selected($item['status'] ?? 'draft','draft') ?>>Чернетка</option><option value="published" <?= selected($item['status'] ?? '','published') ?>>Опубліковано</option></select></label>
            <label>Slug <small>необов’язково</small><input name="slug" value="<?= e($item['slug'] ?? '') ?>" placeholder="registration"></label>
        </div></section>
        <section class="card form-editor-section"><div class="form-section-head"><div><p class="eyebrow">Після надсилання</p><h2>Підтвердження</h2></div></div><div class="form-grid"><label>Текст кнопки<input name="submit_label" value="<?= e($settings['submit_label'] ?? 'Надіслати') ?>"></label><label>Повідомлення користувачу<textarea name="success_message" rows="4"><?= e($settings['success_message'] ?? 'Дякуємо! Відповідь надіслано.') ?></textarea></label></div></section>
        <div class="forms-save-bar"><button type="submit"><span class="mdi mdi-content-save-outline"></span><span>Зберегти форму</span></button><span class="meta" data-form-save-hint>Зміни ще не збережені</span></div>
    </aside>
</div></form>
<?php if (!empty($item['id'])): ?><details class="card forms-danger-zone"><summary>Небезпечна зона</summary><div><p>Видалення форми також видалить усі надіслані відповіді.</p><form method="post" action="<?= url('/admin/forms/delete') ?>" data-no-ajax data-delete-confirm="Видалити форму та всі відповіді?"><?= \App\Core\Csrf::field() ?><input type="hidden" name="id" value="<?= e((string) $item['id']) ?>"><button class="button danger" type="submit"><span class="mdi mdi-delete-outline"></span><span>Видалити форму</span></button></form></div></details><?php endif; ?>

<template data-form-field-template><article class="form-field-card" draggable="true" data-form-field><div class="form-field-card-head"><span class="form-field-drag mdi mdi-drag" title="Перетягнути"></span><span class="form-field-type-icon mdi mdi-form-textbox"></span><div><strong data-field-summary>Нове поле</strong><small data-field-type-label>Текстове поле</small></div><label class="form-required-toggle"><input type="checkbox" data-field-required><span>Обов’язкове</span></label><button class="button secondary compact" type="button" data-field-collapse aria-label="Згорнути"><span class="mdi mdi-chevron-up"></span></button><button class="button danger compact" type="button" data-field-remove aria-label="Видалити"><span class="mdi mdi-delete-outline"></span></button></div><div class="form-field-card-body"><div class="form-field-grid"><label>Назва поля<input data-field-label placeholder="Наприклад, Ваше ім’я"></label><label>Тип<select data-field-type><option value="text">Короткий текст</option><option value="textarea">Довгий текст</option><option value="email">Email</option><option value="tel">Телефон</option><option value="number">Число</option><option value="date">Дата</option><option value="select">Випадний список</option><option value="radio">Один варіант</option><option value="checkbox">Прапорець</option><option value="consent">Згода</option></select></label><label class="form-field-options" data-field-options-wrap>Варіанти відповіді<textarea data-field-options rows="3" placeholder="Кожен варіант з нового рядка"></textarea><small>Один варіант на рядок.</small></label></div></div></article></template>
