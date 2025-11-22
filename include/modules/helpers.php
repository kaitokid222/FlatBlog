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

function url_stats(): string {
    return url_script('stats');
}

function url_entry(int $id): string {
    if (PRETTY_URLS) {
        return "/entry/$id";
    }
    if (!empty($_SERVER['ORIG_PATH_INFO']) || !empty($_SERVER['PATH_INFO'])) {
        return "/entry.php/$id";
    }
    return "/entry.php?id=$id";
}

function url_edit(int $id): string {
    if (PRETTY_URLS) {
        return "/edit/$id";
    }
    if (!empty($_SERVER['ORIG_PATH_INFO']) || !empty($_SERVER['PATH_INFO'])) {
        return "/edit.php/$id";
    }
    return "/edit.php?id=$id";
}

function url_download(int $id): string {
    if (PRETTY_URLS) {
        return "/download/$id";
    }
    if (!empty($_SERVER['ORIG_PATH_INFO']) || !empty($_SERVER['PATH_INFO'])) {
        return "/download.php/$id";
    }
    return "/download.php?id=$id";
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

function app_base_path(): string {
    $base = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return ($base === '' || $base === '.') ? '' : $base; // '' wenn im Webroot, sonst z.B. '/blog'
}
function asset(string $path): string {
    return app_base_path() . '/' . ltrim($path, '/');
}

function get_user_ip_slice(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return ip_prefix($ip, 2, 4);
}

/*
 * Gibt einen gekürzten IP-Präfix zurück.
 * - IPv4: $v4_octets Oktette (Default 3 => /24)
 * - IPv6: $v6_hextets Hextets (Default 6 => /96, i.d.R. sinnvoller sind 4 => /64)
 */
function ip_prefix(string $ip, int $v4_octets = 3, int $v6_hextets = 6): string {
    $ip = trim($ip);

    if (str_contains($ip, '%'))
        $ip = explode('%', $ip, 2)[0];

    if (preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $m))
        return ipv4_prefix($m[1], $v4_octets);

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
        return ipv4_prefix($ip, $v4_octets);

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
        return ipv6_prefix($ip, $v6_hextets);
    return '';
}

function ipv4_prefix(string $ip, int $octets): string
{
    $o = max(1, min(4, $octets));
    $p = explode('.', $ip);
    return implode('.', array_slice($p, 0, $o));
}

function ipv6_prefix(string $ip, int $hextets): string
{
    $h = max(1, min(8, $hextets));
    $b = @inet_pton($ip);
    if ($b === false || strlen($b) !== 16)
        return '';
    $k = $h * 16;
    $m = str_repeat("\xFF", intdiv($k, 8));
    $r = $k % 8;
    if ($r > 0)
        $m .= chr(0xFF << (8 - $r) & 0xFF); // Hier wird eine bitmaske aufgebaut
    $m = str_pad($m, 16, "\x00");
    $ma = $b ^ ($b & ~$m);
    $o = [];
    for ($i = 0; $i < 16; $i += 2) {
        $v = (ord($ma[$i]) << 8) | ord($ma[$i + 1]);
        $o[] = dechex($v);
    }
    return implode(':', array_slice($o, 0, $h));
}

function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>