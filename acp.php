<?php
require_once 'include/core.php';
require_once 'include/template.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

template_header('Admin Control Panel');
?>
<div class="main-content">
    <h2>Admin Control Panel</h2>
    <ul>
        <li><a href="submit.php">➕ Neuen Beitrag erstellen</a></li>
		<li><a href="entrylist.php">📚 Einträge verwalten</a></li>
        <li><a href="admin_blacklist.php">🧰 Blacklist verwalten</a></li>
        <li><a href="index.php">🏠 Zur Startseite</a></li>
        <li><a href="logout.php">🔒 Logout</a></li>
    </ul>

    <h3>Quick Infos</h3>
    <p>Angemeldet als Owner. Hier kannst du Inhalte verwalten und Tools aufrufen.</p>
</div>
<?php
template_footer();
