<?php
require_once 'include/core.php';
require_once 'include/template.php';

$id = get_content_id_from_path('entry');

$posts = get_all_posts();
$post = null;
$postIds = array_column($posts, 'id');

// Beitrag mit passender ID finden
foreach ($posts as $p) {
    if ($p['id'] == $id) {
        $post = $p;
        break;
    }
}

if (!$post) {
    template_header("Beitrag nicht gefunden");
    echo "<div class='main-content'><p>Der Beitrag wurde nicht gefunden.</p></div>";
    template_footer();
    exit;
}

// Vorheriger / Nächster berechnen
sort($postIds);
$currentIndex = array_search($id, $postIds);
$prevId = $currentIndex > 0 ? $postIds[$currentIndex - 1] : null;
$nextId = $currentIndex < count($postIds) - 1 ? $postIds[$currentIndex + 1] : null;

// Zufällige ID (außer aktuelle)
$randomId = null;
if (count($postIds) > 1) {
    do {
        $randomId = $postIds[array_rand($postIds)];
    } while ($randomId == $id);
}

$visLabel = '';
$visColor = '';
if (is_logged_in()){
	switch (strtolower($post['visibility'] ?? 'visible')) {
		case 'visible':
			$visLabel = 'Öffentlich';
			$visColor = 'green';
			break;
		case 'draft':
			$visLabel = 'Entwurf';
			$visColor = 'goldenrod';
			break;
		case 'hidden':
			$visLabel = 'Versteckt';
			$visColor = 'red';
			break;
	}
}

template_header($post['title']);
?>
<div class="main-content">
    <article>
        <h2><?= htmlspecialchars($post['title']) ?></h2>
        <small><?= $post['created_at'] ?> 
		<?php if ($visLabel && $visLabel != ''): ?>
				<span style="color:<?= $visColor ?>; margin-left:0.5rem;">
					[ <?= $visLabel ?> ]
				</span>
			<?php endif; ?></small>
	<?php $cats = get_post_categories($post) ?>
	<?php if ($cats): ?>
		<p class="cats">
			<?php foreach ($cats as $c): ?>
				<a class="cat-badge" href="search.php?category=<?= urlencode($c) ?>">
					<?= htmlspecialchars($c) ?>
				</a>
			<?php endforeach; ?>
		</p>
	<?php endif; ?>
        <p><?= perform_markdown($post['content']) ?></p>

<?php
$mediaItems = get_entry_images((int)$post['id']); // liefert [{url, type, abs}, ...]
if ($mediaItems): ?>
    <h4>Medien zum Beitrag:</h4>
    <div class="gallery">
        <?php foreach ($mediaItems as $m): ?>
            <?php if ($m['type'] === 'video'): ?>
                <a href="<?= htmlspecialchars($m['url']) ?>" target="_blank" class="gallery-item video">
                    <video src="<?= htmlspecialchars($m['url']) ?>" preload="metadata" muted playsinline></video>
                </a>
            <?php else: ?>
                <a href="<?= htmlspecialchars($m['url']) ?>" target="_blank" class="gallery-item image">
                    <img src="<?= htmlspecialchars($m['url']) ?>" alt="Medienbild">
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
    </article>
<?php
if (is_logged_in()) {
?>
	<p><a class="button" href="edit.php?id=<?= $post['id'] ?>">✏️ Bearbeiten</a></p>
<?php
}
?>

    <div style="margin-top: 2rem; display: flex; gap: 1rem;">
        <?php if ($prevId !== null): ?>
            <a class="button" href="entry.php?id=<?= $prevId ?>">← Vorheriger</a>
        <?php endif; ?>

        <?php if ($randomId !== null): ?>
            <a class="button" href="entry.php?id=<?= $randomId ?>">🎲 Zufälliger</a>
        <?php endif; ?>

        <?php if ($nextId !== null): ?>
            <a class="button" href="entry.php?id=<?= $nextId ?>">Nächster →</a>
        <?php endif; ?>
    </div>
	<p><a class="button" href="index.php">Zurück zur Übersicht</a></p>
</div>
<?php
template_footer();
?>
