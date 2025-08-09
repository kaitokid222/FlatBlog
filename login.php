<?php
require_once 'include/core.php';
require_once 'include/template.php';

// Falls schon eingeloggt, gleich weiterleiten
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if ($password === OWNER_PASSWORD) {
        login_owner();
        header('Location: index.php');
        exit;
    } else {
        $error = "Falsches Passwort.";
    }
}

template_header("Login");
?>
<div class="main-content">
    <h2>Login</h2>
    <?php if ($error): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="post">
        <p>
            <input type="password" name="password" placeholder="Passwort" required>
        </p>
        <p>
            <button type="submit">Einloggen</button>
        </p>
    </form>
    <p><a class="button" href="index.php">Zurück zur Übersicht</a></p>
</div>
<?php
template_footer();
?>
