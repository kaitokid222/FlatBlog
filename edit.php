<?php
require_once 'include/core.php';
require_once 'include/template.php';

$id = get_content_id_from_path('edit');

loginCheck();

// CSRF-Token
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

// Post laden
$posts = get_all_posts();
$post = null;
foreach ($posts as $p) {
	if ($p['id'] == $id) {
		$post = $p; break;
	}
}
if (!$post) { 
	template_header("Beitrag nicht gefunden"); 
	echo "<div class='main-content'><p>Beitrag mit ID $id nicht gefunden.</p></div>"; 
	template_footer(); 
	exit; 
}

$currentVis = strtolower($post['visibility'] ?? 'visible');
if (!in_array($currentVis, ['visible','hidden','draft'], true)) {
    $currentVis = 'visible';
}


$valid_csrf = fn() => hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '');

/* ---------- SPEICHERN ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    if (!$valid_csrf()){
		$_SESSION['flash_warn'] = "Ungültiger Sicherheits-Token."; 
		header('Location: edit.php?id='.$id); 
		exit; 
	}

    $title      = trim($_POST['title'] ?? '');
    $content    = trim($_POST['content'] ?? '');
    $chosenCats = array_map('trim', $_POST['cats'] ?? []);
	
	$visibility = strtolower(trim($_POST['visibility'] ?? $currentVis));
	$allowedVis = ['visible','hidden','draft'];
	if (!in_array($visibility, $allowedVis, true)) {
		$visibility = 'visible';
	}

	
	// Datum/Zeit aus den Selects
    $yy = (int)($_POST['date_year']  ?? 0);
    $mm = (int)($_POST['date_month'] ?? 0);
    $dd = (int)($_POST['date_day']   ?? 0);
    $hh = (int)($_POST['time_hour']  ?? 0);
    $mi = (int)($_POST['time_min']   ?? 0);
    $ss = (int)($_POST['time_sec']   ?? 0);

    // Validierung: existierendes Datum? (PHP checkdate prüft nur Datum, nicht Zeit)
    $newCreatedAt = null;
    if (checkdate($mm, $dd, $yy) && $hh>=0 && $hh<=23 && $mi>=0 && $mi<=59 && $ss>=0 && $ss<=59) {
        $newCreatedAt = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $yy, $mm, $dd, $hh, $mi, $ss);
    } else {
        // Fallback: altes Datum behalten + Warnung
        $_SESSION['flash_warn'] = "Ungültiges Datum/Zeit – ursprüngliches Datum wurde beibehalten.";
        $newCreatedAt = $post['created_at'];
    }

    if ($title !== '' && $content !== '') {
        // zentral: update_post übernimmt Cleanups & Standardisierung
		$ok = update_post($id, $title, $content, $chosenCats, $newCreatedAt, $visibility);
        if (!$ok) {
            $error = "Speichern fehlgeschlagen.";
        } else {
            // Bilder hochladen/überschreiben (wie gehabt)
            if (!empty($_FILES)) { handle_entry_image_upload($_FILES, $id); }
            header('Location: entry.php?id=' . $id);
            exit;
        }
    } else {
        $error = "Titel und Inhalt dürfen nicht leer sein.";
    }
}

/* ---------- BILD LÖSCHEN ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_image'])) {
    if (!$valid_csrf()) { $_SESSION['flash_warn'] = "Ungültiger Sicherheits-Token."; header('Location: edit.php?id='.$id); exit; }
    $idx = (int)$_POST['delete_image'];           // kommt vom Button-Wert (1 oder 2)
    // Draft sichern (aus aktuellem Formular!)
    $_SESSION['draft_title'] = $_POST['title'] ?? $post['title'];
    $_SESSION['draft_content'] = $_POST['content'] ?? $post['content'];

    if (in_array($idx, [1,2], true)) {
        $ok = delete_entry_image($id, $idx);
        $_SESSION['flash_warn'] = $ok ? "Bild {$idx} entfernt." : "Bild {$idx} konnte nicht entfernt werden.";
    }
    header('Location: edit.php?id=' . $id . '&warn=1'); exit;
}

/* ---------- BEITRAG LÖSCHEN (separates Formular) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post'])) {
    if (!$valid_csrf()) {
		$_SESSION['flash_warn'] = "Ungültiger Sicherheits-Token.";
		header('Location: edit.php?id='.$id);
		exit;
	}
    if (($_POST['confirm'] ?? '') === 'YES') {
        $ok = delete_post($id);
        $_SESSION['flash_info'] = $ok ? "Beitrag #$id wurde gelöscht." : "Beitrag #$id konnte nicht gelöscht werden.";
        header('Location: index.php'); exit;
    } else {
        $_SESSION['flash_warn'] = "Löschen abgebrochen.";
        header('Location: edit.php?id='.$id); exit;
    }
}

/* ---------- Draft nach Bild-Löschen zurückspielen ---------- */
if (isset($_SESSION['draft_title']) || isset($_SESSION['draft_content'])) {
    $post['title'] = $_SESSION['draft_title'] ?? $post['title'];
    $post['content'] = $_SESSION['draft_content'] ?? $post['content'];
    unset($_SESSION['draft_title'], $_SESSION['draft_content']);
}

