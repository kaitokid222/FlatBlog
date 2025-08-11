<?php
require_once 'include/core.php';
require_once 'include/template.php';

/**
 * Erwartete GET-Parameter (alle optional):
 * - year / y           (z.B. 2025)
 * - month / m          (1..12)
 * - ym / date          (z.B. 2025-08 oder 2025/08)
 * - category / cat     (z.B. "php")
 *
 * Beispiel-URLs:
 * - /search.php?year=2025
 * - /search.php?year=2025&month=8
 * - /search.php?ym=2025-08
 * - /search.php?category=php
 * - /search.php?year=2025&category=markdown
 */
$_GET['page'] = norm_int($_GET['page'] ?? null) ?? 1;
[$year, $month] = normalize_year_month($_GET);
$category = normalize_category($_GET);
$all = get_all_posts();
$allPosts = array_values(array_filter($all, fn($p) => $p['visibility'] === 'visible' || is_logged_in()));

$filtered = array_values(array_filter($allPosts, function($post) use ($year, $month, $category) {
    // Jahr-/Monat-Filter
    if ($year !== null || $month !== null) {
        $ts = strtotime($post['created_at']);
        if ($year !== null && (int)date('Y', $ts) !== (int)$year) {
            return false;
        }
        if ($month !== null && (int)date('n', $ts) !== (int)$month) {
            return false;
        }
    }

    // Kategorie-Filter
    if ($category !== null) {
        $cats = get_post_categories($post);
        if (empty($cats)) return false;
        foreach ($cats as $c) {
            if (mb_strtolower($c) === mb_strtolower($category)) {
                return true; // passt → Post behalten
            }
        }
        return false; // keine Übereinstimmung → raus
    }

    return true; // kein Filter → alles durchlassen
}));


// Titelzeile bauen
$titleBits = [];
if ($year !== null) { $titleBits[] = $year; }
if ($month !== null) {
    // Monat als Name ausgeben
    $dateObj = DateTime::createFromFormat('!n', (int)$month);
    $titleBits[] = $dateObj ? $dateObj->format('F') : sprintf('%02d', $month);
}
if ($category !== null) { $titleBits[] = 'Kategorie: ' . htmlspecialchars($category); }
$pageTitle = empty($titleBits) ? 'Suche' : implode(' / ', $titleBits);


$archive = get_post_archive(is_logged_in());
template_header('Suche – ' . $pageTitle);
?>

<div class="main-content">
    <h2>Suchergebnis: <?= htmlspecialchars($pageTitle) ?></h2>

    <?php if (empty($filtered)){ ?>
        <p>Keine Beiträge gefunden.</p>
    <?php }else{ ?>
        <?php foreach ($filtered as $post){
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
        <small><?= $post['created_at'] ?> 
		<?php if ($visLabel): ?>
				<span style="color:<?= $visColor ?>; margin-left:0.5rem;">
					[ <?= $visLabel ?> ]
				</span>
			<?php endif; ?>
		</small>
		<?php $cats = get_post_categories($post) ?>
		<?php if ($cats): ?>
		<p class="cats">
			<?php foreach ($cats as $c){ ?>
			<a class="cat-badge" href="search.php?category=<?= urlencode($c) ?>"><?= htmlspecialchars($c) ?></a>
		<?php } ?>
		</p>
		<?php endif; ?>
        <?= get_post_preview_uniform($post['content'], PREVIEWLENGTH) ?>
		<p><a class="button" href="<?= e(url_entry($post['id']))?>">Weiterlesen</a></p>
    </article>
    <?php } 
	}?>

    <p><a class="button" href="index.php">Zurück zur Übersicht</a></p>
</div>

<div class="sidebar">
    <h3>Archiv</h3>
    <ul>
    <?php foreach ($archive as $year => $months){ 
        $total = $months['_total'] ?? 0; ?>
        <li>
            <details>
                <summary>
                    <a href="search.php?year=<?= urlencode($year) ?>">
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

/* --------------- Hilfsfunktionen (lokal in search.php) ---------------- */

/**
 * Normalisiert Year/Month aus diversen GET-Varianten.
 * Priorität:
 *  - ym/date (YYYY-MM oder YYYY/MM)
 *  - year/y + month/m
 */
function normalize_year_month(array $get): array {
    $year = null;
	$month = null;

    // ym oder date als "YYYY-MM" / "YYYY/MM"
    $ym = $get['ym'] ?? $get['date'] ?? null;
    if ($ym) {
        $ym = str_replace('/', '-', $ym);
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $ym, $m)) {
            $year = (int)$m[1];
            $month = (int)$m[2];
            if ($month < 1 || $month > 12) $month = null;
            return [$year, $month];
        }
        // nur Jahr?
        if (preg_match('/^(\d{4})$/', $ym, $m)) {
            $year = (int)$m[1];
            return [$year, null];
        }
    }

    // year/y + month/m
    if (isset($get['year']) || isset($get['y'])) {
        $year = (int)($get['year'] ?? $get['y']);
        if ($year < 1970 || $year > 2100) $year = null; // simple sanity-check
    }
    if (isset($get['month']) || isset($get['m'])) {
        $month = (int)($get['month'] ?? $get['m']);
        if ($month < 1 || $month > 12) $month = null;
    }

    return [$year, $month];
}

/**
 * Normalisiert Kategorie-Parameter (category/cat)
 */
function normalize_category(array $get): ?string {
    $cat = $get['category'] ?? $get['cat'] ?? null;
    if ($cat === null)
		return null;
    $cat = trim((string)$cat);
    return $cat === '' ? null : $cat;
}

function norm_int(?string $v): ?int {
    if ($v === null || $v === '' || $v === '-') return null;
    $n = (int)$v;
    return $n > 0 ? $n : null;
}
?>