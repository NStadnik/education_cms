<div class="toolbar">
    <h1>Сторінки</h1>
    <a class="button" href="<?= url('/admin/pages/edit') ?>">Додати</a>
</div>
<table>
    <tr><th>Назва</th><th>Slug</th><th>Статус</th><th></th></tr>
    <?php foreach ($items as $item): ?>
        <tr>
            <td><?= e($item['title']) ?></td>
            <td><?= e($item['slug']) ?></td>
            <td><span class="status"><?= e($item['status']) ?></span></td>
            <td><a href="<?= url('/admin/pages/edit?id=' . $item['id']) ?>">Редагувати</a></td>
        </tr>
    <?php endforeach; ?>
</table>
