<?php
require_once 'include/core.php';
require_once 'include/template.php';

loginCheck(false);

$csrf = $_SESSION['csrf'];

// Einzel-Löschung aus der Liste
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['list_delete'])) {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $_SESSION['flash_warn'] = "Ungültiger Sicherheits-Token.";
        header('Location: ' . e(url_entrylist()) .''); exit;
    }
    $delId = (int)$_POST['list_delete'];
    if ($delId > 0) {
        $ok = delete_post($delId);
        $_SESSION['flash_info'] = $ok ? "Beitrag #$delId gelöscht." : "Beitrag #$delId konnte nicht gelöscht werden.";
    }
    header('Location: ' . e(url_entrylist()) .''); exit;
}

// Daten + Pagination
$allPosts = get_all_posts(); // sollte bereits nach created_at DESC sortiert sein
$total    = count($allPosts);
$perPage  = 50;
$page  = max(1, (int)($_GET['page'] ?? 1));
$pages = max(1, (int)ceil($total / $perPage));
if ($page > $pages) $page = $pages;

$offset = ($page - 1) * $perPage;
$rows   = array_slice($allPosts, $offset, $perPage);

template_header('Einträge verwalten');
?>
<div class="main-content">
    <h2>Einträge verwalten</h2>

    <?php if (!empty($_SESSION['flash_info'])): ?>
        <p style="color:green;"><?= htmlspecialchars($_SESSION['flash_info']) ?></p>
        <?php unset($_SESSION['flash_info']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_warn'])): ?>
        <p style="color:#b58900;"><?= htmlspecialchars($_SESSION['flash_warn']) ?></p>
        <?php unset($_SESSION['flash_warn']); ?>
    <?php endif; ?>

    <p>
        <a class="button" href="<?=  e(url_submit()); ?>">➕ Neuer Eintrag</a>
        <a class="button" href="<?=  e(url_acp()); ?>">⚙️ Zurück zum ACP</a>
    </p>

    <p><small>Seite <?= $page ?> von <?= $pages ?> – insgesamt <?= $total ?> Einträge</small></p>

    <table class="entrylist">
        <thead>
            <tr>
                <th style="width:70px;">ID</th>
                <th>Titel</th>
                <th style="width:210px;">Erstellt</th>
                <th style="width:220px;">Kategorien</th>
                <th style="width:240px; text-align:right;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="5"><em>Keine Einträge auf dieser Seite.</em></td></tr>
        <?php else: ?>
            <?php foreach ($rows as $p):
                $cats = get_post_categories($p);
                $catLabel = $cats ? implode(', ', $cats) : '–';
            ?>
            <tr>
                <td><?= (int)$p['id'] ?></td>
                <td><?= htmlspecialchars($p['title']) ?></td>
                <td><?= htmlspecialchars($p['created_at']) ?></td>
                <td><?= htmlspecialchars($catLabel) ?></td>
                <td style="text-align:right;">
                    <a class="button" href="<?=  e(url_entry((int)$p['id'])); ?>" target="_blank">Zum Beitrag</a>
                    <a class="button" href="edit.php?id=<?= (int)$p['id'] ?>">Bearbeiten</a>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Beitrag #<?= (int)$p['id'] ?> wirklich löschen?');">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <button type="submit" name="list_delete" value="<?= (int)$p['id'] ?>" style="background:#b00020;">🗑️ Löschen</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Seiten">
            <?php if ($page > 1): ?>
                <a class="button" href="?page=<?= $page-1 ?>">← Zurück</a>
            <?php endif; ?>

            <?php
            // einfache Fenster-Navigation
            $window = 3;
            $start = max(1, $page - $window);
            $end   = min($pages, $page + $window);
            for ($i = $start; $i <= $end; $i++):
            ?>
                <?php if ($i === $page): ?>
                    <span class="button" style="opacity:.7; cursor:default;"><?= $i ?></span>
                <?php else: ?>
                    <a class="button" href="?page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $pages): ?>
                <a class="button" href="?page=<?= $page+1 ?>">Weiter →</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

</div>
<?php
template_footer();
