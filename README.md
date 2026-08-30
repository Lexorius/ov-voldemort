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

Das Add-on ist eigenständig: **Datenbank, Webserver und Anwendung stecken im
Container.** Es sind keine weiteren Add-ons und keine Vorarbeiten nötig.

1. **Einstellungen → Add-ons → Add-on Store → ⋮ → Repositories** öffnen und
   diese Adresse hinzufügen:

```
https://github.com/Lexorius/ov-voldemort
```

2. In der Liste erscheint *OV-Budget*. Installieren – der erste Build dauert je
   nach Hardware einige Minuten.
3. Optional unter *Konfiguration* den Namen des Ortsverbands und ein
   Wunschpasswort setzen. Man kann aber auch einfach starten.
4. **Starten**, dann **Protokoll** öffnen: dort steht das Startpasswort, sofern
   keines vergeben wurde.
5. „In Seitenleiste anzeigen" einschalten – fertig.

Beim ersten Start richtet sich alles selbst ein: MariaDB wird initialisiert, ein
zufälliges Datenbankpasswort erzeugt, Datenbank und Benutzer angelegt, Schema und
Grunddaten eingespielt und der Administrator erstellt. Spätere Starts erkennen den
Bestand und ändern nichts daran.

Alles Dauerhafte liegt unter `/data`:

```
/data/mysql      Datenbank
/data/uploads    hochgeladene Angebote
/data/sessions   Anmeldesitzungen
/data/dbpass     erzeugtes Datenbankpasswort
```

Das HA-Backup erfasst diesen Ordner vollständig. Wegen `backup: cold` hält der
Supervisor das Add-on währenddessen an, damit die Datenbankdateien in sich
stimmig sind.

### Optionen

| Option | Bedeutung |
|---|---|
| `ov_name` | Name des Ortsverbands, später in der Anwendung änderbar |
| `admin_username` / `admin_password` | erster Zugang, nur beim allerersten Start angelegt. Passwort leer = Zufallspasswort im Protokoll |
| `db_name` | Name der Datenbank (Vorgabe `ovbudget`) |
| `db_host`, `db_port`, `db_user`, `db_password` | **leer lassen.** Nur ausfüllen, wenn statt der mitgelieferten Datenbank eine vorhandene genutzt werden soll |
| `debug` | PHP-Meldungen im Browser, nur zur Fehlersuche |

### Lieber das MariaDB-Add-on verwenden?

Geht auch: im MariaDB-Add-on eine Datenbank `ovbudget` samt Login anlegen und
hier `db_host: core-mariadb`, `db_user` und `db_password` eintragen. Dann startet
der Container seine eigene Datenbank nicht.

### Zugriff ohne Ingress

Für den direkten Aufruf im Heimnetz in der Add-on-Konfiguration unter *Netzwerk*
einen Port für `8099/tcp` vergeben, dann `http://homeassistant.local:8099`.

## Docker ohne Home Assistant

Ein Container, keine weiteren Dienste – die Datenbank ist eingebaut:

```bash
cp .env.example .env      # optional anpassen
docker compose up -d
docker compose logs app   # Startpasswort ablesen
```

Danach auf `http://<host>:8099`. Läuft die App hinter einem Reverse Proxy in einem
Unterverzeichnis, zusätzlich `OVB_BASE_PATH=/budget` setzen.

Unterstützte Umgebungsvariablen: `OVB_DB_HOST`, `OVB_DB_PORT`, `OVB_DB_NAME`,
`OVB_DB_USER`, `OVB_DB_PASS`, `OVB_BASE_PATH`, `OVB_UPLOAD_DIR`, `OVB_INGRESS`,
`OVB_DEBUG`, `OVB_ADMIN_USER`, `OVB_ADMIN_PASS`, `OVB_OV_NAME`, `TZ`.
Ohne `OVB_DB_HOST` startet der Container seine eigene MariaDB.

Sicherung ohne Home Assistant:

```bash
docker compose exec app mariadb-dump -uovbudget -p"$(docker compose exec -T app cat /data/dbpass)" ovbudget > backup.sql
```

## Klassische Installation

### Anforderungen

* PHP 8.1 oder neuer (`pdo_mysql`, `mbstring`; empfohlen `curl` und `fileinfo`)
* MySQL 5.7+ / MariaDB 10.3+
* Webserver mit Document-Root auf `public/`

### Einrichtung

1. Inhalt von `ov-budget/` auf den Server legen, Document-Root auf dessen
   `public/` zeigen lassen.
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

Das Repository ist ein Home-Assistant-Add-on-Repository: oben liegt nur
`repository.yaml`, die Anwendung steckt im Add-on-Ordner.

```
repository.yaml     macht das Repository im Add-on Store nutzbar
docker-compose.yml  Betrieb ohne Home Assistant
ov-budget/          das Add-on – zugleich die vollstaendige Anwendung
  config.yaml, build.yaml, Dockerfile, DOCS.md, CHANGELOG.md, translations/
  docker/rootfs/    nginx, php-fpm, MariaDB und Startskript des Containers
  public/           Webroot: index.php (Front-Controller), install.php, cron.php, assets/
  src/bootstrap.php Laufzeitumgebung
  src/lib/          Datenbank, Auth, Einstellungen, Listen, Wuensche, Aufgaben, Uploads, Divera
  src/pages/        eine Datei je Route (admin/ fuer den Verwaltungsbereich)
  src/cli/setup.php Einrichtung beim Containerstart
  views/            HTML-Templates, views/layout.php als Rahmen
  sql/              schema.sql (Tabellen) und seed.sql (Grunddaten)
  config/           Konfiguration fuer die klassische Installation
  storage/uploads/  hochgeladene Angebote, ausserhalb des Webroots
                    (im Container stattdessen /data/uploads)
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

Als Home-Assistant-Add-on genügt das normale HA-Backup: Datenbank, Angebote und
Sitzungen liegen alle unter `/data` und werden mitgesichert. Das Add-on wird für
die Dauer der Sicherung angehalten (`backup: cold`), damit die Datenbankdateien
konsistent sind.
