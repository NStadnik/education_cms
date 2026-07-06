<?php
    $layout = array_replace([
        'variant' => 'default',
        'columns' => [],
        'bottom_text' => '',
    ], is_array($footerLayout ?? null) ? $footerLayout : []);
    $variant = preg_replace('/[^a-z0-9_-]/i', '', (string) $layout['variant']) ?: 'default';
    $columns = is_array($layout['columns'] ?? null) ? $layout['columns'] : [];
?>
<footer class="footer site-footer site-footer-<?= e($variant) ?>">
    <div class="container">
        <?php if ($columns): ?>
            <div class="row g-4 site-footer-grid">
                <?php foreach ($columns as $column): ?>
                    <?php $column = is_array($column) ? $column : []; ?>
                    <div class="col-md">
                        <section class="site-footer-card">
                            <?php if (!empty($column['title'])): ?><h2><?= e((string) $column['title']) ?></h2><?php endif; ?>
                            <?php foreach (($column['items'] ?? []) as $item): ?>
                                <?php $item = is_array($item) ? $item : []; ?>
                                <p>
                                    <?php if (!empty($item['url']) && !empty($item['label'])): ?>
                                        <a href="<?= e((string) $item['url']) ?>"><?= e((string) $item['label']) ?></a>
                                    <?php elseif (!empty($item['label'])): ?>
                                        <strong><?= e((string) $item['label']) ?></strong>
                                    <?php endif; ?>
                                    <?php if (!empty($item['text'])): ?>
                                        <span><?= e((string) $item['text']) ?></span>
                                    <?php endif; ?>
                                </p>
                            <?php endforeach; ?>
                        </section>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <strong><?= e($settings['institution_name'] ?? 'Заклад освіти') ?></strong><br>
            <?php foreach (($globalFields ?? []) as $field): ?>
                <?php
                    $field = is_array($field) ? $field : [];
                    $fieldLabel = (string) ($field['label'] ?? 'Поле');
                    $fieldValue = trim((string) ($field['value'] ?? ''));
                ?>
                <?php if ($fieldValue !== ''): ?>
                    <span><?= e($fieldLabel) ?>: <?= e($fieldValue) ?></span><br>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($layout['bottom_text'])): ?>
            <div class="site-footer-bottom"><?= e((string) $layout['bottom_text']) ?></div>
        <?php endif; ?>
    </div>
</footer>
