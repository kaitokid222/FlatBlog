<?php

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