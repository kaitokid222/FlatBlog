# FlatBlog
Minimalistische Low-Tech Blogsoftware

Ein **low-tech**, dateibasiertes Mini-Blog in PHP.  
**Ohne Datenbank**, **ohne Framework**, **ohne Build-Kram** – einfach hochladen und loslegen.

> Ziel: Sofort einsatzbereit, minimalistisch, hackbar.  
> Passt auf Shared Hosting, Raspberry Pi, NAS & Oldschool-Server.

---

## Features

- ✍️ **Beiträge** als Textdateien (`/content/texts/ID.txt`)  
  **Header-Format**:

Titel
YYYY-MM-DD HH:MM:SS
Categories: A, B # optional
Visibility: Visible # Visible | Hidden | Draft
[Leer- oder Content-Zeile]
...Markdown-Content...

- 🖼️ **Bilder** pro Beitrag: `content/images/{ID}-1.{ext}`, `{ID}-2.{ext}`  
Auto-Galerie als Thumbs mit Link zur Vollansicht
- 🏷️ **Kategorien** aus `/content/categories.txt` (CSV) + Badges + Filter
- 👀 **Sichtbarkeit**: Öffentlich / Versteckt / Entwurf
- 🧹 **Pseudonymisierung**: `blacklist.txt` → ersetzt Wörter/Phrasen stabil & case-preserving
- 🔐 **Login/Session** für Erstellen/Bearbeiten/Löschen (kein DB-User nötig)
- 📰 **RSS 2.0 Feed** (`rss.php`) – nur sichtbare Beiträge
- 🗂️ **Archiv** (Jahr/Monat), **Suche**, **Prev/Next/Zufall**
- 🌗 **Responsive Design**, Darkmode, Social-Icons (optional), Impressum
- ⚙️ **ACP-Link** (⚙️) im Footer (nur eingeloggt)

---

## Ordnerstruktur

/index.php, /entry.php, /edit.php, /submit.php, /search.php, /rss.php, /login.php, /logout.php, /acp.php, /admin_blacklist.php, /impressum.php
/include/
    core.php, settings.php, template.php, style.css
/content/
    texts/ # Beiträge (1.txt, 2.txt, ...)
    images/ # Bilder (1-1.jpg, 1-2.png, ...)
    blacklist.txt # Pseudonymisierung
    categories.txt# Kategorienliste


---

## Installation

1. Repo hochladen (oder Dateien kopieren).
2. In `/include/settings.php` anpassen:
   - Pfade (falls nötig)
   - `OWNER_PASSWORD` (empfohlen: Hashing statt Klartext)
   - Social Links (`OWNER_TWITTER`, `OWNER_GITHUB`, `OWNER_EMAIL`)
   - `ALLOW_RSS` (true/false)
   - Impressumsangaben (`OWNER_NAME`, Adresse, etc.)
3. Rechte: `content/` beschreibbar machen (z. B. `0755`/`0775` je nach Hoster).
4. Optional: `categories.txt` anlegen, z. B.: Anekdote,Gedanke

5. Aufrufen: `index.php` – fertig.

---

## Konfiguration (Auszug aus `settings.php`)

- `CONTENT_DIR`, `PREVIEWLENGTH`
- `OWNER_PASSWORD` *(Tipp: Hash verwenden)*
- `BLACKLIST_FILE`, `CATEGORIES_FILE`
- `IMAGE_UPLOAD_DIR`, `IMAGE_UPLOAD_URL`, `ALLOWED_IMAGE_TYPES`
- `OWNER_NAME`, `OWNER_STREET`, `OWNER_ZIP`, `OWNER_CITY`, `OWNER_COUNTRY`, `OWNER_PHONE`, `OWNER_EMAIL`
- `OWNER_TWITTER`, `OWNER_GITHUB`
- `ALLOW_RSS`, optional `SITE_TITLE`, `SITE_DESC`

---

## Sicherheit

- CSRF-Token in Edit-Aktionen
- Upload-Whitelist (MIME/Endungen)
- Session-Login für Adminfunktionen
- **Empfehlung:** `OWNER_PASSWORD` als **Passwort-Hash** speichern (`password_hash()`), nicht Klartext.

---

## Low-Tech-Philosophie

- **Keine Datenbank.**  
Inhalte sind lesbar, versionierbar und portabel (Git-freundlich, Backup = Kopieren).
- **Kein Framework.**  
Reines PHP + einfache Templates, leicht zu verändern.
- **Sofort einsatzbereit.**  
Auf jedem 08/15-PHP-Hoster oder lokal (XAMPP/LAMP) lauffähig.

---

## Roadmap (optional)

- Volltextsuche über Titel + Inhalt
- Paginierung auf der Startseite
- Kommentar-Modul (file-basiert)
- Admin-Liste mit Filtern (Kategorie/Sichtbarkeit)
- SEO-Meta (OG/Twitter Cards)
- Soft-Delete (Papierkorb)
- Mehrsprachigkeit

---

## Lizenz

MIT License – do whatever, just keep the copyright.
