<?php
require_once 'settings.php';

// Session immer früh starten
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in(): bool {
    return !empty($_SESSION['is_owner']);
}

function login_owner(): void {
    $_SESSION['is_owner'] = true;
}

function logout_owner(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
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

function get_post_archive(): array {
    $posts = get_all_posts(); // oder get_all_posts_cached()
    $archive = [];

    foreach ($posts as $post) {
		if(!is_logged_in()){
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


function get_post_preview_uniform(string $content, int $maxChars = PREVIEWLENGTH): string {
    $text = markdown_to_plaintext($content);

    // Whitespace normalisieren
    $text = preg_replace('/\s+/u', ' ', trim($text));

    // Schon kurz genug?
    if (mb_strlen($text, 'UTF-8') <= $maxChars) {
        return '<p>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
    }

    // Auf Wortgrenze kürzen
    $snippet = mb_substr($text, 0, $maxChars, 'UTF-8');
    $lastSpace = mb_strrpos($snippet, ' ', 0, 'UTF-8');
    if ($lastSpace !== false && $lastSpace > ($maxChars * 0.6)) {
        $snippet = mb_substr($snippet, 0, $lastSpace, 'UTF-8');
    }
    $snippet .= ' …';

    return '<p>' . htmlspecialchars($snippet, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
}

/**
 * Sehr einfache Markdown→Text-Reduktion für Previews.
 * Entfernt Formatierung, behält sinnvollen Text.
 */
function markdown_to_plaintext(string $md): string {
    // Codeblöcke komplett entfernen
    $md = preg_replace('/```[\s\S]*?```/u', ' ', $md);

    // Inline-Code entfernen (Inhalt behalten)
    $md = preg_replace('/`([^`]+)`/u', '$1', $md);

    // Bilder: Alt-Text behalten
    $md = preg_replace('/!\[([^\]]*)\]\([^)]+\)/u', '$1', $md);

    // Links: Link-Text behalten
    $md = preg_replace('/\[(.*?)\]\((?:[^)]+)\)/u', '$1', $md);

    // Fett/Kursiv/Strikethrough
    $md = preg_replace('/\*\*([^*]+)\*\*/u', '$1', $md);
    $md = preg_replace('/\*([^*]+)\*/u', '$1', $md);
    $md = preg_replace('/__([^_]+)__/u', '$1', $md);
    $md = preg_replace('/_([^_]+)_/u', '$1', $md);
    $md = preg_replace('/~~([^~]+)~~/u', '$1', $md);

    // Überschriften-/Quote-/Listen-Marker entfernen
    $md = preg_replace('/^#{1,6}\s*/mu', '', $md);
    $md = preg_replace('/^>\s?/mu', '', $md);
    $md = preg_replace('/^\s*[-*+]\s+/mu', '', $md);
    $md = preg_replace('/^\s*\d+\.\s+/mu', '', $md);

    // HR entfernen
    $md = preg_replace('/^\s*(?:-{3,}|\*{3,}|_{3,})\s*$/mu', ' ', $md);

    return $md;
}


function get_post_preview(string $content, int $lines = 7): string {
    // In Zeilen aufteilen
    $allLines = preg_split("/\r\n|\n|\r/", trim($content));
    $previewLines = [];

    foreach ($allLines as $line) {
        $previewLines[] = $line;
        if (count($previewLines) >= $lines) break;
    }

    // Falls Artikel mehr Zeilen hat → Ellipsis anfügen
    if (count($allLines) > $lines) {
        $previewLines[] = '...';
    }

    // Wieder als String zusammenfügen
    $previewText = implode("\n", $previewLines);

    // Markdown parsen
    return perform_markdown($previewText);
}



// Neuen Beitrag als Textdatei speichern
/*function save_post($title, $content) {
    $nextId = get_next_id();
    $filename = CONTENT_DIR . $nextId . '.txt';
    $timestamp = date('Y-m-d H:i:s');
    $fullContent = $title . "\n" . $timestamp . "\n" . $content;
    file_put_contents($filename, $fullContent);
}*/
function save_post(string $title, string $content, array $cats = [], string $visibility = 'visible'): int {
    $nextId   = get_next_id();
    $filename = CONTENT_DIR . $nextId . '.txt';
    $timestamp = date('Y-m-d H:i:s');

    // Kategorien validieren (falls Liste vorhanden)
    $valid = function_exists('load_categories') ? load_categories() : [];
    if ($valid) {
        $cats = array_values(array_intersect(array_map('trim', $cats), $valid));
    } else {
        $cats = array_values(array_filter(array_map('trim', $cats), fn($x) => $x !== ''));
    }

    // Content bereinigen (alte Categories-Meta am Anfang entfernen)
    if (function_exists('strip_category_meta_from_content')) {
        $content = strip_category_meta_from_content($content);
    }

    // Headerzeilen
    $parts = [$title, $timestamp];

    $catLine = function_exists('categories_to_line')
        ? categories_to_line($cats)
        : (count($cats) ? 'Categories: ' . implode(', ', $cats) : '');

    if ($catLine !== '') {
        $parts[] = $catLine;
    }

    // Visibility-Zeile
    $vis = match (strtolower($visibility)) {
        'hidden' => 'hidden',
        'draft'  => 'draft',
        default  => 'visible',
    };
    $parts[] = 'Visibility: ' . $vis;

    // Datei schreiben
    $full = implode("\n", $parts) . "\n" . rtrim($content) . "\n";
    file_put_contents($filename, $full);

    return $nextId;
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

function perform_markdown(string $text): string {
    // 1) Codeblöcke sichern (``` ... ```)
    $placeholders = [];
    $text = preg_replace_callback('/```([\s\S]*?)```/m', function ($m) use (&$placeholders) {
        $key = '[[[CODEBLOCK_' . count($placeholders) . ']]]';
        // NICHT escapen – erst beim Einfügen in <pre><code>
        $placeholders[$key] = $m[1];
        return $key;
    }, $text);

    // 2) HTML escapen
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // 3) Zeilenweise verarbeiten für Block-Elemente
    $lines = preg_split("/\r\n|\n|\r/", $text);
    $html = [];
    $inUl = false; $inOl = false; $inBlockquote = false;

    $closeLists = function() use (&$html, &$inUl, &$inOl) {
        if ($inUl) { $html[] = '</ul>'; $inUl = false; }
        if ($inOl) { $html[] = '</ol>'; $inOl = false; }
    };
    $closeQuote = function() use (&$html, &$inBlockquote) {
        if ($inBlockquote) { $html[] = '</blockquote>'; $inBlockquote = false; }
    };

    foreach ($lines as $line) {
        $trim = ltrim($line);

        // Horizontal Rule
        if (preg_match('/^(?:-{3,}|\*{3,}|_{3,})$/', $trim)) {
            $closeLists(); $closeQuote();
            $html[] = '<hr>';
            continue;
        }

        //  # Überschriften
        if (preg_match('/^(#{1,6})\s+(.*)$/', $trim, $m)) {
            $closeLists(); $closeQuote();
            $level = strlen($m[1]);
            $content = $m[2];
            $html[] = "<h{$level}>{$content}</h{$level}>";
            continue;
        }

        // Blockquote
        if (preg_match('/^>\s?(.*)$/', $trim, $m)) {
            $closeLists();
            if (!$inBlockquote) { $html[] = '<blockquote>'; $inBlockquote = true; }
            $html[] = '<p>' . $m[1] . '</p>';
            continue;
        } else {
            $closeQuote();
        }

        // Unordered list
        if (preg_match('/^[-\*]\s+(.*)$/', $trim, $m)) {
            if ($inOl) { $html[] = '</ol>'; $inOl = false; }
            if (!$inUl) { $html[] = '<ul>'; $inUl = true; }
            $html[] = '<li>' . $m[1] . '</li>';
            continue;
        }

        // Ordered list
        if (preg_match('/^\d+\.\s+(.*)$/', $trim, $m)) {
            if ($inUl) { $html[] = '</ul>'; $inUl = false; }
            if (!$inOl) { $html[] = '<ol>'; $inOl = true; }
            $html[] = '<li>' . $m[1] . '</li>';
            continue;
        }

        // Leere Zeile -> Blocktrenner
        if ($trim === '') {
            $closeLists(); $closeQuote();
            $html[] = '';
            continue;
        }

        // Normale Absatzzeile
        $closeLists(); // Listen enden vor normalen Absätzen
        $html[] = '<p>' . $line . '</p>';
    }
    // offenes Zeug schließen
    $closeLists(); $closeQuote();

    $html = implode("\n", $html);

    // 4) Inline-Formatierungen (nach Blockbau)
    // Links [text](url)
    $html = preg_replace_callback('/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/', function ($m) {
        $txt = $m[1];
        $url = $m[2];
        return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $txt . '</a>';
    }, $html);

    // Fettschrift **text**
    $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
    // Kursiv *text*
    $html = preg_replace('/(?<!\*)\*(?!\s)(.+?)(?<!\s)\*(?!\*)/s', '<em>$1</em>', $html);
    // Inline-Code `code`
    $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);

    // 5) Codeblöcke wieder einfügen
    foreach ($placeholders as $key => $code) {
        $safe = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = str_replace($key, "<pre><code>{$safe}</code></pre>", $html);
    }

    return $html;
}

function load_blacklist(): array {
    if (!file_exists(BLACKLIST_FILE)) return [];
    $lines = file(BLACKLIST_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $map = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        [$needle, $altsCsv] = array_pad(explode('|', $line, 2), 2, '');
        $needle = trim($needle);
        if ($needle === '') continue;
        $alts = array_values(array_filter(array_map('trim', explode(',', (string)$altsCsv))));
        if (!empty($alts)) {
            $map[$needle] = $alts;
        }
    }
    return $map;
}

/** Blacklist im Rohformat speichern (Textarea-Inhalt) */
function save_blacklist_raw(string $raw): void {
    $dir = dirname(BLACKLIST_FILE);
    if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
    file_put_contents(BLACKLIST_FILE, $raw);
}

/**
 * Pseudonymisierung mit:
 * - Wortgrenzen (Unicode)
 * - Case-Preservation (ALLCAPS, Titlecase, lowercase)
 * - stabiler Auswahl der Alternative pro Original (crc32-hashbasiert)
 * - multiword-Keys möglich (Regex-escaped)
 */
function pseudonymize_text(string $text): string {
    $map = load_blacklist();
    if (!$map) return $text;

    // längere Keys zuerst
    uksort($map, fn($a, $b) => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

    foreach ($map as $needle => $alts) {
        // Erlaubt: am Ende optional 's' oder 'es' (Genitiv/Plural)
        $pattern = '/(?<!\p{L})' . preg_quote($needle, '/') . '(?:s|es)?(?!\p{L})/iu';

        $text = preg_replace_callback($pattern, function($m) use ($alts, $needle) {
            $orig = $m[0];

            // Endung extrahieren (falls vorhanden)
            $suffix = '';
            if (mb_strtolower(mb_substr($orig, -2, null, 'UTF-8')) === 'es') {
                $suffix = mb_substr($orig, -2, null, 'UTF-8');
                $base = mb_substr($orig, 0, mb_strlen($orig, 'UTF-8') - 2, 'UTF-8');
            } elseif (mb_strtolower(mb_substr($orig, -1, null, 'UTF-8')) === 's') {
                $suffix = mb_substr($orig, -1, null, 'UTF-8');
                $base = mb_substr($orig, 0, mb_strlen($orig, 'UTF-8') - 1, 'UTF-8');
            } else {
                $base = $orig;
            }

            // Alternative stabil wählen
            $idx = abs(crc32(mb_strtolower($needle, 'UTF-8'))) % count($alts);
            $rep = $alts[$idx];

            // Case-Preservation für Grundwort
            if (mb_strtoupper($base, 'UTF-8') === $base) {
                $rep = mb_strtoupper($rep, 'UTF-8');
            } else {
                $first = mb_substr($base, 0, 1, 'UTF-8');
                if ($first === mb_strtoupper($first, 'UTF-8')) {
                    $rep = mb_strtoupper(mb_substr($rep, 0, 1, 'UTF-8'), 'UTF-8')
                         . mb_substr($rep, 1, null, 'UTF-8');
                }
            }

            return $rep . $suffix;
        }, $text);
    }

    return $text;
}

function handle_entry_image_upload(array $files, int $entryId): array {
    $results = [];

    // Zielordner sicherstellen
    if (!is_dir(IMAGE_UPLOAD_DIR)) {
        @mkdir(IMAGE_UPLOAD_DIR, 0775, true);
    }

    // MIME → Dateiendung
    $mimeToExt = [
        'image/jpeg' => '.jpg',
        'image/png'  => '.png',
        'image/gif'  => '.gif',
        'image/webp' => '.webp',
        'video/mp4'  => '.mp4',
    ];

    for ($i = 1; $i <= 2; $i++) {
        $inputName = "image{$i}";
        if (!isset($files[$inputName]) || $files[$inputName]['error'] !== UPLOAD_ERR_OK) {
            continue;
        }
        $file = $files[$inputName];

        // MIME bestimmen (Videos funktionieren hiermit, getimagesize nicht)
        $mime = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $file['tmp_name']) ?: null;
                finfo_close($finfo);
            }
        }
        // Fallbacks
        if (!$mime && function_exists('mime_content_type')) {
            $mime = @mime_content_type($file['tmp_name']) ?: null;
        }
        if (!$mime && !empty($file['type'])) {
            $mime = $file['type']; // aus $_FILES – nicht perfekt, aber besser als nichts
        }

        // Zulässig?
        if (!$mime || !in_array($mime, ALLOWED_MEDIA_TYPES, true)) {
            continue;
        }

        // Endung mappen
        $ext = $mimeToExt[$mime] ?? null;
        if (!$ext) {
            continue;
        }

        // Vorherige Dateien dieses Slots (andere Endungen) löschen
        foreach (glob(IMAGE_UPLOAD_DIR . "{$entryId}-{$i}.*") as $old) {
            @unlink($old);
        }

        // Zielpfad
        $filename = "{$entryId}-{$i}{$ext}";
        $target   = rtrim(IMAGE_UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            continue;
        }

        $results[] = rtrim(IMAGE_UPLOAD_URL, '/').'/'.$filename;
    }

    return $results;
}

function get_entry_images(int $entryId): array {
    // Akzeptierte Endungen (Bilder + Videos)
    $exts = ['jpg','jpeg','png','gif','webp','mp4'];
    $files = [];

    for ($i = 1; $i <= 2; $i++) {
        foreach ($exts as $ext) {
            $abs = IMAGE_UPLOAD_DIR . "{$entryId}-{$i}.{$ext}";
            if (file_exists($abs)) {
                $files[] = [
                    'abs'  => $abs,
                    'url'  => IMAGE_UPLOAD_URL . basename($abs),
                    'type' => ($ext === 'mp4') ? 'video' : 'image'
                ];
                break; // pro Slot nur 1 Datei
            }
        }
    }
    return $files;
}

function find_entry_image(int $entryId, int $index): ?array {
    foreach (glob(IMAGE_UPLOAD_DIR . "{$entryId}-{$index}.*") as $abs) {
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $url = IMAGE_UPLOAD_URL . basename($abs);
        return [
            'abs'  => $abs,
            'url'  => $url,
            'type' => ($ext === 'mp4') ? 'video' : 'image'
        ];
    }
    return null;
}

function delete_entry_image(int $entryId, int $index): bool {
    $deleted = false;
    foreach (glob(IMAGE_UPLOAD_DIR . "{$entryId}-{$index}.*") as $abs) {
        if (@unlink($abs)) $deleted = true;
    }
    return $deleted;
}

// löscht alle Bilder zu einem Beitrag (entryId-1.*, entryId-2.*)
function delete_entry_images(int $entryId): int {
    $count = 0;
    foreach ([1,2] as $idx) {
        foreach (glob(IMAGE_UPLOAD_DIR . "{$entryId}-{$idx}.*") as $abs) {
            if (@unlink($abs)) { $count++; }
        }
    }
    return $count;
}

// löscht den Beitragstext und die Bilder
function delete_post(int $entryId): bool {
    $ok = true;
    $txt = CONTENT_DIR . $entryId . '.txt';
    if (file_exists($txt) && !@unlink($txt)) { $ok = false; }
    delete_entry_images($entryId);
    return $ok;
}

// Kategorien-Datei lesen → ['Anekdote','Gedanke', ...]
function load_categories(): array {
    if (!file_exists(CATEGORIES_FILE)) return [];
    $raw = trim(file_get_contents(CATEGORIES_FILE));
    if ($raw === '') return [];
    $arr = array_map('trim', explode(',', $raw));
    // Duplikate raus
    $arr = array_values(array_unique(array_filter($arr, fn($x) => $x !== '')));
    return $arr;
}

// Kategorien aus Content ziehen (Meta-Zeile "Categories: ...")
function extract_categories_from_content(string $content): array {
    $lines = preg_split("/\r\n|\n|\r/", $content);
    foreach ($lines as $line) {
        if (preg_match('/^(categories|kategorie|tags)\s*:\s*(.+)$/i', trim($line), $m)) {
            $list = array_map('trim', explode(',', $m[2]));
            return array_values(array_filter($list, fn($x) => $x !== ''));
        }
        // nur erste Zeilen checken, später abbrechen
        if (trim($line) !== '') break;
    }
    return [];
}

// Kategorien-Zeile im Content setzen/aktualisieren
function upsert_categories_in_content(string $content, array $cats): string {
    // nur gültige, eindeutige aus der globalen Liste
    $valid = load_categories();
    $cats = array_values(array_intersect($cats, $valid));
    $metaLine = count($cats) ? 'Categories: ' . implode(', ', $cats) : '';

    $lines = preg_split("/\r\n|\n|\r/", $content);

    // Falls erste nicht-leere Zeile bereits Categories/Tags ist → ersetzen/entfernen
    $replaced = false;
    for ($i = 0; $i < min(3, count($lines)); $i++) {
        if (isset($lines[$i]) && preg_match('/^(categories|kategorie|tags)\s*:/i', trim($lines[$i]))) {
            if ($metaLine) {
                $lines[$i] = $metaLine;
            } else {
                // keine Kategorien → Meta-Zeile entfernen
                array_splice($lines, $i, 1);
            }
            $replaced = true;
            break;
        }
        if (isset($lines[$i]) && trim($lines[$i]) !== '') break;
    }

    if (!$replaced && $metaLine) {
        array_unshift($lines, $metaLine);
    }

    return rtrim(implode("\n", $lines)) . "\n";
}

// "Categories: Anekdote, Gedanke" ODER "Anekdote,Gedanke" → ['Anekdote','Gedanke']
function parse_categories_line(string $line): array {
    $line = trim($line);
    if ($line === '') return [];
    if (preg_match('/^(categories|kategorie|tags)\s*:\s*(.+)$/i', $line, $m)) {
        $line = $m[2]; // nur den rechten Teil nehmen
    }
    $parts = array_map('trim', explode(',', $line));
    $parts = array_values(array_filter($parts, fn($x) => $x !== ''));
    return $parts;
}

// Array → standardisierte Zeile "Categories: A, B"
function categories_to_line(array $cats): string {
    $cats = array_values(array_filter(array_map('trim', $cats), fn($x) => $x !== ''));
    return count($cats) ? 'Categories: ' . implode(', ', $cats) : '';
}

function get_post_categories(array $post): array {
    if (!empty($post['categories']) && is_array($post['categories'])) {
        return $post['categories'];
    }
    // Fallback: aus Content herausziehen (Altformat)
    return extract_categories_from_content($post['content']);
}

// Entfernt eine evtl. vorhandene "Categories:" / "Kategorie:" / "Tags:"-Zeile
// falls sie am Anfang des Content-Blocks steht.
function strip_category_meta_from_content(string $content): string {
    $lines = preg_split("/\r\n|\n|\r/", $content);

    // bis zu den ersten paar Zeilen checken (leerzeilen tolerant)
    for ($i = 0; $i < min(3, count($lines)); $i++) {
        $t = trim($lines[$i] ?? '');
        if ($t === '') continue; // führende Leerzeilen überspringen

        if (preg_match('/^(categories|kategorie|tags)\s*:/i', $t)) {
            // diese Metazeile entfernen
            array_splice($lines, $i, 1);
        }
        break; // nach erster nicht-leerer Zeile abbrechen
    }
    // trailing newline erlauben
    return rtrim(implode("\n", $lines)) . "\n";
}

?>
