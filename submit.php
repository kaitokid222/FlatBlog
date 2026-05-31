<?php
require_once 'include/core.php';
require_once 'include/template.php';

loginCheck();

$allCats = load_categories();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title      = trim($_POST['title'] ?? '');
    $content    = trim($_POST['content'] ?? '');
    $chosenCats = array_map('trim', $_POST['cats'] ?? []);
	$visibility = strtolower(trim($_POST['visibility'] ?? 'visible'));
	$allowedVis = ['visible','hidden','draft'];
	if (!in_array($visibility, $allowedVis, true)) {
		$visibility = 'visible';
	}

    if ($title !== '' && $content !== '') {
        // Content säubern & pseudonymisieren
        $content = strip_category_meta_from_content($content);
        $content = pseudonymize_text($content);

        // zentral speichern -> gibt neue ID zurück
        $newId = save_post($title, $content, $chosenCats, $visibility);

        // Medien hochladen (entryID-1, entryID-2, ...)
        $imageUrls = handle_entry_image_upload($_FILES, $newId);

        // Weiterleitung oder Markdown-Hilfe anzeigen
        if ($imageUrls) {
            template_header("Medien eingefügt");
            echo '<div class="main-content"><h2>Medien erfolgreich hochgeladen</h2>';
            foreach ($imageUrls as $url) {
                $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
                echo "<p>Markdown zum Einfügen:</p>";
                echo "<code>![Bildbeschreibung]($url)</code><br><br>";
                if ($ext === 'mp4') {
                    echo "<video src=\"$url\" controls style=\"max-width:100%;\"></video><hr>";
                } else {
                    echo "<img src=\"$url\" style=\"max-width:100%;\"><hr>";
                }
            }
            echo "<p><a class=\"button\" href=\"" . e(url_entry($newId)) . "\">Beitrag ansehen</a></p>";
            template_footer();
            exit;
        } else {
            header('Location: ' . e(url_entry($newId)));
            exit;
        }
    } else {
        $error = "Titel und Inhalt dürfen nicht leer sein.";
    }
}

template_header("Neuen Beitrag erstellen");

if (!empty($error)) {
    echo "<p style='color:red;'>$error</p>";
}
?>
<div class="main-content">
	<form method="post" enctype="multipart/form-data">
		<input type="hidden" name="MAX_FILE_SIZE" value="5242880"><!-- 5 MB optional -->
		<p><input type="text" name="title" placeholder="Titel" required style="width:100%;"></p>
		<p><textarea name="content" rows="10" placeholder="Inhalt" required style="width:100%;"></textarea></p>
		<p>
		  <label>Sichtbarkeit:
			<select name="visibility" required>
			  <option value="visible">Öffentlich</option>
			  <option value="hidden">Versteckt</option>
			  <option value="draft">Entwurf</option>
			</select>
		  </label>
		</p>
		<?php if ($allCats): ?>
        <fieldset>
            <legend>Kategorien</legend>
            <?php foreach ($allCats as $c): ?>
                <label style="margin-right:1rem;">
                    <input type="checkbox" name="cats[]" value="<?= htmlspecialchars($c) ?>">
                    <?= htmlspecialchars($c) ?>
                </label>
        <?php endforeach; ?>
        </fieldset>
    <?php endif; ?>
		<p><label>Medien: <input type="file" name="media[]" accept="image/*,video/mp4" multiple></label></p>
		<p><button type="submit">Veröffentlichen</button></p>
	</form>
	<p><a class="button" href="<?= url_acp(); ?>">Zurück</a></p>
</div>

<?php
template_footer();
?>
