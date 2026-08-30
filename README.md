# OV-Budget – Pseudo-Budgetverwaltung für einen THW-Ortsverband

Kleine, handytaugliche Webanwendung (PHP + MySQL, ohne Framework) für die interne
Beschaffungsplanung eines Ortsverbands:

* **Wünsch dir was** – Fachgruppen und Zugführer tragen Bedarfe ein, alle priorisieren gemeinsam
* **Aufgaben** – ToDos für den OV, einzelne Fachgruppen, Funktionen oder Personen
* **Budget** – Töpfe je Haushaltsjahr, Auslastung gegen die offenen Wünsche
* **Divera 24/7** – Formulare abrufen und deren Einträge als Wünsche übernehmen
* **Verwaltung** – Benutzer, Rollen sowie *alle* Auswahllisten und Texte frei konfigurierbar

> Die Zahlen sind eine interne Planungshilfe – bewusst „pseudo“, keine offizielle Haushaltsstelle.

Drei Betriebsarten: als **Home-Assistant-Add-on**, als **Docker-Container** oder
klassisch auf einem **Webserver**.

## Home-Assistant-Add-on

Voraussetzung: Home Assistant OS oder Supervised und das offizielle **MariaDB-Add-on**.

1. Im MariaDB-Add-on unter *Konfiguration* die Datenbank ergänzen:

```yaml
databases:
  - homeassistant
  - ovbudget
```

MariaDB neu starten.

2. Dieses Verzeichnis nach `/addons/ov-budget` kopieren – etwa per Samba- oder
   Terminal-Add-on:

```bash
git clone https://github.com/Lexorius/ov-voldemort.git /addons/ov-budget
```

3. **Einstellungen → Add-ons → Add-on Store → ⋮ → Neu laden.** Unter „Lokale Add-ons"
   erscheint *OV-Budget*. Installieren (der erste Build dauert ein paar Minuten).
4. Unter *Konfiguration* mindestens `ov_name` setzen, optional `admin_password`.
   Bleibt das Passwort leer, erzeugt der erste Start eines und schreibt es ins
   Protokoll des Add-ons.
5. Starten, dann **Protokoll** öffnen und das Startpasswort notieren.
6. „In Seitenleiste anzeigen" einschalten – die Anwendung läuft über Ingress, es
   muss also kein Port nach außen geöffnet werden.

Die Zugangsdaten zur Datenbank holt sich das Add-on über den Supervisor-Dienst
`mysql` selbst. Nur für eine **externe** Datenbank sind `db_host`, `db_user` und
`db_password` auszufüllen.

Persistent unter `/data` liegen die hochgeladenen Angebote und die Sitzungen; die
eigentlichen Daten stehen in der MariaDB und gehören in deren Sicherung.

### Optionen

| Option | Bedeutung |
|---|---|
| `db_name` | Name der Datenbank (Vorgabe `ovbudget`) |
| `admin_username` / `admin_password` | erster Zugang, nur beim allerersten Start angelegt |
| `ov_name` | Name des Ortsverbands, später in der Anwendung änderbar |
| `db_host`, `db_port`, `db_user`, `db_password` | nur für eine externe Datenbank |
| `debug` | PHP-Meldungen im Browser, nur zur Fehlersuche |

### Zugriff ohne Ingress

Für den direkten Aufruf im Heimnetz in der Add-on-Konfiguration unter *Netzwerk*
einen Port für `8099/tcp` vergeben, dann `http://homeassistant.local:8099`.

## Docker ohne Home Assistant

```bash
cp .env.example .env      # Passwörter anpassen
docker compose up -d
docker compose logs app   # Startpasswort ablesen
```

Danach auf `http://<host>:8099`. Läuft die App hinter einem Reverse Proxy in einem
Unterverzeichnis, zusätzlich `OVB_BASE_PATH=/budget` setzen.

Unterstützte Umgebungsvariablen: `OVB_DB_HOST`, `OVB_DB_PORT`, `OVB_DB_NAME`,
`OVB_DB_USER`, `OVB_DB_PASS`, `OVB_BASE_PATH`, `OVB_UPLOAD_DIR`, `OVB_INGRESS`,
`OVB_DEBUG`, `OVB_ADMIN_USER`, `OVB_ADMIN_PASS`, `OVB_OV_NAME`, `TZ`.

