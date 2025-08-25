<?php

//function template_header($title = 'Title Issue') {
function template_header($title = 'Title Issue', array $meta = []) {
    $style = '<link rel="stylesheet" href="' . asset('include/style.css') . '">';
    $base = '<base href="' . e(site_url(''), ENT_QUOTES) . '">';

    $desc = $meta['description'] ?? '';
    $url  = $meta['url'] ?? site_url();
    $type = $meta['type'] ?? 'article';
    $img  = $meta['image'] ?? '';

    $metaTags  = '';
    if ($desc !== '') {
        $metaTags .= '    <meta name="description" content="' . e($desc) . '">' . "\n";
        $metaTags .= '    <meta property="og:description" content="' . e($desc) . '">' . "\n";
    }
    $metaTags .= '    <meta property="og:title" content="' . e($title) . '">' . "\n";
    $metaTags .= '    <meta property="og:type" content="' . e($type) . '">' . "\n";
    $metaTags .= '    <meta property="og:url" content="' . e($url) . '">' . "\n";
    if ($img !== '') {
        $metaTags .= '    <meta property="og:image" content="' . e($img) . '">' . "\n";
    }

    echo <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>$title</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	$style
	$base
	$metaTags
</head>
<body>
<header>
    <h1>$title</h1>
</header>
<main class="layout">
HTML;
}

function template_footer() {
    // Login/Logout
    $authLink = '';
    $acpLink  = '';
    if (function_exists('is_logged_in')) {
        if (is_logged_in()) {
            $authLink = '<a href="' . e(url_logout()) .'" title="Logout">??</a>';
            $acpLink  = '<a href="' . e(url_acp()) .'" title="Admin Control Panel">??</a>';
        } else {
            $authLink = '<a href="' . e(url_login()) .'" title="Login">??</a>';
        }
    }

    echo "</main>\n<footer>\n<div class=\"social-icons\">\n";

    // Social-Links nur, wenn gesetzt
    if (!empty(OWNER_TWITTER)) {
        echo '<a href="' . htmlspecialchars(OWNER_TWITTER) . '" target="_blank" rel="noopener" title="X / Twitter">??</a>' . "\n";
    }
    if (!empty(OWNER_GITHUB)) {
        echo '<a href="' . htmlspecialchars(OWNER_GITHUB) . '" target="_blank" rel="noopener" title="GitHub">??</a>' . "\n";
    }
    if (!empty(OWNER_EMAIL)) {
        echo '<a href="mailto:' . htmlspecialchars(OWNER_EMAIL) . '" title="E-Mail">??</a>' . "\n";
    }
	
	if (defined('ALLOW_RSS') && ALLOW_RSS) {
		echo '<a href="' . e(url_rss()) .'" title="RSS-Feed">??</a>' . "\n";
	}
    // Impressum, ACP (nur logged in), Auth
    echo '<a href="' . e(url_impressum()) .'" title="Impressum">??</a>' . "\n";
    echo $acpLink . "\n";
    echo $authLink . "\n";
	echo '<meta name="csrf-token" content="' . htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) . '">' . "\n";
    echo <<<HTML
    </div>
    <p>&copy; 2025 David</p>
</footer>
</body>
</html>
HTML;
}

?>