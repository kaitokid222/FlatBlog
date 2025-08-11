<?php

function get_content_id_from_path(string $path): int {
	// ID finden: ?id=2  oder  /entry.php/2  ODER (zur Not) /entry/2, falls Rewrite aktiv ist
	$id = 0;

	if (isset($_GET['id'])) {
		$id = (int)$_GET['id'];
	} elseif (!empty($_SERVER['PATH_INFO']) && preg_match('#^/(\d+)/?$#', $_SERVER['PATH_INFO'], $m)) {
		$id = (int)$m[1];
	} else {
		// Falls die Anfrage sowieso via Rewrite hier landete (Apache setzt oft REDIRECT_URL/REQUEST_URI)
		$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
		if (preg_match('#/' . $path . '/(\d+)/?$#', $path, $m)) {
			$id = (int)$m[1];
		}
	}
	return $id;
}

// Sichtbarkeit aus Zeile parsen ("Visibility: Visible/Hidden/…")
function parse_visibility_line(string $line): ?string {
    if (preg_match('/^visibility\s*:\s*(.+)$/i', trim($line), $m)) {
        $v = mb_strtolower(trim($m[1]), 'UTF-8');
        if (in_array($v, ['visible','public','on','yes'], true))  return 'visible';
        if (in_array($v, ['hidden','private','off','no'], true)) return 'hidden';
        if (in_array($v, ['draft'], true)) return 'draft';
        return 'visible'; // Fallback
    }
    return null;
}