Beim Start richtet `src/cli/setup.php` alles ein: Konfiguration schreiben, auf die
Datenbank warten, Schema und Grunddaten einspielen (wiederholbar, ohne bestehende
Daten zu überschreiben) und beim ersten Mal den Administrator anlegen. Der
Einrichtungsassistent `install.php` wird im Container nicht mitgeliefert.

## Klassische Installation

### Anforderungen

* PHP 8.1 oder neuer (`pdo_mysql`, `mbstring`; empfohlen `curl` und `fileinfo`)
* MySQL 5.7+ / MariaDB 10.3+
* Webserver mit Document-Root auf `public/`

### Einrichtung

1. Dateien auf den Server legen, Document-Root auf `public/` zeigen lassen.
2. Leere Datenbank samt Benutzer anlegen.
3. Schreibrechte setzen:

```bash
chmod 750 config storage storage/uploads
chown -R www-data:www-data config storage
```

4. Im Browser `https://…/install.php` aufrufen. Der Assistent legt die Tabellen an,
   spielt die Grunddaten ein, erstellt den ersten Administrator und schreibt `config/config.php`.
5. **`public/install.php` anschließend löschen.**

Alternativ von Hand: `config/config.example.php` nach `config/config.php` kopieren, anpassen und

```bash
mysql -u ov_budget -p ov_budget < sql/schema.sql
mysql -u ov_budget -p ov_budget < sql/seed.sql
```

einspielen. Danach einen Administrator anlegen (Passwort-Hash z.B. per
`php -r 'echo password_hash("...", PASSWORD_DEFAULT);'`).

### Läuft die App in einem Unterverzeichnis?

`base_path` in `config/config.php` setzen, z.B. `'/budget'`.

### Apache / nginx

Liegt der Document-Root – etwa bei einfachem Shared Hosting – **nicht** auf `public/`,
schützen die mitgelieferten `.htaccess`-Dateien die Verzeichnisse `config/`, `src/`,
`views/`, `sql/` und `storage/`. Unter nginx gibt es kein `.htaccess`; dort gehört
in den Server-Block:

```nginx
root /var/www/ov-budget/public;
index index.php;

location / { try_files $uri $uri/ /index.php$is_args$args; }

location ~ ^/(config|src|views|sql|storage)/ { deny all; }

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

## Verzeichnisse

```
config/           Konfiguration (config.php wird nicht versioniert)
public/           Webroot: index.php (Front-Controller), install.php, cron.php, assets/
src/bootstrap.php Laufzeitumgebung
src/lib/          Datenbank, Auth, Einstellungen, Listen, Wünsche, Aufgaben, Uploads, Divera
src/pages/        eine Datei je Route (admin/ für den Verwaltungsbereich)
src/cli/setup.php Einrichtung beim Containerstart
views/            HTML-Templates, views/layout.php als Rahmen
sql/              schema.sql (Tabellen) und seed.sql (Grunddaten)
storage/uploads/  hochgeladene Angebote – liegt bewusst außerhalb des Webroots
                  (im Container stattdessen /data/uploads)

