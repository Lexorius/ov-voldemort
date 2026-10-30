# OV-Multitool – das Werkzeug für den THW-Ortsverband

*Bis Fassung 1.48 hieß die Anwendung OV-Budget.*

Handytaugliche Webanwendung (PHP 8.1+, MariaDB/MySQL, ohne Framework) für die
Verwaltung eines THW-Ortsverbands – von der Beschaffungsplanung über
Fahrzeuge, Funkgeräte und Zähler bis zu Besprechungen und Veranstaltungen.
Gedacht als **Home-Assistant-Add-on**, das alles mitbringt (Datenbank,
Webserver, Anwendung in einem Container); läuft aber genauso als
**Docker-Container** oder klassisch auf einem **Webserver**.

> Die Zahlen sind eine interne Planungshilfe – bewusst „pseudo", keine
> offizielle Haushaltsstelle. Privates Projekt, kein offizielles Angebot des THW.

## ⚠ Sicherheitshinweis: PHP2Shell (Oktober 2026)

Am 21.10.2026 hat das PHP-Projekt zwei kritische Lücken geschlossen
([CVE-2026-109618, CVE-2026-109619](https://www.php.net/archive/2026.php#2026-10-21-5)):
unauthentifizierte Codeausführung über Multipart-Anfragen, ausgelöst beim
Einlesen der Anfrage, vor jedem Skript und unabhängig von der Anwendung.
Behoben ab **PHP 8.5.12, 8.4.27, 8.3.36 und 8.2.35**; 8.1 und älter bekommen
keine Releases mehr.

**Was das für OV-Multitool heißt**

* **Add-on:** PHP kommt aus den Alpine-Paketen des Home-Assistant-Basisimages.
  Ab Fassung 2.1.51.0 prüft der Bauvorgang die PHP-Fassung, lädt bei Bedarf
  das Paket aus Alpine edge nach und warnt, wenn beides zu alt ist; die
  Verwaltungsseite zeigt die laufende Fassung mit Urteil. Bis ein gepatchtes
  PHP da ist, greifen die vom PHP-Projekt empfohlenen Maßnahmen in der
  php.ini (kein Sitzungs-Upload-Fortschritt, keine Backtraces, keine
  PHP-Speichergrenze, Anfragen auf 48 MB begrenzt, ein FPM-Worker je
  Anfrage) – sie mindern, ersetzen aber kein Update. **Bis die Meldung in der
  Verwaltung grün ist, keinen Port des Add-ons aus dem Internet erreichbar
  machen**; über die Home-Assistant-Oberfläche kommen nur Angemeldete an
  die Anwendung.
* **Connector:** läuft mit dem PHP des Hosters. *Verwaltung → Connectoren*
  zeigt dessen Fassung und schlägt Alarm, wenn sie verwundbar ist. Beheben
  kann das nur der Hoster – dort die PHP-Fassung auf 8.4.27 oder neuer
  umstellen.
* **Eigene Installation (Docker, Webserver):** PHP auf eine gepatchte Fassung
  bringen und alle PHP-Worker neu starten.

Sobald Alpine die gepatchte Fassung liefert, baut die nächste Add-on-Fassung
sie automatisch ein. Einzelheiten in [ov-budget/DOCS.md](ov-budget/DOCS.md)
unter „Sicherheitslücke PHP2Shell".

## Was drin ist

### Planen und Geld

* **Wünsch dir was** – Fachgruppen und Zugführer tragen Bedarfe ein (Bezeichnung,
  Anzahl, Betrag, Dringlichkeit, Fachgruppe, Kategorie, Frist, eigene
  Zusatzfelder), alle priorisieren gemeinsam per Abstimmung und Kommentar.
  Angebote als Anlage, CSV-Export, Übernahme aus Divera-Formularen.
  **„Freigegeben, bitte bestellen"** mit Bestellberechtigungen je Rolle und
  Funktion, Betragsgrenze und Vier-Augen-Prinzip; **Bestellungen** fassen
  mehrere Wünsche zusammen.
* **Budget** – Jahresbudget und Budgettöpfe, Ausgaben und Einnahmen (Einsätze,
  technische Hilfeleistung, Spenden …), Übersicht nach Kategorie und Monat mit
  Grafik, Stand je Ausgabe (bezahlt, gebucht, geplant). **Objektkonten** als
  zweite Auswertung: mehrere Kategorien laufen auf ein Konto, die Statistik
  nach Kategorien bleibt daneben bestehen. **Auslagen**: welcher Helfer
  vorgestreckt hat, wann es zur Regionalstelle weiterging und wann erstattet
  wurde – die Liste zeigt, wer noch auf sein Geld wartet.
* **Aufgaben** – ToDos für den Ortsverband, Fachgruppen, Funktionen oder
  Personen; mit Fälligkeit erscheinen sie im Kalender.

### Termine und Leute

* **Kalender** – alle Termine an einem Ort: eigene Termine für sich, die
  Fachgruppe, eine Funktion oder den OV, dazu Besprechungen, Veranstaltungen,
  Fristen, fällige Aufgaben und Budget-Stichtage. Feiertage des Bundeslands,
  Kalender aus Home Assistant. **Je Termin**: mehrere Fahrzeuge, mehrere Plätze
  aus den Stell- und Lagerplätzen plus freier Ort, mehrere Fachgruppen,
  Instandsetzungsaufträge (Werkstatttermine), Kontakte, THW-Dienststellen und
  Dateien wie Einladungen. **Doppelbuchungen** von Fahrzeugen werden farbig
  markiert und beim Speichern gemeldet. Terminseite als Zusammenfassung mit
  Druckansicht und **„Navigation starten"** fürs Handy. Startseite mit
  Zwei-Wochen-Übersicht und den in der Woche verplanten Fahrzeugen.
* **Newsletter** – fertige PDF-Newsletter an Kontakte, Verteiler und freie
  Adressen, zu einem Zeitpunkt oder sofort, in Portionen; Betreff und Text
  vor dem Versand änderbar, **Abmeldelink über den Connector**, eine
  **Sperrliste aus Prüfsummen** statt Adressen, KI-Zusammenfassung des PDFs.
  **Nur für interne Newsletter** – Adressen nur mit Zustimmung der
  Empfänger, siehe [Newsletter](#newsletter).
* **Protokoll per E-Mail** – das Besprechungsprotokoll als **PDF**, im
  Multitool selbst erzeugt, an Kontakte oder ganze Verteiler aus dem
  Kontaktmodul; Betreff und Text vor dem Versand änderbar.
* **Besprechungen** – Talking Points im Themenspeicher, Tagesordnung mit
  Zeitplan, Anmerkungen und Diskussion je Punkt, Themenarchiv, Aufgaben aus
  der Besprechung, Bezüge zu Terminen, Veranstaltungen, Fahrzeugen und
  Funkgeräten, Budgetübersicht als Tagesordnungspunkt, wiederkehrende
  Termine, Anwesenheit, Protokoll zum Drucken. Themen über Divera einreichen,
  Status-Rückmeldung an Divera.
* **Veranstaltungen** – Termin, Ort, Art (Ausbildung, Übung, Helferversammlung,
  Feier …), Budgettopf und geplante gegen tatsächliche Kosten, mehrere Plätze,
  Dateien, Gästeliste aus den Kontakten oder nur eine Teilnehmerzahl,
  **Einladung per kurzer Adresse** mit Zu-/Absage, Begleitern und Vertretung
  (verschlüsselt über den Connector), Einlassliste zum Drucken, Serienbrief-CSV
  mit QR-Code, **Verpflegungskalkulation** nach Tagessatz.
* **Abstimmungen** – Umfragen mit einer oder mehreren Seiten (Auswahl,
  Ankreuzen, Ja/Nein, Skala, Freitext), in der Oberfläche zusammengeklickt
  und über den Connector im Netz: QR-Code zum Scannen, Link zum Weitergeben,
  Zeitfenster. Die Antworten kommen verschlüsselt an und sind beim Connector
  nach dem Abholen gelöscht; ausgezählt wird im Multitool, mit CSV-Export
  und **Ergebnisbericht per E-Mail** an einen oder mehrere Kontakte (täglich, wöchentlich
  oder am Ende, optional von der KI zusammengefasst).
* **Getränke** – Lieferanten und Getränkearten (Wasser, Schorle, Cola-Mix),
  Lager je Stell- und Lagerplatz mit Zugang, Abgang und Inventur,
  **„ist leer" per QR-Code** über den Connector, Bedarf je Lieferant,
  Bestellung als Entwurf, nach dem Prüfen **per E-Mail an den Lieferanten**,
  Lieferung ins Lager, **Rechnung als Ausgabe ins Budget**.
* **E-Mail-Versand** – Postausgang über einen SMTP-Server (STARTTLS oder
  SSL/TLS), in der Verwaltung mit Testnachricht eingerichtet.
* **Kontakte** – Ansprechpartner mit Zusatzfeldern, anrufen und mailen mit einem
  Tipp, Verteiler für Einladungen, Import aus Excel/CSV, Outlook, Google und
  vCard mit Dublettenerkennung.
* **THW-Kontakte** – Dienststellen als Baum (Landesverband › Regionalstelle ›
  Ortsverbände) mit Anschrift und Erreichbarkeit, Funktionspostfächer mit Zweck
  und den Haken „offizielles Netz" / „Behördennetz"; Kontakte aus dem
  Kontaktmodul lassen sich Dienststellen zuordnen und umgekehrt.

### Technik und Bestand

* **Fahrzeuge** – Fahrzeugakte mit Funkrufname, Kennzeichen, ISSI, OPTA, RIC,
  Fristen HU/SP/UVV, Sitzplätzen und Zuladung; **Kapazitätsliste** zum Planen
  von Fahrten. **Journal, das sich nicht nachträglich ändern lässt**
  (Prüfsummen-Kette). **Instandsetzungsaufträge** mit Nummer, Werkstatt,
  Kosten und Stand, Fotos direkt aus der Kamera. **Boote auf Anhängern**:
  Trägerfahrzeug, Kennzeichen und Stellplatz werden vererbt, der Anhänger
  wird bei Terminen automatisch mitgebucht. Bilder mit Titelbild, Dokumente,
  Karte mit letzter Position, QR-Code zur Standortmeldung ohne Anmeldung,
  Wünsche je Fahrzeug, Stempel „Nicht einsatzbereit". **Tanken und
  Verbrauch**: Tankquittungen je Fahrzeug mit Belegfoto, Verbrauch je 100 km
  von Volltankung zu Volltankung, auch ohne Beträge (Tankkarte), Verlauf und
  Auffälligkeiten.
* **Funkgeräte** – HRT, MRT, Feststationen, Meldeempfänger mit Seriennummer,
  Inventarnummer, Funkrufname und Prüffrist; Karten hineinbuchen, Geräte
  Fahrzeugen und Plätzen zuordnen, Gruppen für Koffer und Sätze, „Gerät ist
  da" per QR-Code.
* **SIM- und TETRA-Karten** – Mobilfunk (Rufnummer, ICCID, Vertrag) oder
  TETRA-Sicherheitskarte (ISSI, OPTA), Zuordnung zu Fahrzeug, Fachgruppe,
  Person oder OV, PIN/PUK verdeckt, Warnung vor Vertragsende.
* **Tankkarten und Ladekarten** – mehrere Anbieter (DKV, UTA, Shell, Aral,
  Ladenetze …), Freischaltung für Kraftstoff, Laden oder beides, Zuordnung
  zu einem oder mehreren Fahrzeugen, PIN verdeckt, Warnung vor Ablauf,
  Verbleib gesperrt / zurückgegeben / abgelaufen mit Datum und Grund,
  CSV-Import mit Vorschau; die Karte wird je Tankung mit erfasst, Strom in
  kWh je 100 km.
* **Verbrauch** – Strom-, Gas- und Wasserzähler mit Ständen aus Home
  Assistant, von Hand oder per QR-Code; Tarife, Kosten je Monat und Jahr,
  **Kosten je Bereich** entlang der Stell- und Lagerplätze, Unterzähler mit
  Zeiträumen, Solaranlage mit Eigenverbrauch und Autarkie, auffälliger
  Verbrauch als Warnung, Verbrauchsprofil je Wochentag und Stunde, Berichte
  zum Drucken, CSV-Export.
* **Stell- und Lagerplätze** – Gebäude, Hallen und Höfe mit Stockwerken,
  Räumen, Stellplätzen, Schränken und Regalen als Baum (Serien wie
  „Raum 1–16" auf einmal), je Platz Bilder, GPS-Koordinaten und Karte.
  Fahrzeuge, Zähler, Funkgeräte und Funkgruppen hängen an Plätzen.
  **Standortübersicht** zum Durchklicken, **Hallenplan** als Foto oder
  Skizze mit jedem Fahrzeug an seinem Platz, Belegung aus dem Kalender.

### Anbindungen

* **Home Assistant** – Kennzahlen aller Module über MQTT mit Auto-Discovery
  (nur Zahlen, Zeitpunkte, Beträge – keine Namen oder Kennungen), optional
  Entitäten je Fahrzeug mit Standort, Benachrichtigungen über die
  Companion-App und als **Web Push** im Browser.
* **Stein.APP** – Abgleich von Status, HU, SP, Kennzeichen, ISSI und
  Funkrufname, schonend gegenüber dem Rate Limit, optional per Webhook.
  Vorhandene Akten werden über Kennzeichen oder Bezeichnung erkannt statt
  doppelt angelegt; Verknüpfungen lassen sich umhängen.
* **Divera 24/7** – Formulare als Wünsche oder Besprechungsthemen übernehmen;
  Fahrzeuge mit Funkstatus (FMS) als Fahrtenbuch, Position und Besatzung.
* **Connector** – kleines PHP-Skript auf einem öffentlichen Webserver für
  alles, was ohne Anmeldung erreichbar sein muss: Einladungen,
  Standortmeldung, „Gerät ist da", Zählerstand. Ende-zu-Ende verschlüsselt,
  der Connector sieht nur Chiffrate; Störungsmeldung und Integritätsprüfung
  („Ist der Connector sauber?").
* **KI-Auswertung der Dateien (Ollama)** – ein Ollama im eigenen Netz fasst
  hochgeladene Dokumente und Fotos zusammen und vergibt Schlagwörter;
  Dateisuche über alle Ablagen. **Standardmäßig aus und jederzeit
  abschaltbar**, nur Adressen im eigenen Netz werden angenommen.
* **Entfernungen (!! TEST !!)** – Luftlinie und Fahrstrecke von der Unterkunft
  zu Terminen, Veranstaltungen und THW-Dienststellen über OpenStreetMap und
  den OSRM-Demoserver; standardmäßig aus, eine Anfrage je Abruf mit Pause,
  jede Adresse nur einmal angefragt.

### Betrieb

* **Sicherung** – Datenbank und Dateiablage als ZIP sichern, herunterladen,
  hochladen und wiederherstellen; automatisch alle *n* Tage.
* **Verwaltung** – Benutzer mit Rollen und Funktionen, **zweiter Faktor**
  (Authenticator-App, Backup-Codes, als Pflicht je Rolle), alle Auswahllisten,
  Texte und Regeln frei einstellbar, Menüleiste in eigener Reihenfolge,
  Speicherplatz von Datenbank und Dateiablage, Änderungsprotokoll. Jede
  Veröffentlichung hat eine vierstellige Fassungsnummer; unten auf jeder Seite
  steht sie, ein Klick zeigt, was neu ist.

Die ausführliche Anleitung steht in [ov-budget/DOCS.md](ov-budget/DOCS.md)
(im Add-on als Reiter *Dokumentation*), der Änderungsverlauf in
[ov-budget/CHANGELOG.md](ov-budget/CHANGELOG.md).

## Home-Assistant-Add-on

Das Add-on ist eigenständig: **Datenbank, Webserver und Anwendung stecken im
Container.** Es sind keine weiteren Add-ons und keine Vorarbeiten nötig.

1. **Einstellungen → Add-ons → Add-on Store → ⋮ → Repositories** öffnen und
   diese Adresse hinzufügen:

```
https://github.com/Lexorius/ov-voldemort
```

2. In der Liste erscheint *OV-Multitool*. Installieren – der erste Build dauert je
   nach Hardware einige Minuten.
3. Optional unter *Konfiguration* den Namen des Ortsverbands und ein
   Wunschpasswort setzen. Man kann aber auch einfach starten.
4. **Starten**, dann **Protokoll** öffnen: dort steht das Startpasswort, sofern
   keines vergeben wurde.
5. „In Seitenleiste anzeigen" einschalten – fertig.

Beim ersten Start richtet sich alles selbst ein: MariaDB wird initialisiert, ein
zufälliges Datenbankpasswort erzeugt, Datenbank und Benutzer angelegt, Schema und
Grunddaten eingespielt und der Administrator erstellt. Spätere Starts erkennen den
Bestand, spielen die Datenbank-Wanderungen ein und ändern sonst nichts.

Alles Dauerhafte liegt unter `/data`:

```
/data/mysql      Datenbank
/data/uploads    Dateiablage: Angebote, Fahrzeuge, Veranstaltungen, Termine,
                 Stell- und Lagerplätze, Sicherungen
/data/sessions   Anmeldesitzungen
/data/dbpass     erzeugtes Datenbankpasswort
```

Das HA-Backup erfasst diesen Ordner vollständig. Wegen `backup: cold` hält der
Supervisor das Add-on währenddessen an, damit die Datenbankdateien in sich
stimmig sind. Zusätzlich gibt es in der Anwendung eine eigene Sicherung als ZIP
(Datenbank und Dateiablage), auf Wunsch automatisch.

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

### Automatische Abrufe

Im Add-on ruft der Container jede Minute `cron.php` auf. Darüber laufen der
Stein.APP- und Divera-Abgleich, das Abholen der Connector-Meldungen (auch
die Antworten auf Abstimmungen, Getränkemeldungen und Abmeldungen vom
Newsletter), die Ergebnisberichte per E-Mail, der Newsletter-Versand in
Portionen, die Kalender aus Home Assistant, die automatische Sicherung, die
Benachrichtigungen, die MQTT-Meldungen und die KI-Auswertung. Jeder Abruf
prüft selbst, ob er an der Reihe ist.

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

* PHP 8.1 oder neuer (`pdo_mysql`, `mbstring`; empfohlen `curl`, `fileinfo`,
  `gd` für Vorschaubilder, `zip` für Sicherung und Office-Dateien, `openssl`
  für die Connector-Kopplung)
* MySQL 5.7+ / MariaDB 10.3+
* Webserver mit Document-Root auf `public/`
* optional `poppler-utils` (pdftotext, pdftoppm) für die KI-Auswertung von PDFs

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
`php -r 'echo password_hash("...", PASSWORD_DEFAULT);'`). Für die automatischen
Abrufe einen Cronjob auf `php public/cron.php` (jede Minute) einrichten.

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
scripts/            kleine Helfer (z. B. QR-Bibliothek aktualisieren)
connector/          eigenständiger Briefkasten: Standort per QR-Code, Einladungen,
                    „Gerät ist da", Zählerstand – siehe connector/README.md
  public/           Dokumentenverzeichnis: index.php, melden.js, einladung.js
  src/              Ablage, Kopplung, Signaturen, Melde- und Einladungsseiten
  daten/            entsteht beim ersten Aufruf; gehört NICHT ins Web
ov-budget/          das Add-on – zugleich die vollständige Anwendung
  config.yaml, build.yaml, Dockerfile, DOCS.md, CHANGELOG.md, translations/
  docker/rootfs/    nginx, php-fpm, MariaDB und Startskript des Containers
  public/           Webroot: index.php (Front-Controller), install.php, cron.php, assets/
  src/bootstrap.php Laufzeitumgebung
  src/lib/          eine Bibliothek je Modul: Datenbank, Auth, Einstellungen, Wünsche,
                    Budget, Fahrzeuge, Funkgeräte, SIM-Karten, Verbrauch, Kalender,
                    Standorte, THW-Kontakte, Connector, MQTT, Stein, Divera, KI, Sicherung …
  src/pages/        eine Datei je Route (admin/ für den Verwaltungsbereich)
  src/cli/          setup.php (Einrichtung beim Containerstart), migrate.php (Wanderungen)
  views/            HTML-Templates, views/layout.php als Rahmen, views/partials/ Bausteine
  sql/              schema.sql (Tabellen) und seed.sql (Grunddaten, Einstellungen)
  tests/            Prüfungen ohne Datenbank (php tests/alle.php) und Rundlauf der Sicherung
  config/           Konfiguration für die klassische Installation
  storage/uploads/  Dateiablage außerhalb des Webroots
                    (im Container stattdessen /data/uploads)
```

Dateien werden ausschließlich über `index.php` ausgeliefert – also nur für
angemeldete Benutzer, nach Rechteprüfung und mit `nosniff`-Header.

## Rollen

| Rolle | Darf |
|---|---|
| `user` (Mitglied) | Wünsche anlegen und die eigenen bearbeiten, abstimmen, kommentieren, Aufgaben im eigenen Zuständigkeitsbereich bearbeiten, Termine für sich und die eigene Fachgruppe anlegen, Fahrzeugmeldungen abgeben, Tankungen erfassen |
| `leitung` | zusätzlich: alle Wünsche bearbeiten, Status und Priorität setzen, Budget und Budgettöpfe pflegen, alle Aufgaben und Termine verwalten, Fahrzeuge, Funkgeräte, SIM-Karten, Tankkarten und Zähler pflegen, Veranstaltungen anlegen und die Gästeliste führen |
| `admin` | zusätzlich: Benutzerverwaltung, Auswahllisten, Einstellungen, Stell- und Lagerplätze, Anbindungen (Divera, Stein.APP, Home Assistant, Connectoren, KI), Sicherung, Protokoll |

Viele Rechte lassen sich in den Einstellungen feiner stellen, etwa ob Mitglieder
Aufgaben anlegen oder Fahrzeugmeldungen abgeben dürfen, und wer bestellen darf.

## Was ist konfigurierbar?

**Verwaltung → Auswahllisten** – Fachgruppen, Funktionen, Kategorien, Dringlichkeiten,
Status (Wünsche und Aufgaben), Mengeneinheiten, Anlagen-Typen, Fahrzeugarten,
Veranstaltungsarten, Arten und Status der SIM-Karten, Anbieter und Status der
Tankkarten und vieles mehr. Je Eintrag:
Bezeichnung, Farbe, Reihenfolge, Gewicht (steuert die Sortierung), Vorgabewert und –
bei Status – ob er als „abgeschlossen" gilt. Einträge, die noch verwendet werden,
lassen sich auf *inaktiv* setzen, statt sie zu löschen.

**Verwaltung → Einstellungen** – nach Gruppen: Name der Anwendung und des OV,
Akzentfarbe, Fuß- und Einleitungstexte, Modulbezeichnungen („Wünsch dir was" heißt bei
euch anders? Kein Problem), Haushaltsjahr, MwSt-Satz, Warnschwellen, Pflichtfelder,
Upload-Grenzen und erlaubte Dateitypen, Abstimmung, Session-Laufzeit, Sperre nach
Fehlversuchen, zweiter Faktor, Fristen der Fahrzeuge, Takt der Abrufe, Sicherung,
KI-Auswertung, E-Mail-Versand, Abstimmungen (Sichtbarkeit, Abruf-Takt,
Codelänge, Uhrzeit der Berichte), Getränke, Newsletter (Sichtbarkeit,
Portion und Takt des Versands, Fußtext mit dem Abmeldelink), Tankungen (wer
sie erfassen darf), Tankkarten (Sichtbarkeit, Vorwarnung vor Ablauf).

**Unscharfe Budgetübersicht** – `budget_rundung` stellt die Übersichtsseite auf
gerundete Beträge um (10, 100 oder 1.000). Praktisch für Aushänge und Vorträge,
wo die Größenordnung zählt. Listen und CSV bleiben centgenau.

**Eigene Zusatzfelder für Wünsche und Kontakte** – in den Einstellungen unter
`wunsch_extra_felder` beziehungsweise `kontakte_extra_felder`, eine Zeile je Feld:

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
2. **Verwaltung → Divera 24/7 → „Formulare aus Divera abrufen"**: Die Antwort wird tolerant
   nach einer Liste von Datensätzen durchsucht. Passende Formulare einbinden.
3. **Feldzuordnung**: „Formularfelder abrufen" liest die vorhandenen Einträge und sammelt
   daraus die Feldnamen. Danach wird je Wunschfeld (Bezeichnung, Anzahl, Nettobetrag,
   Dringlichkeit, Fachgruppe, nice to have …) der Name des Divera-Feldes ausgewählt.
   Fachgruppe und Dringlichkeit werden über Bezeichnung bzw. Schlüssel der Auswahllisten
   zugeordnet; Nicht-Treffer fallen auf die Vorgabe des Formulars zurück.
   **Nicht zugeordnete Divera-Felder gehen nicht verloren** – sie werden gesammelt an die
   Beschreibung angehängt.
4. **Vorschau** zeigt, was entstehen würde, **Jetzt importieren** legt die Wünsche an.
   Bereits importierte Einträge erkennt die App an Formular- und Eintrags-ID und
   überspringt sie.

Formulare lassen sich genauso in den **Themenspeicher der Besprechungen** leiten;
der Bearbeitungsstand geht an Divera zurück. **Verwaltung → Divera-Fahrzeuge**
holt Funkstatus, Position und Besatzung der Fahrzeuge.

Automatischer Abruf: in den Einstellungen `cron_token` setzen, beim Formular
„automatisch importieren" ankreuzen und einrichten:

```bash
curl -s "https://DEINE-DOMAIN/cron.php?token=DEIN_TOKEN"
```

Auf der Konsole geht es auch ohne Token: `php public/cron.php`. Im Add-on ist
das schon eingerichtet.

## Connectoren

Manches kommt von außen: der Standort eines Fahrzeugs, das jemand ohne Zugang
abgestellt hat, die Rückmeldung auf eine Einladung, ein Zählerstand oder „das
Gerät ist am Lagerort". Dafür gibt es den **Connector** im Ordner `connector/`
– eine kleine PHP-Anwendung für einen öffentlich erreichbaren Webserver. Die
Anleitung steht in [connector/README.md](connector/README.md).

```
Handy / Browser  --verschlüsselt-->  Connector  <--holt ab--  OV-Multitool (Add-on)
```

Unter *Verwaltung → Connectoren* lassen sich beliebig viele anlegen. Jeder
bekommt eine eigene Kopplung und trägt, wofür er zuständig ist: **Fahrzeuge**
(Standort per QR-Code), **Veranstaltungen** (Einladungen), **Bestand** („Gerät
ist da"), **Zähler** (Stand per QR-Code), **Abstimmungen** (Umfragen),
**Getränke** („ist leer"), **Newsletter** (Abmeldelink) – auch mehreres
zugleich.

### Standortmeldung per QR-Code

In jedem Fahrzeug kann ein QR-Code hängen. Wer ihn scannt, meldet den Standort
des Fahrzeugs – ohne Anmeldung, ohne Zugang zu OV-Multitool und ohne Home
Assistant. Gedacht für Helferinnen und Helfer, die ein Fahrzeug irgendwo
abstellen oder wiederfinden.

* Das Handy verschlüsselt die Position **im Browser** für den öffentlichen
  Schlüssel von OV-Multitool (ECDH P-256, AES-256-GCM). Der Connector speichert
  nur Geheimtext und löscht ihn beim Abholen – er kann die Standorte nicht
  lesen.
* Er kennt auch die Fahrzeuge nicht: gespeichert werden nur **Prüfsummen** der
  Zugänge; Bezeichnung und Kennzeichen stehen im Anker der Adresse (hinter dem
  `#`) und erreichen den Server nie.
* Connector und OV-Multitool koppeln sich einmalig mit einem Code und tauschen
  dabei ihre öffentlichen Schlüssel aus. Danach ist jede Anfrage signiert.
* Die Melde-Seite bietet drei Möglichkeiten: einmal senden, alle 60 Sekunden
  oder alle 500 Sekunden – jeweils nur, solange die Seite offen ist. Ein
  Namensfeld ist freiwillig und wird auf dem Gerät gemerkt.
* Auf der Karte in der Fahrzeugakte erscheint jede Meldung sofort. Ins Journal
  kommt nur eine **Parkposition**: wenn ein Fahrzeug länger als eine Stunde
  (einstellbar) im selben Umkreis steht.
* Codes lassen sich je Fahrzeug erzeugen, neu erzeugen und zurückziehen.
  Gibt es mehrere Connectoren, wird beim Erzeugen ausgewählt, über welchen der
  Code läuft.

### Einladungen zu Veranstaltungen

Wer im Veranstaltungsmodul auf der Gästeliste steht, bekommt einen eigenen
Einladungscode. Er steht in einer möglichst kurzen Adresse:

```
https://i.example.de/AB23CD
```

* Die Seite zeigt Titel, Zeitpunkt, Ort und den Hinweistext und fragt:
  *Ich nehme teil*, *Ich kann leider nicht teilnehmen* oder *Es kommt jemand
  für mich*. Dazu, wenn erlaubt, „Mit wie vielen Begleitern kommen Sie?" und
  ein Feld für eine Nachricht.
* Die Rückmeldung wird **im Browser verschlüsselt**. Der Connector kennt Titel,
  Zeitpunkt und Ort der Veranstaltung – die Seite muss sie zeigen –, aber
  weder Namen noch Antworten.
* Von den Einladungscodes liegen dort nur Prüfsummen. Ein Code lässt sich
  jederzeit neu erzeugen; der alte gilt dann nicht mehr.
* OV-Multitool meldet Veranstaltungen und Codes selbstständig an und holt die
  Rückmeldungen ab (Vorgabe: alle zwei Minuten). Beides geht auch von Hand.
* Ohne Connector funktioniert das Modul trotzdem – Rückmeldungen werden dann
  von Hand eingetragen.

### Getränke über den Connector

Am Getränkeregal oder Kühlschrank hängt ein QR-Code je Lagerplatz. Die
Seite zeigt die Getränke; wer vorbeikommt, tippt *leer*, *wird knapp* oder
*reicht noch* an, wahlweise mit der Zahl der Kästen. Der Connector kennt
nur die Getränkenamen – nicht den Platz (steht im Anker der Adresse) und
nicht die Meldung (verschlüsselt, nach dem Abholen gelöscht). Im Multitool
wird daraus Bedarf und Bestand; mehr im Abschnitt [Getränke](#getränke).

### Abstimmungen über den Connector

Eine freigegebene Abstimmung steht hinter derselben kurzen Adresse wie eine
Einladung (`https://i.example.de/27VC8A`) oder hinter dem QR-Code. Der
Connector kennt Titel, Beschreibung, Zeitfenster und die Fragen – die Seite
muss sie zeigen. Die Antworten kennt er nicht: Sie werden im Browser
verschlüsselt, liegen dort nur als Geheimtext und sind nach dem Abholen
gelöscht. Außerhalb des Zeitfensters nimmt die Seite nichts an, einen Tag
nach dem Ende verschwindet die Abstimmung vom Connector. Mehr im Abschnitt
[Abstimmungen](#abstimmungen).

### Ist der Connector sauber?

OV-Multitool kennt die Prüfsummen aller Connector-Dateien und prüft täglich,
ob sie stimmen und ob fremde Dateien aufgetaucht sind. Bei Befund geht eine
Meldung an Home Assistant. Antwortet ein Connector länger nicht, gibt es eine
Störungsmeldung und später die Entwarnung.

## Abstimmungen

Umfragen für den Ortsverband: Termin fürs Sommerfest, Verpflegung bei der
Übung, Stimmungsbild zur Helferversammlung. Gestaltet wird im Multitool,
beantwortet wird draußen – ohne Anmeldung, über den Connector.

**Gestalten.** Erst die Eckdaten (Titel, Beschreibung, Zeitfenster,
Connector, freiwillige Namensabfrage, Dankestext), dann die Seiten: Jede
Seite bekommt Überschrift, Einleitung und beliebig viele Fragen, per
Dropdown und „+ Frage" zusammengeklickt, verschiebbar.

| Fragetyp | Antwort |
|---|---|
| Eine Antwort | genau eine Antwortmöglichkeit |
| Mehrere Antworten | Ankreuzen, so viele wie man will |
| Ja / Nein | ja oder nein |
| Skala | eine Zahl zwischen zwei Grenzen (0 bis 10), Beschriftung links und rechts |
| Freitext | kurz oder lang |
| Hinweistext | keine Frage, ein Absatz zwischen den Fragen |

Jede Frage kann Pflicht sein. Mehrere Seiten zeigt das Handy nacheinander
mit *Weiter*, *Zurück* und Fortschrittsbalken; Pflichtfragen werden je
Seite geprüft.

**Zeitfenster und Freigabe.** Das Ende ist Pflicht, der Beginn freiwillig.
Eine neue Abstimmung ist ein Entwurf und beim Connector unbekannt;
*Freigeben* meldet sie an, *Schließen* holt vorher ab, was noch draußen
liegt, und meldet sie ab.

**QR-Code und Link.** Auf der Seite der Abstimmung: QR-Code zum Scannen mit
Druckansicht, *Link kopieren* und *QR als Bild* zum Weitergeben in
Nachrichten. Der Code lässt sich auch auf der Startseite des Connectors
eintippen; *Code neu* macht alten Link und QR ungültig.

**Ergebnisse.** Je Frage Balken mit Anzahl und Prozent, bei Skalen
Durchschnitt und Verteilung, bei Freitext die Liste, dazu CSV-Export.
Antworten und ganze Abstimmungen lassen sich löschen. Sehen dürfen
Abstimmungen standardmäßig Leitung und Admin; ein Schalter öffnet sie für
alle Angemeldeten.

**Ergebnisbericht per E-Mail.** Je Abstimmung ein Rhythmus – täglich,
wöchentlich oder nur am Ende – und beliebig viele Empfänger aus den
Kontakten (Dropdown mit Suchfeld und „+ Hinzufügen"). Zwischenstände gehen
ab einer einstellbaren Uhrzeit, das Ergebnis, sobald nach dem Ende die
letzten Antworten abgeholt sind. Jeder Empfänger bekommt eine eigene
Textmail mit Titel, Zeitraum, Zahl der Antworten und der Zählung je Frage.
Auf Wunsch fasst die KI-Auswertung (Ollama, wenn eingeschaltet) die
Ergebnisse oben im Bericht in wenigen Sätzen zusammen – ohne Gewähr; ist
sie aus oder nicht erreichbar, geht der Bericht ohne sie. „Jetzt senden"
auf der Seite der Abstimmung schickt einen Bericht von Hand.

## Getränke

Das Modul führt Getränkearten mit Lieferanten, den Bestand je Stell- und
Lagerplatz und die Bestellungen bis zur Rechnung.

* **Lieferanten und Getränke** – Name, E-Mail für Bestellungen,
  Kundennummer; je Getränk Lieferant, Gebinde, Preis und Mindestbestand.
  Bestellungen werden je Lieferant gebündelt.
* **Lager** – Getränke gegen Plätze als Tabelle; Zugang, Abgang und
  Inventur als Bewegungen mit Zeit, Person und Notiz. Rot, was unter dem
  Mindestbestand liegt.
* **„Ist leer" per QR-Code** – je Lagerplatz ein Code über den Connector;
  *leer* setzt den Bestand auf null, eine Zahl ist eine Inventur.
* **Bedarf und Bestellung** – aus Meldungen und Mindestbestand ein
  Vorschlag je Lieferant, als Entwurf angelegt, von einer verantwortlichen
  Person geprüft und erst dann **per E-Mail** an den Lieferanten geschickt
  (Positionen, Lieferadresse, Abladeplatz, Ansprechpartner).
* **Absender** – im Namen des Ortsverbands, des Helfervereins oder der
  Regionalstelle bestellen; Name und Rechnungsanschrift stehen in der Mail.
* **Lieferung und Rechnung** – gelieferte Gebinde gehen als Zugang ins
  Lager, die Rechnung als **Ausgabe ins Budget** mit Kategorie,
  Rechnungsnummer und Zahlungsstand, verknüpft mit der Bestellung.

## Newsletter

Das Modul schreibt keine Newsletter – es verschickt fertige. Je Newsletter
wird ein PDF hochgeladen, dazu Betreff und Begleittext, die Empfänger und
ein Zeitpunkt.

* **Empfänger** – Kontakte mit E-Mail-Adresse, ganze Verteiler aus dem
  Kontaktmodul und freie Adressen; jede Adresse einmal, Gesperrte nie.
* **Versand** – sofort oder zu einem Zeitpunkt; beim Einplanen wird die
  Empfängerliste festgehalten, der automatische Abruf verschickt dann in
  Portionen (Größe und Takt einstellbar). Betreff und Text sind bis dahin
  änderbar, eine Probe geht an die eigene Adresse.
* **Abmeldelink** – jede Mail trägt einen Link auf den Connector. Der kennt
  nur eine Kennung je Adresse, nie die Adresse selbst; OV-Multitool holt die
  Abmeldungen ab und setzt die Kennung auf die **Sperrliste**. Die speichert
  nur Prüfsummen – wer einmal abgemeldet ist, bekommt nichts mehr, auch wenn
  die Adresse später wieder in einer Liste steht.
* **KI-Zusammenfassung** – das hochgeladene PDF wird, wenn die KI
  eingerichtet ist, für die Verwaltung zusammengefasst.
* Die Liste ist seitenweise, je 50 Newsletter.

**Nur für interne Newsletter.** E-Mail-Adressen nur mit Zustimmung der
Empfänger verwenden – Mitglieder, Helferinnen und Helfer, Angehörige, die
den Newsletter ausdrücklich wollen. Für Werbung an Außenstehende ist das
Modul nicht gedacht. Die rechtlichen Voraussetzungen (Einwilligung,
Datenschutz, Impressum in der Mail, jederzeit mögliche Abmeldung) liegen in
der Verantwortung des Ortsverbands; das Modul zeigt diesen Hinweis in der
Liste, beim Bearbeiten und vor dem Versand.

## E-Mail-Versand

Das Add-on hat keinen Mailserver. Darum spricht OV-Multitool einen
SMTP-Server direkt an – den des Vereins, des Providers oder ein Postfach
der Dienststelle. Unter *Verwaltung → Einstellungen → E-Mail* stehen
Server, Port, Verschlüsselung (STARTTLS, SSL/TLS oder keine), Benutzer,
Passwort, Absenderadresse und Absendername; die Verwaltungsseite *E-Mail*
zeigt den Zustand samt letztem Fehler und schickt eine Testnachricht. Der
Versand ist ein Hauptschalter, standardmäßig aus. Verschickt werden
Textnachrichten, bei Bedarf mit PDF-Anhang; genutzt wird er von den
Berichten der Abstimmungen, den Getränkebestellungen, dem Protokollversand
der Besprechungen und dem Newsletter.

## KI-Auswertung der Dateien

Optional fasst ein [Ollama](https://ollama.com) im eigenen Netz hochgeladene
Dokumente und Fotos zusammen und vergibt Schlagwörter – in der Fahrzeugakte
und an Aufträgen, an Veranstaltungen, Terminen und Plätzen. Unter der Datei
stehen Zusammenfassung und Schlagwort-Chips, die **Dateisuche** findet über
alle Ablagen hinweg; wer die Datei bearbeiten darf, kann die Auswertung
anpassen, neu anstoßen oder entfernen.

* **Standardmäßig aus.** Hauptschalter und ein Schalter je Ablage in den
  Einstellungen. Aus heißt: nichts wird eingereiht, keine Verbindung, nichts
  verändert; vorhandene Zusammenfassungen bleiben sichtbar.
* **Nur das eigene Netz.** Erlaubt sind private IP-Adressen, Rechnernamen ohne
  Punkt und Namen auf `.local`, `.lan`, `.home`, `.internal`. Öffentliche
  Adressen lehnt die Einstellung ab – die Dokumente verlassen das Haus nicht.
* Texte aus PDFs liest `pdftotext` (im Add-on enthalten), Office-Dateien
  werden direkt gelesen; Bilder und gescannte PDFs gehen an ein optionales
  Vision-Modell (z. B. `llava:7b`). Voreinstellung für Text: `qwen2.5:7b`.
* Verwaltungsseite mit Verbindungstest, Warteschlange, Fehlerliste und
  „Vorhandene Dateien nachholen".
* Dieselbe KI fasst auf Wunsch die **Ergebnisberichte der Abstimmungen**
  zusammen – nur, wenn sie eingeschaltet ist, und immer als „ohne Gewähr"
  gekennzeichnet.

## Sicherheit

* Passwörter als `password_hash()` (bcrypt/argon2, automatischer Rehash beim Anmelden)
* **Zweiter Faktor** mit Authenticator-App (TOTP) und Backup-Codes, als Pflicht je Rolle
* CSRF-Token für jedes POST-Formular
* Bremse gegen Passwortraten (Anzahl und Sperrdauer einstellbar)
* Startpasswörter müssen beim ersten Anmelden geändert werden
* durchgängig vorbereitete Statements, Ausgabe HTML-escaped
* Uploads mit Endungs-, Typ- und Größenprüfung, Ablage außerhalb des Webroots
  unter zufälligem Namen; Fotos verlieren beim Speichern ihre Metadaten (Aufnahmeort)
* Fahrzeugjournal mit Prüfsummen-Kette, Änderungsprotokoll unter *Verwaltung → Protokoll*
* Connector-Meldungen Ende-zu-Ende verschlüsselt, signierte Kopplung,
  Begrenzung je Fahrzeug und Anschluss – Einzelheiten in
  [connector/README.md](connector/README.md)
* Nach außen gehen nur Kennzahlen (MQTT) – keine Namen, Kennungen oder Rufnummern;
  die KI-Auswertung spricht nur mit Rechnern im eigenen Netz

## Datensicherung

In der Anwendung: *Verwaltung → Sicherung* sichert Datenbank und Dateiablage als
ZIP, lädt sie herunter oder hoch und stellt sie wieder her – mit Sicherheitskopie
vorher; auf Wunsch automatisch alle *n* Tage, ältere werden ausgedünnt.

Von Hand:

```bash
mysqldump -u ov_budget -p ov_budget > backup-$(date +%F).sql
tar czf uploads-$(date +%F).tar.gz storage/uploads
```

Als Home-Assistant-Add-on genügt daneben das normale HA-Backup: Datenbank,
Dateiablage und Sitzungen liegen alle unter `/data` und werden mitgesichert. Das
Add-on wird für die Dauer der Sicherung angehalten (`backup: cold`), damit die
Datenbankdateien konsistent sind.

## Prüfungen für Entwickler

```bash
cd ov-budget
php tests/alle.php            # alle Prüfungen, ohne Datenbank
php tests/alle.php kalender   # nur t_kalender.php
```

Jede Veröffentlichung läuft durch GitHub Actions (PHP 8.2); die Fassungsnummer
steht in `ov-budget/config.yaml`, der Änderungsverlauf in `ov-budget/CHANGELOG.md`.