// Lade alle Beiträge aus dem Verzeichnis (mit optionaler Categories- und Visibility-Zeile)
function get_all_posts(): array {
    $posts = [];
    $files = glob(CONTENT_DIR . '*.txt');
    natsort($files);

    // Für Rückwärtskompatibilität (CSV-Zeile nur akzeptieren, wenn alle Werte gültige Kategorien sind)
    $allValidCats = function_exists('load_categories') ? load_categories() : [];
    $isValidCatsLine = function(string $line) use ($allValidCats): bool {
        $parsed = parse_categories_line($line);
        if (!$parsed) return false;
        if (!$allValidCats) return true; // wenn keine Liste vorhanden ist, akzeptieren
        return empty(array_diff($parsed, $allValidCats));
    };

    foreach ($files as $file) {
        $id = (int)basename($file, '.txt');
        $lines = file($file, FILE_IGNORE_NEW_LINES); // leere Zeilen im Content zulassen
        if (!$lines || count($lines) < 2) continue;

        $title     = array_shift($lines); // 1: Titel
        $timestamp = array_shift($lines); // 2: Timestamp

        $categories = [];
        $visibility = 'visible';

        // Bis zu zwei Metazeilen konsumieren, Reihenfolge egal
        for ($round = 0; $round < 2 && !empty($lines); $round++) {
            $peek = trim($lines[0]);

            if ($peek === '') { array_shift($lines); $round--; continue; } // führende Leerzeilen überspringen

            // 1) Sichtbarkeit?
            $vis = parse_visibility_line($peek);
            if ($vis !== null) {
                $visibility = $vis;
                array_shift($lines);
                continue;
            }

            // 2) Kategorien (mit Label)
            if (preg_match('/^(categories|kategorie|tags)\s*:/i', $peek)) {
                $cats = parse_categories_line($peek);
                if ($cats) {
                    $categories = $cats;
                    array_shift($lines);
                    continue;
                }
            }

            // 3) Kategorien (CSV ohne Label) – nur akzeptieren, wenn Werte plausibel sind
            if (strpos($peek, ',') !== false && $isValidCatsLine($peek)) {
                $categories = parse_categories_line($peek);
                array_shift($lines);
                continue;
            }

            // keine weitere Meta → raus
            break;
        }

        $content = implode("\n", $lines);

        $posts[] = [
            'id'         => $id,
            'title'      => $title,
            'created_at' => $timestamp,
            'content'    => $content,
            'categories' => $categories,   // Array
            'visibility' => $visibility,   // 'visible' | 'hidden'
        ];
    }

    // Nach Datum absteigend
    usort($posts, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
    return $posts;
}

function get_post_archive(bool $includeHidden = false): array {
    $posts = get_all_posts(); // oder get_all_posts_cached()
    $archive = [];

    foreach ($posts as $post) {
		if($includeHidden === false){
			// Nur sichtbare Beiträge zählen
			if (isset($post['visibility']) && strtolower($post['visibility']) !== 'visible') {
				continue;
			}
		}

        $ts = strtotime($post['created_at']);
        if (!$ts) continue;

        $year = date('Y', $ts);
        $monthNum = (int)date('n', $ts);   // 1..12
        $monthName = date('F', $ts);       // z. B. "August"

        if (!isset($archive[$year])) {
            $archive[$year] = ['_total' => 0];
        }
        if (!isset($archive[$year][$monthNum])) {
            $archive[$year][$monthNum] = ['name' => $monthName, 'count' => 0];
        }
        $archive[$year][$monthNum]['count']++;
        $archive[$year]['_total']++;
    }

    // Sortierung: neueste Jahre/Monate zuerst
    krsort($archive);
    foreach ($archive as $y => &$months) {
        $meta = $months['_total']; unset($months['_total']);
        krsort($months);
        $months['_total'] = $meta;
    }
    unset($months);

    return $archive;
}

/**
 * Bestehenden Beitrag aktualisieren (Titel/Content/Kategorien/Sichtbarkeit).
 * - Timestamp bleibt unverändert (aus created_at oder Datei)
 * - Kategorienzeile wird standardisiert in die 3. Zeile geschrieben (optional)
 * - Danach Visibility-Zeile
 * - Alte "Categories:"-Zeile im Content wird entfernt
 * - $visibility: 'visible' | 'hidden' | 'draft' (optional; wenn null -> aus Datei gelesen, sonst Default 'visible')
 */
function update_post(
    int $id,
    string $title,
    string $content,
    array $cats = [],
    ?string $createdAt = null,
    ?string $visibility = null
): bool {
    $filename = CONTENT_DIR . $id . '.txt';
    if (!file_exists($filename)) return false;

    // Datei einlesen (für createdAt/Visibility-Fallbacks)
    $lines = file($filename, FILE_IGNORE_NEW_LINES);
    if (!$lines || count($lines) < 2) return false;

    // Timestamp beibehalten (aus Param) oder aus Datei
    if ($createdAt === null) {
        $createdAt = $lines[1];
    }

    // Vorhandene Visibility aus Datei holen, falls nicht übergeben
    if ($visibility === null) {
        $visibilityFound = 'visible';
        // prüfe die nächsten 2–3 Zeilen auf "Visibility:"
        for ($i = 2; $i < min(5, count($lines)); $i++) {
            $peek = trim($lines[$i] ?? '');
            if ($peek === '') continue;
            if (preg_match('/^visibility\s*:\s*(.+)$/i', $peek, $m)) {
                $v = mb_strtolower(trim($m[1]), 'UTF-8');
                if (in_array($v, ['visible','public','on','yes'], true))  $visibilityFound = 'visible';
                elseif (in_array($v, ['hidden','private','off','no'], true)) $visibilityFound = 'hidden';
                elseif (in_array($v, ['draft','entwurf'], true)) $visibilityFound = 'draft';
                break;
            }
        }
        $visibility = $visibilityFound;
    }

    // Sichtbarkeit validieren
    $visibility = match (mb_strtolower((string)$visibility, 'UTF-8')) {
        'hidden' => 'hidden',
        'draft'  => 'draft',
        default  => 'visible',
    };

    // gültige Kategorien (falls Liste vorhanden)
    $valid = function_exists('load_categories') ? load_categories() : [];
    if ($valid) {
        $cats = array_values(array_intersect(array_map('trim', $cats), $valid));
    } else {
        $cats = array_values(array_filter(array_map('trim', $cats), fn($x) => $x !== ''));
    }

    // Content säubern + pseudonymisieren
    if (function_exists('strip_category_meta_from_content')) {
        $content = strip_category_meta_from_content($content);
    }
    $content = pseudonymize_text($content);

    // Header neu aufbauen: Titel, Timestamp, (Categories), Visibility
    $parts = [$title, $createdAt];

    $catLine = function_exists('categories_to_line')
        ? categories_to_line($cats)
        : (count($cats) ? 'Categories: ' . implode(', ', $cats) : '');
    if ($catLine !== '') {
        $parts[] = $catLine;
    }

    $visLine = 'Visibility: ' . match ($visibility) {
        'hidden' => 'hidden',
        'draft'  => 'draft',
        default  => 'visible',
    };
    $parts[] = $visLine;

    $full = implode("\n", $parts) . "\n" . rtrim($content) . "\n";
    return file_put_contents($filename, $full) !== false;
}

// Ermittle nächste freie ID
function get_next_id() {
    $files = glob(CONTENT_DIR . '*.txt');
    $ids = array_map(function ($f) {
        return (int) basename($f, '.txt');
    }, $files);
    return empty($ids) ? 1 : max($ids) + 1;
}

// löscht den Beitragstext und die Bilder
function delete_post(int $entryId): bool {
    $ok = true;
    $txt = CONTENT_DIR . $entryId . '.txt';
    if (file_exists($txt) && !@unlink($txt)) { $ok = false; }
    delete_entry_images($entryId);
    return $ok;
}

?>