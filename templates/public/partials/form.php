<?php $fields = json_decode((string) $form['fields_json'], true) ?: []; $formSettings = json_decode((string) $form['settings_json'], true) ?: []; ?>
<article class="card content-card public-form-card">
    <h3><?= e($form['title']) ?></h3>
    <?php if (!empty($form['description'])): ?><p><?= e($form['description']) ?></p><?php endif; ?>
    <form method="post" action="<?= url('/forms/' . $form['id'] . '/submit') ?>" data-public-form>
        <?= \App\Core\Csrf::field() ?><input type="hidden" name="_page_url" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
        <label class="form-honeypot" aria-hidden="true">Website<input name="_website" tabindex="-1" autocomplete="off"></label>
        <div class="form-grid">
        <?php foreach ($fields as $field): $name='field_'.($field['id']??''); $type=$field['type']??'text'; $required=!empty($field['required']); ?>
            <label><?= e($field['label'] ?? '') ?><?= $required ? ' *' : '' ?>
            <?php if ($type === 'textarea'): ?><textarea name="<?= e($name) ?>" <?= $required?'required':'' ?>></textarea>
            <?php elseif (in_array($type,['select','radio'],true)): ?>
                <?php if ($type === 'select'): ?><select name="<?= e($name) ?>" <?= $required?'required':'' ?>><option value="">Оберіть…</option><?php foreach(($field['options']??[]) as $option): ?><option value="<?= e($option['value']) ?>"><?= e($option['label']) ?></option><?php endforeach; ?></select>
                <?php else: ?><span class="form-options"><?php foreach(($field['options']??[]) as $option): ?><label><input type="radio" name="<?= e($name) ?>" value="<?= e($option['value']) ?>" <?= $required?'required':'' ?>> <?= e($option['label']) ?></label><?php endforeach; ?></span><?php endif; ?>
            <?php elseif (in_array($type,['checkbox','consent'],true)): ?><span><input type="checkbox" name="<?= e($name) ?>" value="1" <?= $required?'required':'' ?>> <?= e($field['label'] ?? '') ?></span>
            <?php else: ?><input type="<?= e(in_array($type,['email','tel','number','date'],true)?$type:'text') ?>" name="<?= e($name) ?>" <?= $required?'required':'' ?>><?php endif; ?>
            <small data-form-error="<?= e((string) ($field['id'] ?? '')) ?>"></small></label>
        <?php endforeach; ?>
        </div><button type="submit"><?= e($formSettings['submit_label'] ?? 'Надіслати') ?></button><p class="form-submit-status" data-form-status aria-live="polite"></p>
    </form>
</article>
