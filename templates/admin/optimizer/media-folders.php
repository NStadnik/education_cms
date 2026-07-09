<?php
    $stats = $analysis['stats'] ?? [];
    $suggestions = $analysis['suggestions'] ?? [];
    $conflicts = $analysis['conflicts'] ?? [];
    $canManageMedia = !empty($canManageMedia);
    $previewLimit = max(1, (int) ($previewLimit ?? 200));
    $visibleSuggestions = array_slice($suggestions, 0, $previewLimit);
    $visibleConflicts = array_slice($conflicts, 0, $previewLimit);
    $suggestionCount = count($suggestions);
    $conflictCount = count($conflicts);
?>
<div class="metrics optimizer-analysis-metrics">
    <div class="metric"><div><span>Медіафайлів</span><strong><?= e((string) ($stats['media_total'] ?? 0)) ?></strong></div><span class="mdi mdi-image-multiple-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Знайдено в новинах</span><strong><?= e((string) ($stats['news_media'] ?? 0)) ?></strong></div><span class="mdi mdi-newspaper-variant-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Буде змінено</span><strong><?= e((string) ($stats['updates'] ?? 0)) ?></strong></div><span class="mdi mdi-folder-sync-outline metric-icon" aria-hidden="true"></span></div>
    <div class="metric"><div><span>Конфлікти</span><strong><?= e((string) ($stats['conflicts'] ?? 0)) ?></strong></div><span class="mdi mdi-alert-circle-outline metric-icon" aria-hidden="true"></span></div>
</div>

<section class="card admin-form-card optimizer-media-card">
    <div class="form-section-head optimizer-media-head">
        <div>
            <div class="optimizer-title-row">
                <span class="optimizer-icon mdi mdi-folder-sync-outline" aria-hidden="true"></span>
                <h2>Сортування медіафайлів новин</h2>
            </div>
            <p class="meta">Оптимізатор не переміщує файли фізично. Він змінює тільки поле “Віртуальна папка” у медіатеці, наприклад: “Новини: Оголошення”.</p>
        </div>
        <form method="post" action="<?= url('/admin/optimizer/media-folders/apply') ?>" data-optimizer-media-apply data-optimizer-confirm="Застосувати сортування для <?= e((string) $suggestionCount) ?> файлів?">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" <?= (!$canManageMedia || !$suggestions) ? 'disabled' : '' ?>>
                <span class="mdi mdi-auto-fix" aria-hidden="true"></span><span>Застосувати сортування</span>
            </button>
        </form>
    </div>
    <?php if (!$canManageMedia): ?>
        <p class="optimizer-note is-warning"><span class="mdi mdi-lock-outline" aria-hidden="true"></span><span>Для сортування медіафайлів потрібен доступ до медіатеки.</span></p>
    <?php elseif (!$suggestions): ?>
        <p class="optimizer-note is-ok"><span class="mdi mdi-check-circle-outline" aria-hidden="true"></span><span>Усі знайдені медіафайли вже мають актуальні віртуальні папки.</span></p>
    <?php endif; ?>
</section>

<section class="list-panel optimizer-table-panel">
    <div class="list-tools optimizer-list-tools">
        <div>
            <strong>Заплановані зміни</strong>
            <span class="meta">Папка буде оновлена тільки для файлів без конфліктів.</span>
        </div>
        <span class="list-count-pill"><?= e((string) $suggestionCount) ?> файлів<?= $suggestionCount > $previewLimit ? ' · перші ' . e((string) $previewLimit) : '' ?></span>
    </div>
    <div class="table-scroll">
        <table class="optimizer-table">
            <thead><tr><th>Файл</th><th>Поточна папка</th><th>Нова папка</th><th>Новина</th></tr></thead>
            <tbody>
                <?php foreach ($visibleSuggestions as $item): ?>
                    <tr>
                        <td>
                            <div class="optimizer-file-cell">
                                <span class="mdi mdi-file-image-outline" aria-hidden="true"></span>
                                <div>
                                    <strong><?= e((string) ($item['name'] ?? '')) ?></strong>
                                    <code><?= e((string) ($item['path'] ?? '')) ?></code>
                                </div>
                            </div>
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
                            <div class="optimizer-usage-list">
                            <?php foreach (($item['usages'] ?? []) as $usage): ?>
                                <span class="optimizer-usage-item">
                                    <a href="<?= e((string) ($usage['url'] ?? '#')) ?>"><?= e((string) ($usage['title'] ?? 'Без назви')) ?></a>
                                    <small><?= e((string) ($usage['category'] ?? '')) ?></small>
                                </span>
                            <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$suggestions): ?>
                    <tr><td colspan="4" class="empty-state optimizer-empty-table"><span class="mdi mdi-check-circle-outline" aria-hidden="true"></span><span>Немає змін для застосування.</span></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($conflicts): ?>
    <section class="list-panel optimizer-table-panel mt-4">
        <div class="list-tools optimizer-list-tools">
            <div>
                <strong>Конфлікти категорій</strong>
                <span class="meta">Ці файли використані в новинах з різними категоріями, тому автоматично не змінюються.</span>
            </div>
            <span class="optimizer-status-pill is-warning">
                <span class="mdi mdi-alert-circle-outline" aria-hidden="true"></span>
                <span><?= e((string) $conflictCount) ?><?= $conflictCount > $previewLimit ? ' · перші ' . e((string) $previewLimit) : '' ?></span>
            </span>
        </div>
        <div class="table-scroll">
            <table class="optimizer-table">
                <thead><tr><th>Файл</th><th>Поточна папка</th><th>Можливі папки</th><th>Новини</th></tr></thead>
                <tbody>
                    <?php foreach ($visibleConflicts as $item): ?>
                        <tr>
                            <td>
                                <div class="optimizer-file-cell">
                                    <span class="mdi mdi-file-alert-outline" aria-hidden="true"></span>
                                    <div>
                                        <strong><?= e((string) ($item['name'] ?? '')) ?></strong>
                                        <code><?= e((string) ($item['path'] ?? '')) ?></code>
                                    </div>
                                </div>
                            </td>
                            <td><?= (string) ($item['current_folder'] ?? '') !== '' ? e((string) $item['current_folder']) : '<span class="meta">Без папки</span>' ?></td>
                            <td>
                                <?php foreach (($item['folders'] ?? []) as $folder): ?>
                                    <span class="media-folder-pill mb-1"><span class="mdi mdi-folder-outline" aria-hidden="true"></span><?= e((string) $folder) ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <div class="optimizer-usage-list">
                                <?php foreach (($item['usages'] ?? []) as $usage): ?>
                                    <span class="optimizer-usage-item">
                                        <a href="<?= e((string) ($usage['url'] ?? '#')) ?>"><?= e((string) ($usage['title'] ?? 'Без назви')) ?></a>
                                        <small><?= e((string) ($usage['category'] ?? '')) ?></small>
                                    </span>
                                <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
