<?php
require_once 'settings.php';

// Session immer früh starten
/*if (session_status() === PHP_SESSION_NONE) {
    session_start();
}*/

// ---- Secure Session Bootstrap ----
function secure_session_boot(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    // Streng & nur Cookies (keine SID in URLs)
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    // Cookie-Flags
    //ini_set('session.cookie_secure', $https ? '1' : '0'); // nur über HTTPS senden
    ini_set('session.cookie_httponly', '1');              // JS hat keine Finger dran

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,           // Session-Cookie
            'path'     => '/',
            'domain'   => '',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Strict',       // Egal? Dann ruhig 'Lax'
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Strict', '', $https, true);
    }

    session_name('flatblog_sid');      // eigener Name, weniger Konflikte
    session_start();

    _session_validate();               // Idle-Timeout & Fingerprint prüfen
}

function _session_calc_fp(): string {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR']      ?? '';
    // IP grob abschneiden (geringer Drift, aber keine harte Bindung)
    if (strpos($ip, ':') !== false) {   // IPv6
        $ip = substr($ip, 0, 4);
    } else {                            // IPv4
        $ip = preg_replace('/(\d+\.\d+)\..*/', '$1', $ip ?? '');
    }
    return hash('sha256', $ua . '|' . $ip);
}

function _session_validate(): void {
    $now = time();
    // 1) Inaktivität-Timeout
    if (isset($_SESSION['last']) && ($now - (int)$_SESSION['last']) > SESSION_IDLE_LIMIT) {
        $_SESSION = [];
        session_destroy();
        session_start(); // frische anonyme Session
    }
    $_SESSION['last'] = $now;

    // 2) Fingerprint (UA + grobe IP)
    $fp = _session_calc_fp();
    if (!isset($_SESSION['fp'])) {
        $_SESSION['fp'] = $fp;
    } elseif ($_SESSION['fp'] !== $fp) {
        // Session geklaut/gewandert → sofort invalidieren
        $_SESSION = [];
        session_destroy();
        session_start();
    }

    // 3) Periodisch ID erneuern (alle 10 Min)
    if (!isset($_SESSION['regen_at'])) {
        $_SESSION['regen_at'] = $now + 600;
    } elseif ($now >= (int)$_SESSION['regen_at']) {
        session_regenerate_id(true);
        $_SESSION['regen_at'] = $now + 600;
    }
}

// Boot sofort ausführen
secure_session_boot();

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__.'/modules/auth.php';
require_once __DIR__.'/modules/text.php';
require_once __DIR__.'/modules/media.php';
require_once __DIR__.'/modules/content.php';

?>