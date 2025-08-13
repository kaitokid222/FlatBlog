<?php
/**
 * Basis-Helfer für statische Skripte mit optional Pretty URLs.
 * $name ist der Name ohne .php
 */
function url_script(string $name): string {
    if (defined('PRETTY_URLS') && PRETTY_URLS) {
        return '/' . rawurlencode($name) . '/';
    }

    if (!empty($_SERVER['ORIG_PATH_INFO']) || !empty($_SERVER['PATH_INFO'])) {
        return '/' . rawurlencode($name) . '.php/';
    }

    return '/' . rawurlencode($name) . '.php';
}

// Einzelskripte (nur Wrapper für url_script)
function url_acp(): string {
    return url_script('acp');
}

function url_login(): string {
    return url_script('login');
}

function url_logout(): string {
    return url_script('logout');
}

function url_submit(): string {
    return url_script('submit');
}

function url_entrylist(): string {
    return url_script('entrylist');
}

function url_rss(): string {
    return url_script('rss');
}

function url_impressum(): string {
    return url_script('impressum');
}

function url_admin_blacklist(): string {
    return url_script('admin_blacklist');
}

function url_entry(int $id): string {
    // 1) Admin kann pretty URLs erzwingen
    if (PRETTY_URLS) {
        return "/entry/$id";
    }
    // 2) PATH_INFO verfÃ¼gbar? Dann wenigstens /entry.php/2
    if (!empty($_SERVER['ORIG_PATH_INFO']) || !empty($_SERVER['PATH_INFO'])) {
        return "/entry.php/$id";
    }
    // 3) Fallback: klassisch
    return "/entry.php?id=$id";
}

function url_edit(int $id): string {
    // 1) Admin kann pretty URLs erzwingen
    if (PRETTY_URLS) {
        return "/edit/$id";
    }
    // 2) PATH_INFO verfÃ¼gbar? Dann wenigstens /entry.php/2
    if (!empty($_SERVER['ORIG_PATH_INFO']) || !empty($_SERVER['PATH_INFO'])) {
        return "/edit.php/$id";
    }
    // 3) Fallback: klassisch
    return "/edit.php?id=$id";
}

function url_search(?int $year=null, ?int $month=null, ?string $category=null, ?int $page=null): string {
    // Platzhalter für "egal"
    $y = $year  ?? '-';
    $m = $month ?? '-';
    $c = ($category !== null && $category !== '') ? rawurlencode($category) : '-';

    if (PRETTY_URLS) {
        $u = "/search/$y/$m/$c";
        if (!empty($page) && $page > 1) { $u .= "/page/$page"; }
        return $u;
    }

    if (!empty($_SERVER['ORIG_PATH_INFO']) || !empty($_SERVER['PATH_INFO'])) {
        $u = "/search.php/$y/$m/$c";
        if (!empty($page) && $page > 1) { $u .= "/page/$page"; }
        return $u;
    }

    $qs = [];
    if ($year   !== null)               $qs['year']     = (int)$year;
    if ($month  !== null)               $qs['month']    = (int)$month;
    if ($category !== null && $category !== '') $qs['category'] = $category;
    if (!empty($page) && $page > 1)     $qs['page']     = (int)$page;

    $query = $qs ? ('?' . http_build_query($qs)) : '';
    return "/search.php{$query}";
}

// settings.php oder helpers.php
function app_base_path(): string {
    $base = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return ($base === '' || $base === '.') ? '' : $base; // '' wenn im Webroot, sonst z.B. '/blog'
}
function asset(string $path): string {
    return app_base_path() . '/' . ltrim($path, '/');
}

?>