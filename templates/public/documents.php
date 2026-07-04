<section class="section">
    <div class="container">
        <h1>Документи</h1>
        <table>
            <tr><th>Назва</th><th>Категорія</th><th>Дата</th><th>Файл</th></tr>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['title']) ?><br><span class="meta"><?= e($item['description'] ?? '') ?></span></td>
                    <td><?= e($item['category']) ?></td>
                    <td><?= e($item['approved_at'] ?? $item['created_at']) ?></td>
                    <td><?php if ($item['file_path']): ?><a href="<?= url('/uploads/' . $item['file_path']) ?>">Відкрити</a><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php if (!$items): ?><p>Документи ще не додані.</p><?php endif; ?>
    </div>
</section>
