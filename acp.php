<?php
require_once 'include/core.php';
require_once 'include/template.php';

loginCheck();

template_header('Admin Control Panel');
?>
<div class="main-content">
    <h2>Admin Control Panel</h2>
    <ul>
        <li><a href="<?= url_submit(); ?>">➕ Neuen Beitrag erstellen</a></li>
		<li><a href="<?= url_entrylist(); ?>">📚 Einträge verwalten</a></li>
        <li><a href="<?= url_admin_blacklist(); ?>">🧰 Blacklist verwalten</a></li>
        <li><a href="<?= site_url(); ?>">🏠 Zur Startseite</a></li>
        <li><a href="<?= url_logout(); ?>">🔒 Logout</a></li>
		<!-- More Bulletproof, aber sieht halt aus wie es aussieht -> CSS bauen! -->
		<!--<form action="logout.php" method="post" style="display:inline">
		  <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?? '' ?>">
		  <button>🔒 Logout</button>
		</form>-->
    </ul>

    <h3>Quick Infos</h3>
    <p>Angemeldet als Owner. Hier kannst du Inhalte verwalten und Tools aufrufen.</p>
</div>
<?php
template_footer();
?>