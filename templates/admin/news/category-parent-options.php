<option value="">Без батьківської</option>
<?php foreach (($parentOptions ?? []) as $category): ?>
    <option value="<?= e((string) $category['id']) ?>"><?= e($category['label'] ?? $category['category']) ?></option>
<?php endforeach; ?>