$dt = DateTime::createFromFormat('Y-m-d H:i:s', $post['created_at']) ?: new DateTime();
$Y = (int)$dt->format('Y');
$m = (int)$dt->format('n');
$d = (int)$dt->format('j');
$H = (int)$dt->format('G');
$i = (int)$dt->format('i');
$s = (int)$dt->format('s');

// Jahrbereich (anpassen wie du willst)
$yearMin = 2010;
$yearMax = (int)date('Y') + 1;

// Monatsnamen (DE)
$months = [
    1=>'Januar',2=>'Februar',3=>'März',4=>'April',5=>'Mai',6=>'Juni',
    7=>'Juli',8=>'August',9=>'September',10=>'Oktober',11=>'November',12=>'Dezember'
];

$allCats = load_categories();
$currentCats = get_post_categories($post); // statt aus Content parsen

template_header("Beitrag bearbeiten");
?>
<div class="main-content">
    <h2>Beitrag bearbeiten</h2>

    <?php if (!empty($_GET['warn']) && !empty($_SESSION['flash_warn'])): ?>
        <p style="color:#b58900;"><?= htmlspecialchars($_SESSION['flash_warn']) ?></p>
        <?php unset($_SESSION['flash_warn']); ?>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <!-- HAUPTFORMULAR -->
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <p><input type="text" name="title" value="<?= htmlspecialchars($post['title']) ?>" required></p>
		<fieldset class="dtpicker">
			<legend>Datum/Zeit</legend>
			<label>
				Tag
				<select name="date_day" required>
					<?php for ($dd=1; $dd<=31; $dd++): ?>
						<option value="<?= $dd ?>" <?= $dd===$d?'selected':'' ?>><?= $dd ?></option>
					<?php endfor; ?>
				</select>
			</label>
			<label>
				Monat
				<select name="date_month" required>
					<?php foreach ($months as $mm=>$name): ?>
						<option value="<?= $mm ?>" <?= $mm===$m?'selected':'' ?>><?= htmlspecialchars($name) ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				Jahr
				<select name="date_year" required>
					<?php for ($yy=$yearMax; $yy>=$yearMin; $yy--): ?>
						<option value="<?= $yy ?>" <?= $yy===$Y?'selected':'' ?>><?= $yy ?></option>
					<?php endfor; ?>
				</select>
			</label>
			<label>
				Stunde
				<select name="time_hour" required>
					<?php for ($hh=0; $hh<=23; $hh++): ?>
						<option value="<?= $hh ?>" <?= $hh===$H?'selected':'' ?>><?= sprintf('%02d',$hh) ?></option>
					<?php endfor; ?>
				</select>
			</label>
			<label>
				Minute
				<select name="time_min" required>
					<?php for ($mi=0; $mi<=59; $mi++): ?>
						<option value="<?= $mi ?>" <?= $mi===$i?'selected':'' ?>><?= sprintf('%02d',$mi) ?></option>
					<?php endfor; ?>
				</select>
			</label>
			<label>
				Sekunde
				<select name="time_sec" required>
					<?php for ($ss=0; $ss<=59; $ss++): ?>
						<option value="<?= $ss ?>" <?= $ss===$s?'selected':'' ?>><?= sprintf('%02d',$ss) ?></option>
					<?php endfor; ?>
				</select>
			</label>
		</fieldset>
		<!-- HIER DATUMS-picker oder so einfügen was html css halt hergibt-->
        <p><textarea name="content" rows="15" required><?= htmlspecialchars($post['content']) ?></textarea></p>
		<p>
		  <label>Sichtbarkeit:
			<select name="visibility" required>
			  <option value="visible" <?= $currentVis==='visible' ? 'selected' : '' ?>>Öffentlich</option>
			  <option value="hidden"  <?= $currentVis==='hidden'  ? 'selected' : '' ?>>Versteckt</option>
			  <option value="draft"   <?= $currentVis==='draft'   ? 'selected' : '' ?>>Entwurf</option>
			</select>
		  </label>
		</p>

	<?php if ($allCats): ?>
		<fieldset>
			<legend>Kategorien</legend>
			<?php foreach ($allCats as $c):
				$checked = in_array($c, $currentCats, true) ? 'checked' : ''; ?>
				<label style="margin-right:1rem;">
					<input type="checkbox" name="cats[]" value="<?= htmlspecialchars($c) ?>" <?= $checked ?>>
					<?= htmlspecialchars($c) ?>
				</label>
			<?php endforeach; ?>
		</fieldset>
	<?php endif; ?>

		<h3>Medien</h3>
		<div class="thumb-grid">
			<?php for ($i = 1; $i <= 2; $i++):
				$media = find_entry_image($id, $i); // ['url','abs','type'=>image|video]
				if ($media): ?>
					<div class="thumb">
						<a href="<?= htmlspecialchars($media['url']) ?>" target="_blank" title="Vollansicht">
							<?php if ($media['type'] === 'video'): ?>
								<video src="<?= htmlspecialchars($media['url']) ?>"
									   preload="metadata" muted playsinline></video>
								<span class="play-badge">▶</span>
							<?php else: ?>
								<img src="<?= htmlspecialchars($media['url']) ?>" alt="Medien <?= $i ?>">
							<?php endif; ?>
						</a>
						<!-- Button bleibt im selben Formular -->
						<button type="submit"
								name="delete_image"
								value="<?= $i ?>"
								class="delete-btn"
								formnovalidate
								title="Medien löschen">✖</button>
					</div>
				<?php else: ?>
					<div class="thumb empty">
						<label>Datei <?= $i ?><br>
							<input type="file" name="image<?= $i ?>" accept="image/*,video/mp4">
						</label>
					</div>
				<?php endif;
			endfor; ?>
		</div>


        <p>
            <button type="submit" name="save" value="1">💾 Speichern</button>
            <a class="button" href="entry.php?id=<?= $id ?>">Abbrechen</a>
        </p>
    </form>

    <hr>
    <h3 style="color:#a00;">Beitrag löschen</h3>
    <p><small>Achtung: Der Beitragstext und die zugehörigen Bilder (Slots 1 & 2) werden entfernt.</small></p>

    <!-- SEPARATES LÖSCH-FORMULAR -->
    <form method="post"
          onsubmit="return confirm('Wirklich löschen? Das kann nicht rückgängig gemacht werden.');"
          style="margin-top:0.5rem;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="delete_post" value="1">
        <input type="hidden" name="confirm" value="YES">
        <button type="submit" style="background:#b00020;">🗑️ Endgültig löschen</button>
    </form>
</div>
<?php template_footer(); ?>
