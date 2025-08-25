<?php

// -------- Helper für Open-Graph-Vorschau ---------

/**
 * Erstellt eine verkleinerte Kopie eines Bildes.
 *
 * @param string $src       Absoluter Pfad zum Quellbild
 * @param string $dst       Absoluter Pfad zur Zieldatei
 * @param int    $maxWidth  Maximale Breite
 * @param int    $maxHeight Maximale Höhe
 * @return bool  true bei Erfolg
 */
function resize_image(string $src, string $dst, int $maxWidth, int $maxHeight): bool {
    $info = @getimagesize($src);
    if (!$info) return false;

    [$width, $height] = $info;
    $mime = $info['mime'] ?? '';

    $createMap = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png'  => 'imagecreatefrompng',
        'image/gif'  => 'imagecreatefromgif',
        'image/webp' => 'imagecreatefromwebp',
    ];
    $saveMap = [
        'image/jpeg' => 'imagejpeg',
        'image/png'  => 'imagepng',
        'image/gif'  => 'imagegif',
        'image/webp' => 'imagewebp',
    ];

    $create = $createMap[$mime] ?? null;
    $save   = $saveMap[$mime]   ?? null;
    if (!$create || !$save || !function_exists($create) || !function_exists($save)) {
        return false;
    }

    $srcImg = @$create($src);
    if (!$srcImg) return false;

    $scale = min($maxWidth / $width, $maxHeight / $height, 1);
    $newW  = (int)($width  * $scale);
    $newH  = (int)($height * $scale);

    // Kein Resize nötig → Quelle kopieren
    if ($scale >= 1) {
        imagedestroy($srcImg);
        return @copy($src, $dst);
    }

    $dstImg = imagecreatetruecolor($newW, $newH);

    // Transparenz unterstützen
    if (in_array($mime, ['image/png','image/gif','image/webp'], true)) {
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);
        $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
        imagefilledrectangle($dstImg, 0, 0, $newW, $newH, $transparent);
    }

    imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $width, $height);

    $ok = false;
    switch ($mime) {
        case 'image/jpeg':
            $ok = @$save($dstImg, $dst, 85);
            break;
        case 'image/png':
            $ok = @$save($dstImg, $dst, 6);
            break;
        case 'image/gif':
            $ok = @$save($dstImg, $dst);
            break;
        case 'image/webp':
            $ok = @$save($dstImg, $dst, 85);
            break;
    }

    imagedestroy($srcImg);
    imagedestroy($dstImg);
    return $ok;
}

/**
 * Gibt die URL eines auf Open-Graph-Maße verkleinerten Bildes zurück.
 * Erzeugt die verkleinerte Version bei Bedarf.
 *
 * @param string $abs Absoluter Pfad zum Originalbild
 * @param string $url Relative URL zum Originalbild
 * @return string     Relative URL zur verkleinerten Version
 */
function ensure_og_image(string $abs, string $url): string {
    $targetAbs = preg_replace('/(\.[^.]+)$/', '-og$1', $abs);
    $targetUrl = preg_replace('/(\.[^.]+)$/', '-og$1', $url);

    if (!file_exists($targetAbs)) {
        if (!resize_image($abs, $targetAbs, 1080, 1080)) {
            return $url; // Fallback
        }
    }

    return $targetUrl;
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

?>