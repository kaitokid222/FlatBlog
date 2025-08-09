<?php
require_once 'include/core.php';
require_once 'include/template.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

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

        // Bilder hochladen (nach fester Namenskonvention entryID-1/2)
        $imageUrls = handle_entry_image_upload($_FILES, $newId);

        // Weiterleitung oder Markdown-Hilfe anzeigen
        if ($imageUrls) {
            template_header("Bilder eingefügt");
            echo '<div class="main-content"><h2>Bilder erfolgreich hochgeladen</h2>';
            foreach ($imageUrls as $url) {
                echo "<p>Markdown zum Einfügen:</p>";
                echo "<code>![Bildbeschreibung]($url)</code><br><br>";
                echo "<img src=\"$url\" style=\"max-width:100%;\"><hr>";
            }
            echo "<p><a class=\"button\" href=\"entry.php?id=$newId\">Beitrag ansehen</a></p>";
            template_footer();
            exit;
        } else {
            header('Location: entry.php?id=' . $newId);
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
		<p><label>Bild 1: <input type="file" name="image1" accept="image/*"></label></p>
		<p><label>Bild 2: <input type="file" name="image2" accept="image/*"></label></p>
		<p><button type="submit">Veröffentlichen</button></p>
	</form>
	<p><a class="button" href="acp.php">Zurück</a></p>
</div>

<?php
template_footer();
?>
