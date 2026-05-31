<?php

// -------- Helper fuer Open-Graph-Vorschau ---------

/**
 * Erstellt eine verkleinerte Kopie eines Bildes.
 *
 * @param string $src       Absoluter Pfad zum Quellbild
 * @param string $dst       Absoluter Pfad zur Zieldatei
 * @param int    $maxWidth  Maximale Breite
 * @param int    $maxHeight Maximale Hoehe
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

    // Kein Resize noetig -> Quelle kopieren
    if ($scale >= 1) {
        imagedestroy($srcImg);
        return @copy($src, $dst);
    }

    $dstImg = imagecreatetruecolor($newW, $newH);

    // Transparenz unterstuetzen
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
 * Gibt die URL eines auf Open-Graph-Masse verkleinerten Bildes zurueck.
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

function get_entry_media_index(string $path, int $entryId): ?int {
    $base = basename($path);
    if (!preg_match('/^' . preg_quote((string)$entryId, '/') . '-(\d+)(?:-og)?\.[^.]+$/', $base, $m)) {
        return null;
    }
    return (int)$m[1];
}

function normalize_entry_media_uploads(array $files): array {
    $uploads = [];

    foreach ($files as $inputName => $file) {
        if (!is_array($file) || !isset($file['error'])) {
            continue;
        }

        $targetIndex = null;
        if (preg_match('/^image(\d+)$/', (string)$inputName, $m)) {
            $targetIndex = (int)$m[1];
        }

        if (is_array($file['error'])) {
            foreach ($file['error'] as $idx => $error) {
                $uploads[] = [
                    'name' => $file['name'][$idx] ?? '',
                    'type' => $file['type'][$idx] ?? '',
                    'tmp_name' => $file['tmp_name'][$idx] ?? '',
                    'error' => $error,
                    'size' => $file['size'][$idx] ?? 0,
                    'target_index' => null,
                ];
            }
            continue;
        }

        $file['target_index'] = $targetIndex;
        $uploads[] = $file;
    }

    return $uploads;
}

function get_next_entry_media_index(int $entryId): int {
    $max = 0;
    foreach (glob(IMAGE_UPLOAD_DIR . "{$entryId}-*.*") ?: [] as $abs) {
        if (preg_match('/-og\.[^.]+$/', $abs)) {
            continue;
        }
        $idx = get_entry_media_index($abs, $entryId);
        if ($idx !== null) {
            $max = max($max, $idx);
        }
    }
    return $max + 1;
}

function handle_entry_image_upload(array $files, int $entryId): array {
    $results = [];

    // Zielordner sicherstellen
    if (!is_dir(IMAGE_UPLOAD_DIR)) {
        @mkdir(IMAGE_UPLOAD_DIR, 0775, true);
    }

    $mimeToExt = [
        'image/jpeg' => '.jpg',
        'image/png'  => '.png',
        'image/gif'  => '.gif',
        'image/webp' => '.webp',
        'video/mp4'  => '.mp4',
    ];

    $nextIndex = get_next_entry_media_index($entryId);

    foreach (normalize_entry_media_uploads($files) as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            continue;
        }

        // MIME bestimmen; Videos funktionieren hiermit, getimagesize nicht.
        $mime = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $file['tmp_name']) ?: null;
                finfo_close($finfo);
            }
        }
        if (!$mime && function_exists('mime_content_type')) {
            $mime = @mime_content_type($file['tmp_name']) ?: null;
        }
        if (!$mime && !empty($file['type'])) {
            $mime = $file['type'];
        }

        if (!$mime || !in_array($mime, ALLOWED_MEDIA_TYPES, true)) {
            continue;
        }

        $ext = $mimeToExt[$mime] ?? null;
        if (!$ext) {
            continue;
        }

        $targetIndex = (int)($file['target_index'] ?? 0);
        if ($targetIndex > 0) {
            foreach (["{$entryId}-{$targetIndex}.*", "{$entryId}-{$targetIndex}-og.*"] as $pattern) {
                foreach (glob(IMAGE_UPLOAD_DIR . $pattern) ?: [] as $old) {
                    @unlink($old);
                }
            }
            if ($targetIndex >= $nextIndex) {
                $nextIndex = $targetIndex + 1;
            }
        } else {
            $targetIndex = $nextIndex++;
        }

        $filename = "{$entryId}-{$targetIndex}{$ext}";
        $target   = rtrim(IMAGE_UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            continue;
        }

        $results[] = rtrim(IMAGE_UPLOAD_URL, '/').'/'.$filename;
    }

    return $results;
}

function get_entry_images(int $entryId): array {
    $files = [];

    foreach (glob(IMAGE_UPLOAD_DIR . "{$entryId}-*.*") ?: [] as $abs) {
        if (preg_match('/-og\.[^.]+$/', $abs)) {
            continue;
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp','mp4'], true)) {
            continue;
        }
        $index = get_entry_media_index($abs, $entryId);
        if ($index === null) {
            continue;
        }
        $files[] = [
            'index' => $index,
            'abs'  => $abs,
            'url'  => IMAGE_UPLOAD_URL . basename($abs),
            'type' => ($ext === 'mp4') ? 'video' : 'image'
        ];
    }

    usort($files, fn($a, $b) => $a['index'] <=> $b['index']);
    return $files;
}

function find_entry_image(int $entryId, int $index): ?array {
    foreach (glob(IMAGE_UPLOAD_DIR . "{$entryId}-{$index}.*") ?: [] as $abs) {
        if (preg_match('/-og\.[^.]+$/', $abs)) {
            continue;
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $url = IMAGE_UPLOAD_URL . basename($abs);
        return [
            'index' => $index,
            'abs'  => $abs,
            'url'  => $url,
            'type' => ($ext === 'mp4') ? 'video' : 'image'
        ];
    }
    return null;
}

function delete_entry_image(int $entryId, int $index): bool {
    $deleted = false;
    foreach (["{$entryId}-{$index}.*", "{$entryId}-{$index}-og.*"] as $pattern) {
        foreach (glob(IMAGE_UPLOAD_DIR . $pattern) ?: [] as $abs) {
            if (@unlink($abs)) $deleted = true;
        }
    }
    return $deleted;
}

// Loescht alle Medien zu einem Beitrag (entryId-1.*, entryId-2.*, ...)
function delete_entry_images(int $entryId): int {
    $count = 0;
    foreach (glob(IMAGE_UPLOAD_DIR . "{$entryId}-*.*") ?: [] as $abs) {
        if (@unlink($abs)) { $count++; }
    }
    return $count;
}

?>
