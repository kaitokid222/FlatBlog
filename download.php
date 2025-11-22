<?php
require_once 'include/core.php';
require_once 'include/template.php';

if (!is_logged_in()) {
    template_header("Nicht eingeloggt");
    echo "<div class='main-content'><p>Das ist nicht erlaubt.</p></div>";
    template_footer();
    exit;
}

$id = get_content_id_from_path('download');
$posts = get_all_posts();
$post = null;

foreach ($posts as $p) {
    if ($p['id'] == $id) {
        $post = $p;
        break;
    }
}

if (!$post) {
    template_header("Beitrag nicht gefunden");
    echo "<div class='main-content'><p>Der Beitrag wurde nicht gefunden.</p></div>";
    template_footer();
    exit;
}

$content = $post['content'];
$title = preg_replace('/[^a-zA-Z0-9]/', '', $post['title']);
$title = $title ?: 'Unbenannt'; // something went wrong

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="beitrag_' . $title . '.txt"');
echo $content;
exit;
?>
