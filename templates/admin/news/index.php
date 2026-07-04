<div class="toolbar">
    <h1>Новини</h1>
    <a class="button" href="<?= url('/admin/news/edit') ?>">Додати</a>
</div>
<table>
    <tr><th>Назва</th><th>Статус</th><th>Дата</th><th></th></tr>
    <?php foreach ($items as $item): ?>
        <tr>
            <td><?= e($item['title']) ?></td>
            <td><span class="status"><?= e($item['status']) ?></span></td>
            <td><?= e($item['published_at'] ?? '') ?></td>
            <td><a href="<?= url('/admin/news/edit?id=' . $item['id']) ?>">Редагувати</a></td>
        </tr>
    <?php endforeach; ?>
</table>
