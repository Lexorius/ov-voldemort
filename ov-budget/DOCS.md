# OV-Budget

Pseudo-Budgetverwaltung, Wunschliste („Wünsch dir was") und Aufgaben für einen
THW-Ortsverband. Das Add-on bringt **alles mit** – Datenbank, Webserver und
Anwendung stecken im Container. Weitere Add-ons sind nicht nötig.

## Installation

1. **Einstellungen → Add-ons → Add-on Store → ⋮ → Repositories** und
   `https://github.com/Lexorius/ov-voldemort` hinzufügen.
2. In der Liste erscheint *OV-Budget*. **Installieren** – der erste Build dauert
   je nach Hardware einige Minuten, auf einem Raspberry Pi auch länger.
3. Optional unter *Konfiguration* den Namen des Ortsverbands und ein
   Wunschpasswort setzen. Man kann aber auch einfach starten.
4. **Starten**, dann **Protokoll** öffnen. Dort steht das Startpasswort, falls
   keines vergeben wurde.
5. „In Seitenleiste anzeigen" einschalten – fertig.

Beim ersten Start richtet sich alles selbst ein: MariaDB wird initialisiert, ein
zufälliges Datenbankpasswort erzeugt, Datenbank und Benutzer angelegt, Schema und
Grunddaten eingespielt und der Administrator erstellt. Spätere Starts erkennen
den Bestand und ändern nichts daran.

## Konfiguration

| Option | Bedeutung |
|---|---|
| `ov_name` | Name des Ortsverbands. Wird beim ersten Start als Überschrift übernommen, später in der Anwendung änderbar. |
| `admin_username` | Benutzername des ersten Zugangs. Nur beim allerersten Start angelegt. |
| `admin_password` | Mindestens 10 Zeichen. Leer lassen: dann erzeugt der erste Start ein Zufallspasswort und schreibt es ins Protokoll. |
| `db_name` | Name der Datenbank, Vorgabe `ovbudget`. |
| `db_host`, `db_port`, `db_user`, `db_password` | **Leer lassen.** Nur ausfüllen, wenn statt der mitgelieferten Datenbank eine vorhandene genutzt werden soll. |
| `debug` | PHP-Meldungen im Browser. Nur zur Fehlersuche. |

### Beispiel

```yaml
ov_name: THW Ortsverband Musterstadt
admin_username: obmann
admin_password: ""
db_name: ovbudget
db_host: ""
db_port: 3306
db_user: ""
db_password: ""
debug: false
```

### Lieber das MariaDB-Add-on verwenden?

Geht auch: im MariaDB-Add-on eine Datenbank samt Login anlegen und hier
`db_host: core-mariadb` mit `db_user` und `db_password` eintragen. Dann startet
der Container seine eigene Datenbank nicht.

### Zugriff ohne Ingress

Normalerweise läuft alles über die Seitenleiste, es muss kein Port geöffnet
werden. Für den direkten Aufruf im Heimnetz unter *Konfiguration → Netzwerk*
einen Port für `8099/tcp` vergeben, dann `http://homeassistant.local:8099`.

## Daten und Sicherung

Alles Dauerhafte liegt unter `/data`:

```
/data/mysql      Datenbank
/data/uploads    hochgeladene Angebote
/data/sessions   Anmeldesitzungen
/data/dbpass     erzeugtes Datenbankpasswort
```

Das normale Home-Assistant-Backup erfasst diesen Ordner vollständig. Das Add-on
ist als `backup: cold` eingetragen, wird also für die Dauer der Sicherung
angehalten, damit die Datenbankdateien in sich stimmig sind.

## Was kann die Anwendung?

* **Wünsch dir was** – Bedarfe mit Bezeichnung, Anzahl, Nettobetrag,
  Dringlichkeit, Fachgruppe, Kategorie, Status, „nice to have", Frist,
  Angebots-Uploads, Abstimmung und Kommentaren. CSV-Export inklusive.
* **Aufgaben** – für den Ortsverband, einzelne Fachgruppen, Funktionen oder
  Personen. Wer angemeldet ist, sieht unter „Für mich" alles aus dem eigenen
  Zuständigkeitsbereich.
* **Budget** – Gesamtbudget je Haushaltsjahr, **Ausgaben** (Haus, Nebenkosten,
  Getränke, Tanken ...) und **Einnahmen** (Kostenerstattung für Einsätze,
  technische Hilfeleistung, Spenden ...) mit Einsatz- oder Auftragsnummer.
  Die Übersicht rechnet Budget plus Einnahmen minus Ausgaben und schlüsselt
  nach Kategorie und Monat auf. Optional unterteilen Budgettöpfe das Jahr.
  Mit **„Freigegeben, bitte bestellen"** wird ein Wunsch zur Bestellung
  freigegeben; die Übersicht listet alles Freigegebene, bis es als bestellt
  markiert ist. Wer freigeben (optional bis zu einem Nettobetrag) und wer
  bestellen darf, wird unter *Verwaltung → Bestellberechtigungen* je Rolle und
  je Funktion festgelegt – etwa Ortsbeauftragte:r unbegrenzt, Zugführer:in bis
  500 €, Verwaltungsbeauftragte:r bestellt.
* **Fahrzeuge** – Fahrzeugstamm mit Fristen (HU, SP, UVV) und je Fahrzeug eine
  **Fahrzeugakte**. Ihr **Journal** wird nur ergänzt: Einträge lassen sich weder
  ändern noch löschen, jeder trägt die Prüfsumme des vorherigen, und
  „Journal auf Veränderungen prüfen" rechnet die Kette nach. Schadensmeldungen
  und **Instandsetzungsaufträge** mit fortlaufender Nummer, Werkstatt, Kosten
  und Bearbeitungsstand – jeder Schritt landet in der Akte.
* **Stein.APP** – Abgleich der Fahrzeuge über die Schnittstelle der Stein.APP
  (gleicher API-Schlüssel wie für die Home-Assistant-Integration). Alle zehn
  Minuten wird der komplette Stand geholt und jede Änderung an Status, HU, SP,
  Bemerkung oder Einsatzvorbehalt in die Fahrzeugakte geschrieben.
* **Kontakte** – Ansprechpartner bei Kommune, Feuerwehr, Presse, Firmen und
  Förderern, dazu Verteiler für Einladungen mit Rückmeldungen und einer
  CSV-Ausgabe für den Serienbrief. Neben den Standardfeldern lassen sich
  eigene Felder definieren. Import aus Excel, Outlook, Google Kontakte und vCard
  mit Vorschau und Dublettenerkennung.
* **Besprechungen** – Talking Points im Themenspeicher sammeln, auf die
  Tagesordnung setzen, während der Sitzung Ergebnisse festhalten und daraus
  Aufgaben machen. Tagesordnung und Protokoll zum Drucken. Regelmäßige
  Termine wie „alle 2 Wochen montags" oder „jeden 2. Montag im Monat" als Serie.
  **Anwesenheit:** Eingeladene aus dem Ortsverband, aus den Kontakten (auch ganze
  Verteiler) oder als freier Name; je Person teilgenommen, entschuldigt oder
  nicht erschienen. Die Zählung steht über der Liste, die Namen im Protokoll.
* **Divera 24/7** – Formulare abrufen, Felder frei zuordnen und Einträge als
  Wünsche übernehmen. Mit Vorschau, Dubletten-Erkennung und optionalem
  automatischem Abruf.
* **Verwaltung** – Benutzer und Rollen (Mitglied, Leitung, Administration) sowie
  *alle* Auswahllisten, Texte und Regeln frei konfigurierbar.

## Stein.APP einrichten

1. In der Stein.APP einen API-Schlüssel erzeugen und die **BU-ID** des
   Ortsverbands notieren – dieselben Angaben wie für die
   Home-Assistant-Integration *THW Stein*.
2. In der Anwendung unter *Verwaltung → Einstellungen → Stein.APP* Schlüssel
   und BU-ID eintragen und den Abgleich einschalten.
3. Unter *Verwaltung → Stein.APP* die Fahrzeuge zuordnen: entweder einer
   bestehenden Fahrzeugakte oder als neue Akte.

**Rate Limit:** Die Schnittstelle bremst bei zu vielen Abrufen. Deshalb macht
der Abgleich je Durchgang genau **einen** Aufruf (die Liste aller Fahrzeuge der
BU-ID) und hält den eingestellten Abstand ein (Vorgabe 10 Minuten). Antwortet
die Schnittstelle mit HTTP 429, pausiert der Abruf automatisch. Im Add-on ruft
der Container `cron.php` jede Minute auf; ob wirklich abgerufen wird,
entscheidet das Intervall. Außerhalb des Add-ons: `cron.php` per Cron aufrufen
(Token unter *Einstellungen → Stein.APP*) oder den Abgleich beim Öffnen des
Fahrzeugmoduls laufen lassen.

## Rollen

| Rolle | Darf |
|---|---|
| Mitglied | Wünsche anlegen und die eigenen bearbeiten, abstimmen, kommentieren, Aufgaben im eigenen Zuständigkeitsbereich bearbeiten |
| Leitung | zusätzlich: alle Wünsche bearbeiten, Status und Priorität setzen, Budgettöpfe pflegen, alle Aufgaben verwalten |
| Administration | zusätzlich: Benutzerverwaltung, Auswahllisten, Einstellungen, Divera-Anbindung, Protokoll |

## Wenn etwas klemmt

Das Protokoll des Add-ons zeigt jeden Schritt an. Zeilen mit `[run]` kommen vom
Startskript (Datenbank), Zeilen mit `[setup]` von der Einrichtung der Anwendung.

* **„Keine Verbindung zur Datenbank"** bei externer Datenbank: `db_host`,
  `db_user` und `db_password` gehören zusammen und müssen alle gesetzt sein.
* **Das Startpasswort ist weg**: unter *Konfiguration* ein `admin_password`
  setzen hilft nicht – der Zugang wird nur beim ersten Start angelegt. In dem
  Fall über einen anderen Administrator-Zugang zurücksetzen.
* **Nach einem Neustart fehlen Daten**: prüfen, ob das Add-on wirklich die
  eigene Datenbank nutzt (Protokollzeile „Eigene Datenbank wird verwendet").
* **Auswahllisten enthielten jeden Eintrag mehrfach**: Das war ein Fehler in
  Fassungen vor 1.3.0. Beim Start ab 1.3.0 wird das einmalig bereinigt; die
  Protokollzeilen mit `Wanderung 001` zeigen, was zusammengeführt wurde. Der
  Stand davor liegt als Tabelle `list_items_backup_dedupe` in der Datenbank.
