<section class="section">
    <div class="container">
        <h1>Прозорість та інформаційна відкритість</h1>
        <p class="meta">Розділ сформовано відповідно до вимог відкритості інформації закладу освіти.</p>
        <div class="grid">
            <?php foreach ($sections as $section): ?>
                <article class="card">
                    <div class="toolbar">
                        <h2><?= e($section['title']) ?></h2>
                        <span class="status <?= ((int) $section['documents_count']) > 0 ? 'ok' : 'warn' ?>">
                            <?= ((int) $section['documents_count']) > 0 ? 'опубліковано' : 'очікує документів' ?>
                        </span>
                    </div>
                    <?php $documents = $documentsBySection[$section['id']] ?? []; ?>
                    <?php if ($documents): ?>
                        <table>
                            <tr><th>Документ</th><th>Дата</th><th>Файл</th></tr>
                            <?php foreach ($documents as $document): ?>
                                <tr>
                                    <td><?= e($document['title']) ?><br><span class="meta"><?= e($document['description'] ?? '') ?><?= !empty($document['responsible']) ? ' · Відповідальний: ' . e($document['responsible']) : '' ?></span></td>
                                    <td><?= e($document['published_at'] ?? $document['approved_at'] ?? $document['updated_at']) ?></td>
                                    <td><?php if ($document['file_path']): ?><a href="<?= url('/uploads/' . $document['file_path']) ?>">Відкрити</a><?php endif; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php else: ?>
                        <p class="meta">Документи ще не додані.</p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
