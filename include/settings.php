<?php
// Basisverzeichnis für Beiträge
define('CONTENT_DIR', __DIR__ . '/../content/texts/');

// Maximale Länge der Preview (Zeichen)
define('PREVIEWLENGTH', 300);

// Maximale Idlezeit einer Session
define('SESSION_IDLE_LIMIT', 1800);

// Kategorienliste (Komma-getrennt)
define('CATEGORIES_FILE', __DIR__ . '/../content/categories.txt');

// Passwort für den Blog-Owner (für Bearbeiten/Erstellen)
$opw = md5("123");
define('OWNER_PASSWORD', $opw);

// Pfad zur Blacklist
define('BLACKLIST_FILE', __DIR__ . '/../content/blacklist.txt');

// Bild-Upload
define('IMAGE_UPLOAD_DIR', __DIR__ . '/../content/images/');
define('IMAGE_UPLOAD_URL', 'content/images/');

define('ALLOWED_MEDIA_TYPES', [
    'image/jpeg','image/png','image/gif','image/webp',
    'video/mp4'
]);

// Impressum / Kontakt
define('OWNER_NAME',    'David');
define('OWNER_STREET',  'Birkenweg 51');
define('OWNER_ZIP',     '23999');
define('OWNER_CITY',    'Nicht-Lübeck');
define('OWNER_COUNTRY', 'Deutschland');
define('OWNER_PHONE',   '01234 / 666 666');
define('OWNER_EMAIL',   'owner@doesntexist.com');

// Social Links
//define('OWNER_TWITTER', 'https://twitter.com/deinaccount'); // leer lassen, wenn nichts angezeigt werden soll
define('OWNER_TWITTER', '');
//define('OWNER_GITHUB',  'https://github.com/deinaccount/deinprojekt'); // leer lassen, wenn nichts angezeigt werden soll
define('OWNER_GITHUB', '');

define('ALLOW_RSS', false);
define('SITE_TITLE', 'FlatBlog');
define('SITE_DESC', 'A Flat Blog');

?>