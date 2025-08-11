<?php
require_once 'include/core.php';
require_once 'include/template.php';

loginCheck();

$info = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = $_POST['raw'] ?? '';
    // Optional: minimale Validierung (z. B. erlaubtes Format)
    try {
        save_blacklist_raw($raw);
        $info = 'Blacklist gespeichert.';
    } catch (Throwable $e) {
        $error = 'Speichern fehlgeschlagen: ' . $e->getMessage();
    }
}

$current = file_exists(BLACKLIST_FILE) ? file_get_contents(BLACKLIST_FILE) : "# Beispiel:\n# Lisa|Steffi,Tanja,Kim\n# Max Mustermann|Peter Pan,Tom Tester";

template_header('Blacklist verwalten');
?>
<div class="main-content">
    <h2>Blacklist verwalten</h2>

    <?php if ($info): ?><p style="color:green;"><?= e($info) ?></p><?php endif; ?>
    <?php if ($error): ?><p style="color:red;"><?= e($error) ?></p><?php endif; ?>

    <form method="post">
        <p><textarea name="raw" rows="16" style="width:100%;"><?= e($current) ?></textarea></p>
        <p>
            <button type="submit">💾 Speichern</button>
            <a class="button" href="acp.php">⚙️ Zurück zum ACP</a>
        </p>
    </form>

    <h3>Format</h3>
    <pre><code>bannedword|alternative1,alternative2,alternative3
Lisa|Steffi,Tanja,Kim
"Lisa Müller"|Steffi S.,Tanja M.
"Max Mustermann"|Peter Pan,Tom Tester
</code></pre>
    <p><small>Tipp: Längere Phrasen stehen vor kürzeren; Zeilen mit <code>#</code> sind Kommentare.</small></p>
</div>
<?php
template_footer();
