<?php
require_once 'include/core.php';
require_once 'include/template.php';

$all = get_all_posts();
$posts = array_values(array_filter($all, fn($p) => $p['visibility'] === 'visible' || is_logged_in()));

if (!isset($_GET['show']) || $_GET['show'] !== 'all') {
    $posts = array_slice($posts, 0, 3);
}
$archive = get_post_archive(is_logged_in());

template_header(SITE_TITLE);
?>



<div class="main-content">

<?php if (!empty($_SESSION['flash_info'])): ?>
    <p style="color:green;"><?= htmlspecialchars($_SESSION['flash_info']) ?></p>
    <?php unset($_SESSION['flash_info']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_warn'])): ?>
    <p style="color:#b58900;"><?= htmlspecialchars($_SESSION['flash_warn']) ?></p>
    <?php unset($_SESSION['flash_warn']); ?>
<?php endif; ?>

<?php 
foreach ($posts as $post){
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
?>
    <article>
        <h2><a href="<?= e(url_entry($post['id']))?>"><?= e($post['title']) ?></a></h2>
        <small><?= e($post['created_at']) ?> 
		<?php if ($visLabel && $visLabel != ''): ?>
				<span style="color:<?= $visColor ?>; margin-left:0.5rem;">
					[ <?= $visLabel ?> ]
				</span>
			<?php endif; ?>
		</small>
		<?php $cats = get_post_categories($post) ?>
		<?php if ($cats): ?>
		<p class="cats">
			<?php foreach ($cats as $c): ?>
			<a class="cat-badge" href="search.php?category=<?= urlencode($c) ?>"><?= htmlspecialchars($c) ?></a>
			<?php endforeach; ?>
		</p>
		<?php endif; ?>
        <?= get_post_preview_uniform($post['content'], PREVIEWLENGTH) ?>
		<p><a class="button" href="<?= e(url_entry($post['id']))?>">Weiterlesen</a></p>
    </article>
<?php } 
if (is_logged_in()) {
?>
	<p><a class="button" href="submit.php">➕ Neuen Beitrag erstellen</a></p>
<?php
}
?>
</div>

<div class="sidebar">
    <h3>Archiv</h3>
    <ul>
    <?php foreach ($archive as $year => $months){ 
        $total = $months['_total'] ?? 0; ?>
        <li>
            <details>
                <summary>
				<?php // url_search(?int $year=null, ?int $month=null, ?string $category=null, ?int $page=null) ?>
                    <a href="<?= url_search(urlencode($year)) ?>">

                        <?= htmlspecialchars($year) ?> (<?= (int)$total ?>)
                    </a>
                </summary>
                <ul>
                <?php foreach ($months as $monthNum => $meta){
                    if ($monthNum === '_total') continue; ?>
                    <li>
                        <a href="search.php?year=<?= urlencode($year) ?>&month=<?= urlencode((int)$monthNum) ?>">
                            <?= htmlspecialchars($meta['name']) ?> (<?= (int)$meta['count'] ?>)
                        </a>
                    </li>
                <?php } ?>
                </ul>
            </details>
        </li>
    <?php } ?>
    </ul>
</div>


<?php
template_footer();
?>
