# Änderungsverlauf

## 1.3.0

### Doppelte Grunddaten bereinigt

`list_items` hatte keinen eindeutigen Schlüssel, deshalb blieb das
`INSERT IGNORE` in `seed.sql` wirkungslos: **jeder Containerstart hat die
Grunddaten erneut eingefügt.** Beim nächsten Start räumt eine Wanderung das auf:

- vor der Bereinigung wird `list_items` nach `list_items_backup_dedupe` kopiert
- gleiche Einträge werden zusammengeführt; behalten wird der tatsächlich
  verwendete, bei Gleichstand der älteste
- vorhandene Zuordnungen (Wünsche, Aufgaben, Benutzer, Töpfe) werden auf den
  behaltenen Eintrag umgebogen, **erst danach** werden die dann unbenutzten
  Dubletten gelöscht – es geht also keine Zuordnung verloren
- anschliessend sorgt ein eindeutiger Schlüssel dafür, dass es nicht wieder
  passieren kann

### Budget: Jahresbudget und Ausgaben

- **Gesamtbudget je Haushaltsjahr** eintragbar
- **Ausgaben nachpflegen** mit Datum, Kategorie, Betrag, MwSt, Lieferant,
  Belegnummer, Fachgruppe, optionalem Budgettopf und Bezug zu einem Wunsch
- Kategorien wie Haus, Nebenkosten, Getränke, Tanken, Fahrzeugunterhalt frei
  pflegbar unter Verwaltung → Auswahllisten
- **Budgetübersicht**: verbraucht gegen Jahresbudget, Aufschlüsselung nach
  Kategorie, Verlauf über die zwölf Monate, Töpfe mit tatsächlichen Ausgaben
  und die noch offenen Wünsche
- Ausgabenliste mit Filtern und CSV-Ausgabe
- Beträge wahlweise brutto oder netto erfassen, der andere Wert wird berechnet

## 1.2.2

- Rechte repariert: Die beim Start erzeugte `config/config.php` gehörte root
  und war mit 0640 für den Webserver-Benutzer nicht lesbar – jede Seite endete
  mit "Permission denied". Die Datei wird jetzt dem Webserver-Benutzer
  übereignet; schlägt das fehl, wird sie allgemein lesbar gemacht.
- Eindeutige Fehlermeldung im Protokoll, falls die Konfiguration einmal nicht
  lesbar sein sollte.

## 1.2.1

- Build repariert: Das Image baut jetzt auf dem Home-Assistant-Basisimage auf
  und installiert PHP aus den Alpine-Paketen. Zuvor wurde ein Helfer des
  offiziellen PHP-Images vorausgesetzt, den der Supervisor nicht bereitstellt
  (`docker-php-ext-install: not found`).
- Die PHP-Version wird beim Bauen ermittelt, weil Alpine sie im Paketnamen
  führt und sie je Alpine-Fassung wechselt.

## 1.2.0

- Umbau zu einem regulären Add-on-Repository: das Add-on liegt jetzt im
  Unterordner `ov-budget/`, sodass sich die GitHub-URL direkt unter
  *Repositories* im Add-on Store hinzufügen lässt.
- Dokumentation als `DOCS.md` im Add-on selbst.

## 1.1.0

- Das Add-on bringt seine eigene MariaDB mit und installiert sich vollständig
  allein. Das MariaDB-Add-on ist nicht mehr nötig, kann aber weiterhin über
  `db_host` verwendet werden.
- Datenbank, Uploads und Sitzungen liegen unter `/data`; `backup: cold` sorgt
  für konsistente Sicherungen.
- Geordnetes Herunterfahren der Datenbank beim Stoppen des Add-ons.

## 1.0.0

- Erste Fassung: Wunschliste, Aufgaben, Budgettöpfe, Divera-24/7-Anbindung,
  Benutzerverwaltung und frei konfigurierbare Auswahllisten.
- Ingress-Unterstützung, optionaler direkter Zugriff über Port 8099.
