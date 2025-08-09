<?php
require_once 'include/core.php';
require_once 'include/template.php';

template_header("Impressum");
?>
<div class="main-content">
    <h2>Impressum</h2>

    <p><strong>Angaben gemäß § 5 TMG:</strong></p>
    <p>
        <?= htmlspecialchars(OWNER_NAME) ?><br>
        <?= htmlspecialchars(OWNER_STREET) ?><br>
        <?= htmlspecialchars(OWNER_ZIP . ' ' . OWNER_CITY) ?><br>
        <?= htmlspecialchars(OWNER_COUNTRY) ?>
    </p>

    <p><strong>Kontakt:</strong></p>
    <p>
        Telefon: <?= htmlspecialchars(OWNER_PHONE) ?><br>
        E-Mail: <a href="mailto:<?= htmlspecialchars(OWNER_EMAIL) ?>"><?= htmlspecialchars(OWNER_EMAIL) ?></a>
    </p>

    <p><strong>Verantwortlich für den Inhalt nach § 55 Abs. 2 RStV:</strong></p>
    <p>
        <?= htmlspecialchars(OWNER_NAME) ?><br>
        <?= htmlspecialchars(OWNER_STREET) ?><br>
        <?= htmlspecialchars(OWNER_ZIP . ' ' . OWNER_CITY) ?>
    </p>

    <h3>Haftungsausschluss</h3>
    <p>
        Die Inhalte dieser Webseite wurden mit größter Sorgfalt erstellt. 
        Für die Richtigkeit, Vollständigkeit und Aktualität der Inhalte übernehme ich jedoch keine Gewähr.
    </p>

    <h3>Kunst</h3>
    <p>
        Alle Personen und Ereignisse in diesem Blog sind frei erfunden. 
        Ähnlichkeiten mit realen Personen wären sehr bedauerlich, aber wahrscheinlich deine Schuld, weil du dich zu wichtig nimmst.
    </p>

    <h3>Datenschutz</h3>
    <p>
        Diese Webseite speichert oder verarbeitet keinerlei personenbezogene Daten. 
        Es werden keine Cookies gesetzt, keine Logfiles ausgewertet und keine Drittanbieter-Skripte geladen. 
        Willkommen in der digitalen Steinzeit – genießen Sie es.
    </p>

    <p><a class="button" href="index.php">← Zurück zur Übersicht</a></p>
</div>
<?php
template_footer();
?>
