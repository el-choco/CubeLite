# 📧 CubeLite Webmail (v1.0.0)

A lightweight, lightning-fast, and self-hosted webmail client in PHP. Inspired by the classic Roundcube "Larry" theme, fully containerized via Docker, and equipped with intelligent IMAP/SMTP auto-discovery, native contact management, and high-performance SQLite caching.

---

## ✨ Core Features

*   🎨 **Classic Roundcube Design:** Clean, professional interface including adjustable split views (horizontal, vertical, list-only), adjustable sidebars, and watermarks.
*   ⚡ **High-Performance Caching:** A local SQLite database stores the folder structure and user settings for blazing-fast load times without constant server requests. Includes "Optimistic UI" updates for instant visual feedback.
*   👥 **Contact Management:** Native support for multiple personal address books, CSV/vCard import, and seamless auto-complete integration when composing emails.
*   🔍 **Auto-Discovery Login:** Log in using just your email and password. The system automatically detects IMAP and SMTP servers for known providers (Google, Outlook, GMX, Yahoo, Apple) based on the domain.
*   🚀 **Native SMTP Delivery:** Direct email sending (including attachments) via SSL/TLS (Port 465/587) – independent of local server MTA services, complete with timeout protection.
*   🗂️ **Complete IMAP Actions:** Reply (with quote), forward, move, delete, mark as spam/read/unread, export emails as `.eml`, or print them.
*   ⚙️ **User Settings:** Configurable identities/signatures (incl. HTML support via TinyMCE), customizable emails per page, and free choice of archive and spam folders.

---

## 🛠️ Zero-Touch Installation

CubeLite is published as a ready-to-use Docker image. You do not need to clone source code or set any manual file permissions.

### Step 1: Create Docker Configuration
Create a `docker-compose.yml` file on your server:

```yaml
version: '3.8'

services:
  cubelite:
    image: paquele/cubelite:latest
    container_name: cubelite
    restart: unless-stopped
    ports:
      - "8887:80"
    volumes:
      - cubelite_db_data:/var/www/html/database
    command: ["/bin/sh", "-c", "chown -R www-data:www-data /var/www/html/database && chmod -R 777 /var/www/html/database && apache2-foreground"]

volumes:
  cubelite_db_data:
```

### Step 2: Start the Container
Run the following command in the directory containing your `docker-compose.yml`:
```bash
docker compose up -d
```
Docker will automatically download the image, set up the required permissions, and initialize the database safely within the Named Volume.

---

## 🌐 Usage

1. Open your web browser and navigate to `http://<your-server-ip>:8887`.
2. Log in with your email address and your (app) password.
3. **Done!** The system automatically creates your profile, fetches the folder tree, and you are ready to go. You can set your signature, quick replies, and archive folder under the **Settings** ⚙️ tab.

---
*Version 1.0.0 – Ready for production use!* 🎉

<br>
<br>

---
---

<br>
<br>

# 📧 CubeLite Webmail (v1.0.0)

Ein leichtgewichtiger, pfeilschneller und selbstgehosteter Webmail-Client in PHP. Inspiriert vom klassischen Roundcube "Larry"-Theme, komplett containerisiert via Docker und ausgestattet mit intelligenter IMAP/SMTP-Auto-Erkennung, nativer Kontaktverwaltung sowie performantem SQLite-Caching.

---

## ✨ Kern-Features

*   🎨 **Klassisches Roundcube-Design:** Aufgeräumte, professionelle Oberfläche inkl. anpassbarer Split-Views (Horizontal, Vertikal, Nur-Liste), anpassbaren Seitenleisten und Wasserzeichen.
*   ⚡ **High-Performance Caching:** Lokale SQLite-Datenbank speichert die Ordnerstruktur und Benutzereinstellungen für extrem schnelle Ladezeiten ohne ständige Server-Anfragen. Inklusive "Optimistic UI"-Updates für sofortiges visuelles Feedback.
*   👥 **Kontaktverwaltung:** Native Unterstützung für mehrere persönliche Adressbücher, CSV/vCard-Import und nahtlose Autovervollständigung beim Verfassen von E-Mails.
*   🔍 **Auto-Discovery Login:** Einloggen nur mit E-Mail und Passwort. Das System erkennt IMAP- und SMTP-Server für bekannte Provider (Google, Outlook, GMX, Yahoo, Apple) automatisch anhand der Domain.
*   🚀 **Nativer SMTP-Versand:** Direkter E-Mail-Versand (inklusive Dateianhängen) über SSL/TLS (Port 465/587) – unabhängig von lokalen Server-MTA-Diensten, komplett mit Timeout-Schutz.
*   🗂️ **Vollständige IMAP-Aktionen:** Antworten (mit Zitat), Weiterleiten, Verschieben, Löschen, als Spam/Gelesen/Ungelesen markieren, E-Mails als `.eml` exportieren oder drucken.
*   ⚙️ **Benutzereinstellungen:** Konfigurierbare Identitäten/Signaturen (inkl. HTML-Support via TinyMCE), anpassbare Anzahl an E-Mails pro Seite und freie Wahl der Archiv- und Spam-Ordner.

---

## 🛠️ Zero-Touch Installation

CubeLite wird als fertiges Docker-Image bereitgestellt. Du musst keinen Quellcode herunterladen oder manuelle Dateiberechtigungen setzen.

### Schritt 1: Docker-Konfiguration erstellen
Erstelle auf deinem Server eine `docker-compose.yml`:

```yaml
version: '3.8'

services:
  cubelite:
    image: paquele/cubelite:latest
    container_name: cubelite
    restart: unless-stopped
    ports:
      - "8887:80"
    volumes:
      - cubelite_db_data:/var/www/html/database
    command: ["/bin/sh", "-c", "chown -R www-data:www-data /var/www/html/database && chmod -R 777 /var/www/html/database && apache2-foreground"]

volumes:
  cubelite_db_data:
```

### Schritt 2: Container starten
Wechsle in das Verzeichnis mit der `docker-compose.yml` und führe aus:
```bash
docker compose up -d
```
Docker lädt das Image automatisch herunter, setzt alle nötigen Schreibrechte und initialisiert die Datenbank sicher innerhalb des Named Volumes.

---

## 🌐 Nutzung

1. Öffne deinen Webbrowser und rufe `http://<deine-server-ip>:8887` auf.
2. Logge dich mit deiner E-Mail-Adresse und deinem (App-)Passwort ein.
3. **Fertig!** Das System legt automatisch dein Profil an, liest die Ordnerstruktur aus und du kannst loslegen. Unter dem Reiter **Einstellungen** ⚙️ kannst du deine Signatur, Schnellantworten und den Archiv-Ordner festlegen.

---
*Version 1.0.0 – Bereit für den produktiven Einsatz!* 🎉