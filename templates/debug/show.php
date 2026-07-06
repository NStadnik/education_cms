<div class="card">
    <h1>Debug</h1>
    <p class="meta">Не залишайте debug увімкненим на робочому сайті.</p>
    <table>
        <?php foreach ($info as $name => $value): ?>
            <tr>
                <th><?= e($name) ?></th>
                <td><?= e((string) $value) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="card debug-session-card">
    <h2>Session</h2>
    <pre><?= e(json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
</div>
