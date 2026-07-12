<?php
    $type = in_array($type ?? '', ['pages', 'news', 'categories', 'media'], true) ? $type : 'pages';
    $excerpt = static function (string $value, int $limit = 150): string {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');
        if ($value === '') return '';
        if (function_exists('mb_strlen') && mb_strlen($value) > $limit) return rtrim(mb_substr($value, 0, $limit - 1)) . '…';
        return strlen($value) > $limit ? rtrim(substr($value, 0, $limit - 1)) . '…' : $value;
    };
    $config = [
        'pages' => ['icon' => 'mdi-file-document-outline', 'url' => static fn ($item) => ((string) ($item['slug'] ?? '') === 'home') ? url('/') : url('/page/' . $item['slug']), 'meta' => static fn ($item) => 'Сторінка', 'description' => static fn ($item) => $excerpt((string) ($item['excerpt'] ?? ''))],
        'news' => ['icon' => 'mdi-newspaper-variant-outline', 'url' => static fn ($item) => url('/news/' . $item['slug']), 'meta' => static fn ($item) => !empty($item['published_at']) ? 'Новина · ' . date('d.m.Y', strtotime((string) $item['published_at'])) : 'Новина', 'description' => static fn ($item) => $excerpt((string) ($item['body'] ?? ''))],
        'categories' => ['icon' => 'mdi-shape-outline', 'url' => static fn ($item) => url('/news?category=' . rawurlencode((string) $item['title'])), 'meta' => static fn ($item) => ($item['items_count'] ?? 0) . ' новин', 'description' => static fn ($item) => 'Переглянути всі новини цієї категорії'],
        'media' => ['icon' => 'mdi-file-image-outline', 'url' => static fn ($item) => url('/uploads/' . $item['path']), 'meta' => static fn ($item) => trim(($item['type'] ?? 'Файл') . ' · ' . ($item['size_label'] ?? ''), ' ·'), 'description' => static fn ($item) => $excerpt((string) ($item['caption'] ?? $item['description'] ?? $item['path'] ?? ''))],
    ][$type];
?>
<?php foreach (($items ?? []) as $item): ?>
    <?php $isMediaImage = $type === 'media' && !empty($item['is_image']); ?>
    <?php $isNewsImage = $type === 'news' && !empty($item['image_path']); ?>
    <?php $hasPreview = $isMediaImage || $isNewsImage; ?>
    <a class="public-search-result<?= $hasPreview ? ' public-search-result-media' : '' ?>" href="<?= e($config['url']($item)) ?>" data-search-item-type="<?= e($type) ?>"<?= $type === 'media' ? ' data-search-media-preview data-media-title="' . e((string) ($item['title'] ?? $item['name'] ?? $item['path'] ?? 'Медіафайл')) . '" data-media-type="' . e((string) ($item['type'] ?? 'Файл')) . '" data-media-extension="' . e((string) ($item['extension'] ?? '')) . '" data-media-size="' . e((string) ($item['size_label'] ?? '')) . '"' : '' ?>>
        <?php if ($hasPreview): ?>
            <?php $previewPath = $isNewsImage ? (string) $item['image_path'] : (string) $item['path']; ?>
            <span class="public-search-result-preview"><img src="<?= url('/thumb/' . $previewPath . '?w=240&h=180&fit=crop') ?>" alt="<?= $isNewsImage ? e((string) ($item['title'] ?? '')) : '' ?>"></span>
        <?php else: ?>
            <span class="public-search-result-icon mdi <?= e($config['icon']) ?>" aria-hidden="true"></span>
        <?php endif; ?>
        <span class="public-search-result-body">
            <small><?= e((string) $config['meta']($item)) ?></small>
            <strong><?= e((string) ($item['title'] ?? $item['name'] ?? $item['path'] ?? 'Без назви')) ?></strong>
            <?php $description = (string) $config['description']($item); ?>
            <?php if ($description !== ''): ?><span><?= e($description) ?></span><?php endif; ?>
        </span>
        <span class="mdi mdi-arrow-right public-search-result-arrow" aria-hidden="true"></span>
    </a>
<?php endforeach; ?>
