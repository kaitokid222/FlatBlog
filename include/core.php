<?php

require_once 'settings.php';

if (!defined('BASE_URL')) {
    function _detect_base_url(): string {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
        $scheme = $https ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $base   = ($base === '' || $base === '.') ? '' : $base;
        return $scheme.'://'.$host.$base;
    }
    define('BASE_URL', _detect_base_url());
}

function site_url(string $path = ''): string {
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function redirect_to(string $path = ''): void {
    header('Location: '.site_url($path), true, 302);
    exit;
}

ini_set('expose_php','0');

$isLive = false;

$logDir  = __DIR__;
$logFile = $logDir . '/logs-php.log';

if (!file_exists($logFile)) {
    if (is_writable($logDir)) {
        touch($logFile);
        chmod($logFile, 0660);
    }
}

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

ini_set('html_errors', '0');

require_once __DIR__.'/modules/auth.php';
require_once __DIR__.'/modules/helpers.php';
require_once __DIR__.'/modules/text.php';
require_once __DIR__.'/modules/media.php';
require_once __DIR__.'/modules/content.php';

secure_session_boot();
function secure_session_boot(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Strict', '', $https, true);
    }

    session_name('flatblog_sid');
    session_start();

    _session_validate();
}

function _session_calc_fp(): string {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = get_user_ip_slice();
    $epoch = time();
    $interval = 60 * 60 * 24 * 14;
    $timebucket = floor($epoch / $interval);
    return hash('sha256', $ua . '|' . $ip . '|' . $timebucket);
}

function _session_validate(): void {
    $now = time();
    if (isset($_SESSION['last']) && ($now - (int)$_SESSION['last']) > SESSION_IDLE_LIMIT) {
        $_SESSION = [];
        session_destroy();
        session_start();
    }
    $_SESSION['last'] = $now;

    $fp = _session_calc_fp();
    if (!isset($_SESSION['fp'])) {
        $_SESSION['fp'] = $fp;
    } elseif ($_SESSION['fp'] !== $fp) {
        $_SESSION = [];
        session_destroy();
        session_start();
    }

    if (!isset($_SESSION['regen_at'])) {
        $_SESSION['regen_at'] = $now + 600;
    } elseif ($now >= (int)$_SESSION['regen_at']) {
        session_regenerate_id(true);
        $_SESSION['regen_at'] = $now + 600;
    }
}

$statsFile = dirname(__DIR__) . '/content/stats.txt';
if (!file_exists($statsFile)) {
    @file_put_contents($statsFile, "#date|endpoint|third\n");
    @chmod($statsFile, 0664);
}

$rawUri   = $_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? '/');
$endpoint = canonicalize_uri_for_log($rawUri);

$third = _session_calc_fp();
$line  = sprintf("%s|%s|%s\n", date('c'), $endpoint, $third);
@file_put_contents($statsFile, $line, FILE_APPEND | LOCK_EX);

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function canonicalize_uri_for_log(string $uri, array $keepKeys = ['entry','id','slug','article']): string {
    $parts = parse_url($uri);
    $path  = ($parts['path'] ?? '/') ?: '/';
    $q = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $params);
        $whitelist = [];
        foreach ($keepKeys as $k) {
            if (array_key_exists($k, $params)) $whitelist[$k] = $params[$k];
        }
        if ($whitelist) {
            ksort($whitelist);
            $query = http_build_query($whitelist);
            return $path . '?' . $query;
        }
    }
    return $path;
}

?>