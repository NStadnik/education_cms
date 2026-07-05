<section class="section">
    <div class="container">
        <div class="page-head public-head">
            <div>
                <p class="eyebrow">Офіційні матеріали</p>
                <h1>Документи</h1>
                <p class="page-subtitle">Опубліковані документи, положення, накази та матеріали для завантаження.</p>
            </div>
            <span class="status"><?= e((string) count($items)) ?> документів</span>
        </div>
        <div class="table-scroll">
            <table>
                <tr><th>Назва</th><th>Категорія</th><th>Дата</th><th>Файл</th></tr>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><strong><?= e($item['title']) ?></strong><br><span class="meta"><?= e($item['description'] ?? '') ?></span></td>
                        <td><?= e($item['category']) ?></td>
                        <td><?= e($item['approved_at'] ?? $item['created_at']) ?></td>
                        <td><?php if ($item['file_path']): ?><a class="button secondary compact" href="<?= url('/uploads/' . $item['file_path']) ?>">Відкрити</a><?php else: ?><span class="meta">немає</span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php if (!$items): ?><div class="empty-state">Документи ще не додані.</div><?php endif; ?>
    </div>
</section>
