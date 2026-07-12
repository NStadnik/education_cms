<?php $formatNewsDate = static function (?string $value): string { $timestamp = strtotime((string) $value); return $timestamp !== false ? date('d.m.Y H:i', $timestamp) : (string) $value; }; ?>
<?php foreach ($items as $item): ?>
    <?php
        $statusLabels = ['draft' => 'Чернетка', 'pending_review' => 'Очікує модерації', 'changes_requested' => 'Доопрацювання', 'published' => 'Опубліковано'];
        $statusClasses = ['published' => 'ok', 'pending_review' => 'warn', 'changes_requested' => 'warn', 'draft' => ''];
        $itemStatus = (string) ($item['status'] ?? 'draft');
        $actionLabel = $itemStatus === 'pending_review' && ($canModerate ?? false) ? 'Перевірити' : ($itemStatus === 'changes_requested' ? 'Доопрацювати' : 'Відкрити');
    ?>
    <tr data-list-row>
        <td><input type="checkbox" name="ids[]" value="<?= e((string) $item['id']) ?>" data-bulk-check aria-label="Вибрати"></td>
        <td>
            <div class="news-list-title">
                <?php if (!empty($item['image_path'])): ?>
                    <img class="news-list-thumb" src="<?= url('/thumb/' . \App\Services\Files::normalize((string) $item['image_path']) . '?w=108&h=80&fit=crop') ?>" alt="" loading="lazy">
                <?php else: ?>
                    <span class="news-list-thumb news-list-thumb-placeholder mdi mdi-image-off-outline" aria-label="Без головного зображення" title="Без головного зображення"></span>
                <?php endif; ?>
                <div><strong><?= e($item['title']) ?></strong><div class="news-row-meta"><span class="mdi mdi-account-outline" aria-hidden="true"></span><span><?= e((string) ($item['author_name'] ?? 'Невідомий автор')) ?></span><?php if ($itemStatus === 'pending_review' && !empty($item['submitted_at'])): ?><span class="mdi mdi-clock-outline" aria-hidden="true"></span><span>надіслано <?= e($formatNewsDate((string) $item['submitted_at'])) ?></span><?php endif; ?></div><span class="meta"><?= e(excerpt($item['body'] ?? '', 100)) ?></span></div>
            </div>
        </td>
        <td><?= e($item['category_titles'] ?: ($item['category'] ?? 'Загальні')) ?></td>
        <td><span class="status <?= e($statusClasses[$itemStatus] ?? '') ?>"><?= e($statusLabels[$itemStatus] ?? $itemStatus) ?></span></td>
        <td><span class="mdi mdi-eye-outline" aria-hidden="true"></span> <?= e((string) ($item['views_count'] ?? 0)) ?></td>
        <td><?= e($formatNewsDate((string) ($item['published_at'] ?? ''))) ?></td>
        <td><?= e($formatNewsDate((string) ($item['updated_at'] ?? ''))) ?></td>
        <td>
            <div class="form-actions">
                <?php if ($itemStatus === 'published'): ?>
                    <a class="button secondary compact" href="<?= url('/news/' . ($item['slug'] ?? '')) ?>" target="_blank" rel="noopener"><span class="mdi mdi-eye-outline" aria-hidden="true"></span><span>Перегляд</span></a>
                <?php endif; ?>
                <a class="button secondary compact" href="<?= url('/admin/news/edit?id=' . $item['id']) ?>"><span class="mdi <?= $itemStatus === 'pending_review' && ($canModerate ?? false) ? 'mdi-clipboard-check-outline' : 'mdi-arrow-right' ?>" aria-hidden="true"></span><span><?= e($actionLabel) ?></span></a>
            </div>
        </td>
    </tr>
<?php endforeach; ?>
