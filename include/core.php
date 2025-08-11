<?php

require_once 'settings.php';

if (!defined('BASE_URL')) {
    function _detect_base_url(): string {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
        $scheme = $https ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $base   = ($base === '' || $base === '.') ? '' : $base; // '' im Webroot, sonst z.B. '/blog'
        return $scheme.'://'.$host.$base;
    }
    define('BASE_URL', _detect_base_url());
}

// Baut absolute URLs aus einem Pfad
function site_url(string $path = ''): string {
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

// Bequemer Redirect
function redirect_to(string $path = ''): void {
    header('Location: '.site_url($path), true, 302);
    exit;
}

// PHP-Version nicht im Header verraten
ini_set('expose_php','0');

// Fehler-Handling-Modus
$isLive = false; // true = Produktion, false = Debug

// Log-Datei-Pfad festlegen
$logDir  = __DIR__; // include/
$logFile = $logDir . '/logs-php.log';

// Falls Log-Datei nicht existiert → anlegen (mit Schreibrechten für PHP)
if (!file_exists($logFile)) {
    if (is_writable($logDir)) {
        touch($logFile);
        chmod($logFile, 0660);
    }
}

// Fehlerbehandlung setzen
if ($isLive) {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');

    if (is_writable($logFile)) {
        ini_set('log_errors', '1');
        ini_set('error_log', $logFile);
    } else {
        ini_set('log_errors', '0');
    }
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');

    if (is_writable($logFile)) {
        ini_set('log_errors', '1');
        ini_set('error_log', $logFile);
    }
}
// Kein HTML im Log
ini_set('html_errors', '0');

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

function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

require_once __DIR__.'/modules/auth.php';
require_once __DIR__.'/modules/helpers.php';
require_once __DIR__.'/modules/text.php';
require_once __DIR__.'/modules/media.php';
require_once __DIR__.'/modules/content.php';

?>