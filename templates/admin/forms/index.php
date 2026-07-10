<div class="page-head">
    <div><p class="eyebrow">Збір даних</p><h1>Форми</h1><p class="page-subtitle">Опитування, реєстрації та інші звернення.</p></div>
    <div class="form-actions"><a class="button secondary" href="<?= url('/admin/forms/submissions') ?>"><span class="mdi mdi-inbox-outline"></span><span>Відповіді</span></a><a class="button" href="<?= url('/admin/forms/edit') ?>"><span class="mdi mdi-plus"></span><span>Додати форму</span></a></div>
</div>
<div class="list-panel"><div class="table-scroll"><table>
<thead><tr><th>Назва</th><th>Тип</th><th>Статус</th><th>Відповідей</th><th>Остання відповідь</th><th></th></tr></thead>
<tbody><?php foreach ($items as $item): ?><tr><td><strong><?= e($item['title']) ?></strong><div class="meta"><?= e($item['slug']) ?></div></td><td><?= e($item['type']) ?></td><td><span class="status <?= $item['status'] === 'published' ? 'ok' : '' ?>"><?= $item['status'] === 'published' ? 'Опубліковано' : 'Чернетка' ?></span></td><td><a href="<?= url('/admin/forms/submissions?form_id=' . $item['id']) ?>"><?= e((string) $item['submissions_count']) ?></a></td><td><?= e((string) ($item['last_submission_at'] ?? '—')) ?></td><td><a class="button secondary compact" href="<?= url('/admin/forms/edit?id=' . $item['id']) ?>">Редагувати</a></td></tr><?php endforeach; ?></tbody>
</table></div><?php if (!$items): ?><div class="empty-state">Форм ще немає.</div><?php endif; ?></div>
