<?php

// ~Jeder liebt Hacks .htaccess Umgebungsvariable wird nur gesetzt
// wenn die Datei auch ausgewertet wurde. Rewrite wird nur gesetzt
// dem Laden des Moduls. Es können aber auch Dinge schieflaufen. So
// 85% Bulletproof. Deal with it oder setze Pretty Urls selbst true oder false.
function RewriteMostLikelyActive(): bool {
	$endsWith = function(string $haystack, string $needle): bool {
        if (function_exists('str_ends_with')) 
			return str_ends_with($haystack, $needle);
        $len = strlen($needle);
        return $len === 0 ? true : (substr($haystack, -$len) === $needle);
    };
	$r = null;
	$h = $_SERVER['HTACCESS_CANARY'] ?? $_SERVER['REDIRECT_HTACCESS_CANARY'] ?? null;
	if($h === '1')
		$r = $_SERVER['REWRITE_CANARY'] ?? $_SERVER['REDIRECT_REWRITE_CANARY'] ?? null;
	if ($h === '1' && $r !== '1')
        foreach ($_SERVER as $k => $v)
            if (strpos($k, 'REDIRECT_') === 0 && $endsWith($k, 'REWRITE_CANARY') && $v === '1') {
                $r = '1';
                break;
			}
    return $r === '1';
}

// Titel & Beschreibung
define('SITE_TITLE', 'FlatBlog');
define('SITE_DESC',  'A Flat Blog');

// Basis-URL (inkl. Unterordner, ohne abschließenden Slash):
// Wenn auskommentiert -> Auto-Detection
// define('BASE_URL', 'https://example.com/blog');
// define('BASE_URL', 'localhost');

// Pretty URLs (Apache/Nginx Rewrite)
$b = RewriteMostLikelyActive();         // oder manuell überschreiben:
// define('PRETTY_URLS', false);
define('PRETTY_URLS', $b);

// Impressum / Kontakt
define('OWNER_NAME',    'David');
define('OWNER_STREET',  'Birkenweg 51');
define('OWNER_ZIP',     '23999');
define('OWNER_CITY',    'Nicht-Lübeck');
define('OWNER_COUNTRY', 'Deutschland');
define('OWNER_PHONE',   '01234 / 666 666');
define('OWNER_EMAIL',   'owner@doesntexist.com');

// Social Links (leer lassen = nichts anzeigen)
define('OWNER_TWITTER', '');
define('OWNER_GITHUB',  '');

// RSS einschalten?
define('ALLOW_RSS', false);

// Maximale Länge der Preview (Zeichen)
define('PREVIEWLENGTH', 300);

// Maximale Idlezeit einer Session (Sekunden)
define('SESSION_IDLE_LIMIT', 1800);

// Passwort für den Blog-Owner (für Bearbeiten/Erstellen)
// Hinweis: md5 ist schwach
$opw = md5("123");
define('OWNER_PASSWORD', $opw);

// Bild-Upload
define('IMAGE_UPLOAD_DIR', __DIR__ . '/../content/images/');
define('IMAGE_UPLOAD_URL', 'content/images/');

// Erlaubte Media-Typen
define('ALLOWED_MEDIA_TYPES', [
    'image/jpeg','image/png','image/gif','image/webp',
    'video/mp4'
]);

// Basisverzeichnis für Beiträge
define('CONTENT_DIR', __DIR__ . '/../content/texts/');

// Kategorienliste (Komma-getrennt)
define('CATEGORIES_FILE', __DIR__ . '/../content/categories.txt');

// Pfad zur Blacklist
define('BLACKLIST_FILE', __DIR__ . '/../content/blacklist.txt');

?>