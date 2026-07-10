<?php $fields = $item ? (json_decode((string) $item['fields_json'], true) ?: []) : [['id'=>'name','type'=>'text','label'=>'Ім’я','required'=>true],['id'=>'email','type'=>'email','label'=>'Email','required'=>true]]; $settings = $item ? (json_decode((string) $item['settings_json'], true) ?: []) : []; ?>
<div class="page-head"><div><p class="eyebrow">Конструктор</p><h1><?= e($title) ?></h1></div><a class="button secondary" href="<?= url('/admin/forms') ?>">До списку</a></div>
<form method="post" action="<?= url('/admin/forms/save') ?>" data-no-ajax>
<?= \App\Core\Csrf::field() ?><input type="hidden" name="id" value="<?= e((string) ($item['id'] ?? 0)) ?>">
<div class="editor-layout"><section class="card admin-form-card"><div class="form-grid">
<label>Назва<input name="title" required value="<?= e($item['title'] ?? '') ?>"></label>
<label>Slug<input name="slug" value="<?= e($item['slug'] ?? '') ?>" placeholder="registration"></label>
<label>Опис<textarea name="description" rows="3"><?= e($item['description'] ?? '') ?></textarea></label>
<label>Поля у JSON<textarea class="textarea-large" name="fields_json" rows="18" required><?= e(json_encode($fields, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) ?: '[]') ?></textarea></label>
<p class="meta">Типи: text, textarea, email, tel, number, date, select, radio, checkbox, consent. Для select/radio додайте options: [{"value":"yes","label":"Так"}].</p>
</div></section><aside class="card admin-form-card"><div class="form-grid">
<label>Тип<select name="type"><?php foreach (['generic'=>'Звичайна','survey'=>'Опитування','registration'=>'Реєстрація','feedback'=>'Зворотний зв’язок'] as $v=>$l): ?><option value="<?= e($v) ?>" <?= selected($item['type'] ?? 'generic',$v) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
<label>Статус<select name="status"><option value="draft" <?= selected($item['status'] ?? 'draft','draft') ?>>Чернетка</option><option value="published" <?= selected($item['status'] ?? '','published') ?>>Опубліковано</option></select></label>
<label>Текст кнопки<input name="submit_label" value="<?= e($settings['submit_label'] ?? 'Надіслати') ?>"></label>
<label>Повідомлення після надсилання<textarea name="success_message" rows="4"><?= e($settings['success_message'] ?? 'Дякуємо! Відповідь надіслано.') ?></textarea></label>
<button type="submit"><span class="mdi mdi-content-save-outline"></span><span>Зберегти</span></button>
</div></aside></div></form>
<?php if (!empty($item['id'])): ?><form method="post" action="<?= url('/admin/forms/delete') ?>" data-no-ajax data-delete-confirm="Видалити форму та всі відповіді?"><?= \App\Core\Csrf::field() ?><input type="hidden" name="id" value="<?= e((string) $item['id']) ?>"><button class="button danger" type="submit">Видалити форму</button></form><?php endif; ?>
