<?php
require_once 'include/core.php';
require_once 'include/template.php';

loginCheck();

$statsFile = __DIR__ . '/content/stats.txt';
$rows = [];
if (file_exists($statsFile)) {
    $lines = file($statsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $parts = explode('|', $line);
        if (count($parts) === 3) {
            $rows[] = [
                'date' => $parts[0],
                'endpoint' => $parts[1],
                'ip' => $parts[2],
            ];
        }
    }
}

template_header('Statistiken');
?>
<div class="main-content">
    <h2>Anfrage-Statistiken</h2>
<?php if ($rows): ?>
    <p><small><?= count($rows) ?> Einträge</small></p>
    <table class="entrylist">
        <thead>
            <tr>
                <th>Datum</th>
                <th>Endpoint</th>
                <th>IP-Slice</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['date']) ?></td>
                <td><?= htmlspecialchars($r['endpoint']) ?></td>
                <td><?= htmlspecialchars($r['ip']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>Keine Statistiken vorhanden.</p>
<?php endif; ?>
</div>
<?php
template_footer();
?>