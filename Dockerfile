FROM php:8.2-apache

# Lade das offizielle Helfer-Skript für komplexe PHP-Erweiterungen
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Installiere IMAP und SQLite
RUN install-php-extensions imap pdo_sqlite

# Erlaube .htaccess-Dateien für Sicherheitseinstellungen (AllowOverride All)
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Aktiviere das Rewrite-Modul von Apache
RUN a2enmod rewrite

# KUGELSICHER: Kopiere den kompletten Inhalt des src-Ordners in das Web-Verzeichnis
COPY src/ /var/www/html/

# Erstelle den Datenbank-Ordner und setze alle Rechte
RUN mkdir -p /var/www/html/database \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html \
    && chmod -R 777 /var/www/html/database

EXPOSE 80