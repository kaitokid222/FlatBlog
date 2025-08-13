<?php
require_once 'include/core.php';

/*if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    exit('Method not allowed');
}

if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
    http_response_code(403); // Forbidden
    exit('Invalid CSRF token');
}*/

logout_owner();
header('Location: ' . site_url());
exit;
?>