<?php
    $stats = $analysis['stats'] ?? [];
    $suggestions = $analysis['suggestions'] ?? [];
    $conflicts = $analysis['conflicts'] ?? [];
    $previewLimit = max(1, (int) ($previewLimit ?? 200));
    $visibleSuggestions = array_slice($suggestions, 0, $previewLimit);
    $visibleConflicts = array_slice($conflicts, 0, $previewLimit);
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Сервісні інструменти</p>
        <h1>Оптимізатор</h1>
        <p class="page-subtitle">Автоматично впорядковує медіафайли у віртуальні папки за категоріями новин, де ці файли використані.</p>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert"><?= e((string) $error) ?></div>
<?php endif; ?>
<?php if (!empty($applied)): ?>
    <div class="alert alert-success">Оновлено віртуальні папки для файлів: <?= e((string) $applied) ?>.</div>
<?php endif; ?>

<div class="metrics">
    <div class="metric"><div><span>Медіафайлів</span><strong><?= e((string) ($stats['media_total'] ?? 0)) ?></strong></div><span class="mdi mdi-image-multiple-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Знайдено в новинах</span><strong><?= e((string) ($stats['news_media'] ?? 0)) ?></strong></div><span class="mdi mdi-newspaper-variant-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Буде змінено</span><strong><?= e((string) ($stats['updates'] ?? 0)) ?></strong></div><span class="mdi mdi-folder-sync-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Конфлікти</span><strong><?= e((string) ($stats['conflicts'] ?? 0)) ?></strong></div><span class="mdi mdi-alert-circle-outline metric-icon" aria-hidden="true"></span></div>
</div>

<section class="card admin-form-card">
    <div class="form-section-head">
        <div>
            <h2>Сортування медіафайлів новин</h2>
            <p class="meta">Оптимізатор не переміщує файли фізично. Він змінює тільки поле “Віртуальна папка” у медіатеці, наприклад: “Новини: Оголошення”.</p>
        </div>
        <form method="post" action="<?= url('/admin/optimizer/media-folders/apply') ?>">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" <?= !$suggestions ? 'disabled' : '' ?>>
                <span class="mdi mdi-auto-fix" aria-hidden="true"></span><span>Застосувати сортування</span>
            </button>
        </form>
    </div>
</section>

<section class="list-panel">
    <div class="list-tools">
        <strong>Заплановані зміни</strong>
        <span class="meta"><?= e((string) count($suggestions)) ?> файлів<?= count($suggestions) > $previewLimit ? ' · показано перші ' . e((string) $previewLimit) : '' ?></span>
    </div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>Файл</th><th>Поточна папка</th><th>Нова папка</th><th>Новина</th></tr></thead>
            <tbody>
                <?php foreach ($visibleSuggestions as $item): ?>
                    <tr>
                        <td>
                            <strong><?= e((string) ($item['name'] ?? '')) ?></strong><br>
                            <span class="meta"><?= e((string) ($item['path'] ?? '')) ?></span>
                        </td>
                        <td>
                            <?php if ((string) ($item['current_folder'] ?? '') !== ''): ?>
                                <span class="media-folder-pill"><span class="mdi mdi-folder-outline" aria-hidden="true"></span><?= e((string) $item['current_folder']) ?></span>
                            <?php else: ?>
                                <span class="meta">Без папки</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="media-folder-pill"><span class="mdi mdi-folder-outline" aria-hidden="true"></span><?= e((string) ($item['folder'] ?? '')) ?></span></td>
                        <td>
                            <?php foreach (($item['usages'] ?? []) as $usage): ?>
                                <a href="<?= e((string) ($usage['url'] ?? '#')) ?>"><?= e((string) ($usage['title'] ?? 'Без назви')) ?></a><br>
                                <span class="meta"><?= e((string) ($usage['category'] ?? '')) ?></span><br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$suggestions): ?>
                    <tr><td colspan="4" class="empty-state">Немає змін для застосування.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($conflicts): ?>
    <section class="list-panel mt-4">
        <div class="list-tools">
            <strong>Конфлікти категорій</strong>
            <span class="meta">Ці файли використані в новинах з різними категоріями, тому автоматично не змінюються.<?= count($conflicts) > $previewLimit ? ' Показано перші ' . e((string) $previewLimit) . '.' : '' ?></span>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Файл</th><th>Поточна папка</th><th>Можливі папки</th><th>Новини</th></tr></thead>
                <tbody>
                    <?php foreach ($visibleConflicts as $item): ?>
                        <tr>
                            <td>
                                <strong><?= e((string) ($item['name'] ?? '')) ?></strong><br>
                                <span class="meta"><?= e((string) ($item['path'] ?? '')) ?></span>
                            </td>
                            <td><?= (string) ($item['current_folder'] ?? '') !== '' ? e((string) $item['current_folder']) : '<span class="meta">Без папки</span>' ?></td>
                            <td>
                                <?php foreach (($item['folders'] ?? []) as $folder): ?>
                                    <span class="media-folder-pill mb-1"><span class="mdi mdi-folder-outline" aria-hidden="true"></span><?= e((string) $folder) ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <?php foreach (($item['usages'] ?? []) as $usage): ?>
                                    <a href="<?= e((string) ($usage['url'] ?? '#')) ?>"><?= e((string) ($usage['title'] ?? 'Без назви')) ?></a><br>
                                    <span class="meta"><?= e((string) ($usage['category'] ?? '')) ?></span><br>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
