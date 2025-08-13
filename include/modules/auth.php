<?php

function is_logged_in(): bool {
    return !empty($_SESSION['is_owner']);
}

/**
 * Admin-Gate + Security-Header.
 * @param bool $closeSession  Ob session_write_close() direkt nach dem Check aufgerufen wird (default: true).
 */
function loginCheck(bool $closeSession = true): void {
    if (!is_logged_in()){
		header('Location: ' . url_login());
		exit;
	}

    // Falls du unmittelbar danach nichts mehr in $_SESSION schreibst:
    if ($closeSession) {
        session_write_close();
    }

    // Security-Header (Admin-Seiten)
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');

	/*
    // CSP: minimal-streng; pass ggf. an dein Markup an
    // Achtung: Wenn du Inline-CSS/Icons per <style> verwendest, brauchst du 'unsafe-inline' bei style-src.
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; frame-ancestors 'none'; script-src 'self'; style-src 'self'");

    // Bonus: HSTS nur auf HTTPS, sonst zerschießt du lokale Tests
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    if ($https) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }*/
}

function login_owner(): void {
    // frische ID gegen Fixation
    session_regenerate_id(true);
    $_SESSION['is_owner'] = true;

    // Fingerprint/Zeiten aktualisieren
    $_SESSION['fp']   = _session_calc_fp();
    $_SESSION['last'] = time();
    $_SESSION['regen_at'] = time() + 600;
}

function logout_owner(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']
        );
    }
    session_destroy();

    // Sofort frische anonyme Session starten (verhindert „tote“ Session)
    session_start();
    $_SESSION['last'] = time();
}

?>