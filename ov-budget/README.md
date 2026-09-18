# OV-Budget

Verwaltung für einen THW-Ortsverband: Wünsche und Budget, Aufgaben, Kontakte,
Besprechungen und Fahrzeuge – mit Anbindung an **Stein.APP** und
**Divera 24/7**. Läuft auf dem Handy wie am PC.

Das Add-on bringt **alles mit**: Datenbank (MariaDB), Webserver und Anwendung
stecken im Container. Weitere Add-ons sind nicht nötig. Erreichbar über die
Seitenleiste von Home Assistant, ohne Port nach außen.

## Funktionen

### Wünsch dir was
- Bedarfe mit Bezeichnung, Anzahl, Nettobetrag, Dringlichkeit, Fachgruppe,
  Kategorie, Status, „nice to have", Frist und eigenen Zusatzfeldern
- Angebote als Anlage, Abstimmung, Kommentare, CSV-Export
- Übernahme aus **Divera-24/7-Formularen** mit frei zuordenbaren Feldern
- **„Freigegeben, bitte bestellen"** mit **Bestellberechtigungen** je Rolle und
  Funktion, auch mit Betragsgrenze und Vier-Augen-Prinzip

### Budget
- Jahresbudget, **Ausgaben** (Haus, Nebenkosten, Getränke, Tanken …) und
  **Einnahmen** (Einsätze, technische Hilfeleistung, Spenden …)
- Übersicht nach Kategorie und Monat mit Grafik, Budgettöpfe
- Auf Wunsch „unscharf" gerundet auf 10, 100 oder 1.000

### Aufgaben
- Für den Ortsverband, Fachgruppen, Funktionen oder einzelne Personen

### Kontakte
- Ansprechpartner mit eigenen Zusatzfeldern
- Verteiler für Einladungen mit Rückmeldung, CSV für den Serienbrief
- Import aus Excel/CSV, Outlook, Google Kontakte und vCard mit
  Dublettenerkennung

### Besprechungen
- Talking Points im Themenspeicher, Tagesordnung mit Zeitplan
- **Themenarchiv**: besprochene, beschlossene, abgelehnte und vertagte Themen
  mit Ergebnis und Aufgaben, durchsuchbar
- **Themen über Divera einreichen**: ein Divera-Formular füllt den Themenspeicher
- **Status-Rückmeldung an Divera**: Weitergeleitet → In Bearbeitung → Abgeschlossen
- **Aufgaben aus der Besprechung**: je Punkt vorausgefüllt, mehrere Punkte auf
  einmal übernehmen oder frei anlegen; Übersicht in Besprechung und Protokoll
- **Wiederkehrende Termine** („alle 2 Wochen montags", „jeden 2. Montag im Monat")
- **Anwesenheit**: teilgenommen, entschuldigt, nicht erschienen
- Tagesordnung und Protokoll zum Drucken

### Fahrzeuge
- **Fahrzeugakte** mit Funkrufname, Kennzeichen, ISSI, OPTA, RIC und den
  Fristen HU, SP und UVV
- **Journal, das sich nicht nachträglich ändern lässt** – jede Änderung, jeder
  Auftragsschritt, jede Meldung; eine Prüfsummen-Kette zeigt Manipulationen an
- **Instandsetzungsaufträge** mit Nummer, Werkstatt, Kosten und Bearbeitungsstand
- **Bilder** (mit Titelbild) und **Dokumente** an Fahrzeugen und Aufträgen;
  Fotos verlieren beim Speichern den Aufnahmeort

### Stein.APP
- Abgleich von Status, HU, SP, Kennzeichen, ISSI und Funkrufname; jede
  Änderung steht in der Fahrzeugakte
- Schonend gegenüber dem Rate Limit: ein Aufruf je Abgleich, Vorgabe alle
  10 Minuten, Pause bei HTTP 429; optional per **Webhook** sofort
- Unbekannte Fahrzeuge mit Kennzeichen auf Wunsch automatisch als Akte anlegen
- Mitschnitt der Antworten zur Fehlersuche – ohne den API-Schlüssel

### Divera 24/7 – Fahrzeuge
- **Funkstatus (FMS) als Fahrtenbuch** im Journal: „Status 3 – Einsatz
  übernommen um 14:32 Uhr"
- Letzte **Position** mit Kartenlink und die zugeordnete **Besatzung**
- OPTA und RIC aus den Stammdaten

### Verwaltung
- Benutzer mit Rollen (Mitglied, Leitung, Administration) und Funktionen
- Alle Auswahllisten, Texte und Regeln frei einstellbar
- Protokoll aller Änderungen; unten auf jeder Seite steht die laufende
  Version, ein Klick zeigt, was neu ist

Die ausführliche Anleitung steht im Reiter **Dokumentation**.

*Privates Projekt, kein offizielles Angebot des THW.*