Dockerfile, config.yaml, build.yaml, translations/  Home-Assistant-Add-on
docker/rootfs/    nginx, php-fpm und Startskript des Containers
docker-compose.yml  Betrieb ohne Home Assistant
```

Ausgeliefert werden Uploads ausschließlich über `index.php?p=download&id=…` – also nur
für angemeldete Benutzer und mit `nosniff`-Header.

## Rollen

| Rolle | Darf |
|---|---|
| `user` (Mitglied) | Wünsche anlegen und die eigenen bearbeiten, abstimmen, kommentieren, Aufgaben im eigenen Zuständigkeitsbereich bearbeiten |
| `leitung` | zusätzlich: alle Wünsche bearbeiten, Status und Priorität setzen, Budgettöpfe pflegen, alle Aufgaben verwalten |
| `admin` | zusätzlich: Benutzerverwaltung, Auswahllisten, Einstellungen, Divera-Anbindung, Protokoll |

Ob Mitglieder Aufgaben anlegen oder Status ändern dürfen, ist in den Einstellungen umschaltbar.

## Was ist konfigurierbar?

**Verwaltung → Auswahllisten** – Fachgruppen, Funktionen, Kategorien, Dringlichkeiten,
Status (Wünsche und Aufgaben), Mengeneinheiten, Anlagen-Typen. Je Eintrag: Bezeichnung,
Farbe, Reihenfolge, Gewicht (steuert die Sortierung), Vorgabewert und – bei Status – ob er
als „abgeschlossen“ gilt. Einträge, die noch verwendet werden, lassen sich auf *inaktiv*
setzen, statt sie zu löschen.

**Verwaltung → Einstellungen** – Name der Anwendung und des OV, Akzentfarbe, Fuß- und
Einleitungstexte, Modulbezeichnungen („Wünsch dir was“ heißt bei euch anders? Kein Problem),
Haushaltsjahr, MwSt-Satz, Warnschwelle der Budgetauslastung, Pflichtfelder, ab welchem
Betrag ein Angebot verpflichtend ist, Upload-Grenzen und erlaubte Dateitypen, Abstimmung
und Stimmenzahl, Session-Laufzeit und Sperre nach Fehlversuchen.

**Eigene Zusatzfelder für Wünsche** – in den Einstellungen unter `wunsch_extra_felder`,
eine Zeile je Feld:

```
inventarnummer|Inventarnummer|text
ersatz_fuer|Ersatz für|text
schulung_noetig|Schulung nötig|bool
```

Typen: `text`, `textarea`, `number`, `bool`, `date`. Die Felder erscheinen sofort im
Formular und auf der Detailseite.

## Divera 24/7

Divera liefert je nach Endpunkt und Tarif unterschiedlich aufgebautes JSON. Deshalb ist
nichts fest verdrahtet:

1. **Verwaltung → Einstellungen → Divera 24/7**: Anbindung aktivieren, Basis-URL,
   Accesskey (System-Benutzer), Pfade und Übergabeart (`?accesskey=…` oder Bearer-Header)
   eintragen. Die Standardwerte (`/v2/forms`, `/v2/forms/{form_id}/entries`) passen zur
   üblichen v2-API; weicht eure Instanz ab, einfach den Pfad ändern.
2. **Verwaltung → Divera 24/7 → „Formulare aus Divera abrufen“**: Die Antwort wird tolerant
   nach einer Liste von Datensätzen durchsucht. Passende Formulare einbinden.
3. **Feldzuordnung**: „Formularfelder abrufen“ liest die vorhandenen Einträge und sammelt
   daraus die Feldnamen. Danach wird je Wunschfeld (Bezeichnung, Anzahl, Nettobetrag,
   Dringlichkeit, Fachgruppe, nice to have …) der Name des Divera-Feldes ausgewählt.
   Fachgruppe und Dringlichkeit werden über Bezeichnung bzw. Schlüssel der Auswahllisten
   zugeordnet; Nicht-Treffer fallen auf die Vorgabe des Formulars zurück.
   **Nicht zugeordnete Divera-Felder gehen nicht verloren** – sie werden gesammelt an die
   Beschreibung angehängt.
4. **Vorschau** zeigt, was entstehen würde, **Jetzt importieren** legt die Wünsche an.
   Bereits importierte Einträge erkennt die App an Formular- und Eintrags-ID und
   überspringt sie.

Automatischer Abruf: in den Einstellungen `divera_cron_token` setzen, beim Formular
„automatisch importieren“ ankreuzen und einrichten:

```bash
curl -s "https://DEINE-DOMAIN/cron.php?token=DEIN_TOKEN"
```

Auf der Konsole geht es auch ohne Token: `php public/cron.php`.

## Sicherheit

* Passwörter als `password_hash()` (bcrypt/argon2, automatischer Rehash beim Anmelden)
* CSRF-Token für jedes POST-Formular
* Bremse gegen Passwortraten (Anzahl und Sperrdauer einstellbar)
* Startpasswörter müssen beim ersten Anmelden geändert werden
* durchgängig vorbereitete Statements, Ausgabe HTML-escaped
* Uploads mit Endungs- und Größenprüfung, Ablage außerhalb des Webroots unter zufälligem Namen
* Änderungsprotokoll unter *Verwaltung → Protokoll*

## Datensicherung

```bash
mysqldump -u ov_budget -p ov_budget > backup-$(date +%F).sql
tar czf uploads-$(date +%F).tar.gz storage/uploads
```

Als Home-Assistant-Add-on erfasst das normale HA-Backup den Ordner `/data` des
Add-ons (Angebote und Sitzungen) mit. **Die Datenbank liegt im MariaDB-Add-on** –
also darauf achten, dass auch dieses im Backup enthalten ist.
