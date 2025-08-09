<?php
require_once 'include/core.php';

if (!ALLOW_RSS) {
    header('Location: login.php');
    exit;
}

// ---------- Einstellungen (optional in settings.php auslagern) ----------
$SITE_TITLE = defined('SITE_TITLE') ? SITE_TITLE : 'Mein Blog';
$SITE_DESC  = defined('SITE_DESC')  ? SITE_DESC  : 'RSS-Feed';
$SITE_LANG  = 'de-DE';
$FEED_LIMIT = 15;

// Basis-URL bestimmen (wenn keine BASE_URL-Konstante existiert)
function absolute_url(string $path): string {
    if (preg_match('#^https?://#i', $path)) return $path;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    return $scheme . '://' . $host . ($base ? $base : '') . '/' . ltrim($path, '/');
}

// Sichtbare Posts laden und begrenzen
$all = get_all_posts();
$posts = array_values(array_filter($all, fn($p) => strtolower($p['visibility'] ?? 'visible') === 'visible'));
$posts = array_slice($posts, 0, $FEED_LIMIT);

// HTTP-Header
//header('Content-Type: application/rss+xml; charset=UTF-8');
// statt application/rss+xml:
header('Content-Type: text/xml; charset=UTF-8');
// optional (hilft Chrome & Co. gegen "Download"):
header('Content-Disposition: inline; filename="feed.xml"');
// optional, sauberer:
header('X-Content-Type-Options: nosniff');


// Namespaces für content:encoded
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0"
     xmlns:content="http://purl.org/rss/1.0/modules/content/">
  <channel>
    <title><?= htmlspecialchars($SITE_TITLE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <link><?= htmlspecialchars(absolute_url('index.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></link>
    <description><?= htmlspecialchars($SITE_DESC, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></description>
    <language><?= $SITE_LANG ?></language>
    <lastBuildDate><?= date(DATE_RSS) ?></lastBuildDate>
    <ttl>30</ttl>

    <?php foreach ($posts as $post): 
        $link = absolute_url('entry.php?id=' . (int)$post['id']);
        $guid = $link;
        $pub  = date(DATE_RSS, strtotime($post['created_at'] ?? 'now'));

        // Beschreibung: kurze Vorschau in Plaintext
        $previewHtml = get_post_preview_uniform($post['content'], PREVIEWLENGTH);
        $previewTxt  = trim(strip_tags($previewHtml));

        // Vollinhalt als HTML (Markdown gerendert)
        $fullHtml = perform_markdown($post['content']);

        // Optional: erstes Bild als enclosure
        $enclosureUrl = null;
        $enclosureType = null;
        if (function_exists('get_entry_images')) {
            $imgs = get_entry_images((int)$post['id']);
            if (!empty($imgs)) {
                $enclosureUrl = absolute_url($imgs[0]);
                // Typ grob aus Extension ableiten
                $ext = strtolower(pathinfo($imgs[0], PATHINFO_EXTENSION));
                $enclosureType = match ($ext) {
                    'jpg','jpeg' => 'image/jpeg',
                    'png'       => 'image/png',
                    'gif'       => 'image/gif',
                    'webp'      => 'image/webp',
                    default     => null,
                };
            }
        }
    ?>
    <item>
      <title><?= htmlspecialchars($post['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
      <link><?= htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></link>
      <guid isPermaLink="true"><?= htmlspecialchars($guid, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></guid>
      <pubDate><?= $pub ?></pubDate>
      <description><![CDATA[<?= $previewTxt ?>]]></description>
      <?php if (!empty($post['categories']) && is_array($post['categories'])): ?>
        <?php foreach ($post['categories'] as $cat): ?>
          <category><?= htmlspecialchars($cat, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></category>
        <?php endforeach; ?>
      <?php endif; ?>
      <content:encoded><![CDATA[<?= $fullHtml ?>]]></content:encoded>
      <?php if ($enclosureUrl && $enclosureType): ?>
        <enclosure url="<?= htmlspecialchars($enclosureUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" type="<?= $enclosureType ?>" length="0" />
      <?php endif; ?>
    </item>
    <?php endforeach; ?>

  </channel>
</rss>
