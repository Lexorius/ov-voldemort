# OV-Multitool

Verwaltung für einen THW-Ortsverband: Wünsche und Budget, Aufgaben, Kontakte,
Besprechungen, Veranstaltungen, Fahrzeuge, Funkgeräte, SIM-Karten und
Verbrauch – mit Anbindung an **Stein.APP**, **Divera 24/7** und
**Home Assistant**. Läuft auf dem Handy wie am PC.

Das Add-on bringt **alles mit**: Datenbank (MariaDB), Webserver und Anwendung
stecken im Container. Weitere Add-ons sind nicht nötig. Erreichbar über die
Seitenleiste von Home Assistant, ohne Port nach außen.

## ⚠ Sicherheitshinweis: PHP2Shell (Oktober 2026)

PHP hat am 21.10.2026 zwei kritische Lücken geschlossen (CVE-2026-109618,
CVE-2026-109619; behoben ab 8.5.12 / 8.4.27 / 8.3.36 / 8.2.35). Das Add-on
prüft beim Bauen seine PHP-Fassung, warnt in der Verwaltung und hat die vom
PHP-Projekt empfohlenen Schutzmaßnahmen an Bord; der Connector meldet die
PHP-Fassung des Hosters. Bis die Meldung in der Verwaltung grün ist, keinen
Port des Add-ons aus dem Internet erreichbar machen. Einzelheiten im Reiter
**Dokumentation** unter „Sicherheitslücke PHP2Shell".

## Funktionen

### Wünsch dir was
- Bedarfe mit Bezeichnung, Anzahl, Betrag, Dringlichkeit, Fachgruppe,
  Kategorie, Status, „nice to have", Frist und eigenen Zusatzfeldern
- Angebote als Anlage, Abstimmung, Kommentare, CSV-Export
- Übernahme aus **Divera-24/7-Formularen** mit frei zuordenbaren Feldern
- **„Freigegeben, bitte bestellen"** mit **Bestellberechtigungen** je Rolle und
  Funktion, auch mit Betragsgrenze und Vier-Augen-Prinzip
- **Bestellungen** fassen mehrere Wünsche zusammen – eine Nummer, ein
  Lieferant, eine Rechnung; Buchungen gehören zu mehreren Wünschen und
  Fahrzeugen

### Abstimmungen
- **Umfragen** mit einer oder mehreren Seiten, hier zusammengeklickt: Auswahl,
  Ankreuzen, Ja/Nein, Skala, Freitext, Hinweistexte, Pflichtfragen
- Über den Connector im Netz: **QR-Code** zum Scannen, **Link** zum
  Weitergeben, **Zeitfenster** mit Beginn und Ende
- Antworten werden im Browser **verschlüsselt**, liegen beim Connector nur
  als Geheimtext und sind nach dem Abholen dort gelöscht; Auswertung mit
  Balken, Durchschnitt und CSV
- **Ergebnis per E-Mail** an einen oder mehrere Kontakte: täglich, wöchentlich oder am
  Ende, auf Wunsch mit KI-Zusammenfassung; dafür ein eigener
  **E-Mail-Versand** über SMTP in der Verwaltung, mit Testnachricht

### Budget
- Jahresbudget, **Ausgaben** (Haus, Nebenkosten, Getränke, Tanken …) und
  **Einnahmen** (Einsätze, technische Hilfeleistung, Spenden …)
- Übersicht nach Kategorie und Monat mit Grafik
- **Auslagen**: an jeder Ausgabe, welcher Helfer vorgestreckt hat und wann
  erstattet wurde; die Liste zeigt, wer noch auf sein Geld wartet
- **Termine mit Fahrzeugen und Standorten**: Fahrzeuge je Termin, mehrere
  Plätze aus den Stell- und Lagerplätzen plus freier Ort, auch bei
  Veranstaltungen; die Platzseite zeigt ihre Belegung; die Fahrzeugakte zeigt ihre
  Termine, die Startseite, welche Fahrzeuge diese Woche verplant sind
- **KI-Auswertung der Dateien** (abschaltbar, standardmäßig aus): ein Ollama im
  eigenen Netz fasst Dokumente und Fotos zusammen und vergibt Schlagwörter;
  Dateisuche über alle Ablagen
- **Hochrechnung aufs Jahresende**: wiederkehrende Ausgaben je Kategorie aus
  dem Durchschnitt der letzten zwölf Monate (Tanken) oder als fester Betrag
  (Telefon), dazu Energie und Wasser aus den Zählern – voraussichtlich frei
  oder überzogen, auch in der Budgetübersicht
- **Objektkonten** als zweite Auswertung: mehrere Kategorien laufen auf ein
  Konto (etwa Getränke und Lebensmittel auf Verpflegung), die Statistik nach
  Kategorien bleibt daneben bestehen
- **Budgettöpfe verwalten**: anlegen, ändern, stilllegen und zum Jahreswechsel
  aus dem Vorjahr übernehmen
- **Stand je Ausgabe**: bezahlt, gebucht (Rechnung liegt vor) oder geplant;
  die Übersicht zeigt Gebuchtes und Geplantes getrennt, dazu die Kosten der
  Veranstaltungen samt Verpflegung
- **Stichtag** fürs Jahresbudget: danach lehnt die Anwendung Ausgaben ab,
  mit Countdown und Erinnerung an die Leitung
- **Einnahmen planen**: Einsatz abgerechnet → Rechnung oder Gebührenbescheid
  der Regionalstelle → Mittel zugesagt → eingegangen, je Stufe mit Datum,
  Betrag und Nummer; Übersicht nach Stufen, Forderungen und Kürzungen;
  Ampel für Abrechnungen, die 30, 60 oder 90 Tage auf Geld warten, und eine
  fertige **Anfrage an die Regionalstelle** zum Bearbeitungsstand
- **Nebenkosten-Anhalt** aus dem Verbrauchsmodul für die Planung des
  nächsten Jahres
- Auf Wunsch „unscharf" gerundet auf 10, 100 oder 1.000

### Aufgaben
- Für den Ortsverband, Fachgruppen, Funktionen oder einzelne Personen

### Kalender
- **Alle Termine an einem Ort**: eigene Termine für sich, die Fachgruppe,
  eine Funktion oder den Ortsverband, dazu Besprechungen, Veranstaltungen,
  Fristen, fällige Aufgaben und Budget-Stichtage aus den Modulen
- **Feiertage** des Bundeslands gerechnet, **Kalender aus Home Assistant**
  eingebunden
- **In Besprechungen mitnehmen**: anstehende Termine mit einem Klick auf die
  Tagesordnung

### Funkgeräte
- HRT, MRT, Feststationen und Meldeempfänger mit Seriennummer, Inventarnummer,
  Funkrufname und Prüffrist
- **Karten hineinbuchen**, Geräte **Fahrzeugen** zuordnen – von der
  Fahrzeugakte bis zur ISSI durchsehbar
- **Gruppen** für Koffer, Ladeschalen und Sätze
- **QR-Code am Lagerort**: „Gerät ist da" oder „alle 8 Geräte sind da",
  verschlüsselt über den Connector
- **Fest verbaute Geräte** (MRT, Feststation) zählen am Lagerort nicht mit
  und gelten als gesehen, sobald ihr Fahrzeug einen Standort meldet

### Verbrauch
- **Strom-, Gas- und Wasserzähler** mit Ständen aus **Home Assistant**
  (Abfrage im einstellbaren Takt), von Hand oder **per QR-Code am Zähler**
- **Tarife** mit Zeitraum, Verbrauch und Kosten je Monat und Jahr; Warnung,
  wenn ein Zähler lange keinen Stand hat oder ein Monat ohne Tarif ist
- **Kosten je Bereich**: Verbrauch und Kosten je Gebäude, Halle, Stockwerk
  entlang der Stell- und Lagerplätze, mit Anteil am Hauptzähler
- **Unterzähler** für Stockwerke oder Hallen mit Anteil am Hauptzähler,
  ohne doppelte Kosten
- **Solaranlage**: Erzeugung und Einspeisung als eigene Zähler, daraus
  Eigenverbrauch, Gesamtverbrauch und Autarkie; Erlös über die
  **Einspeisevergütung**
- **Auffälliger Verbrauch**: Sprung gegenüber den Wochen davor oder nachts
  laufendes Wasser, als Warnung und als Meldung; Erinnerung „Zähler bitte
  ablesen"
- **Berichte zum Drucken**: je Zähler mit Vorjahresvergleich und fürs ganze
  Jahr für Präsentationen; **Verbrauchsprofil** je Wochentag und Tagesstunde
  mit den stärksten Zeiten
- CSV-Export aller Stände

### Sicherung
- Datenbank und Dateiablage als **ZIP** sichern, herunterladen, hochladen
  und **wiederherstellen** – mit Sicherheitskopie vorher
- auf Wunsch **automatisch** alle *n* Tage, ältere werden ausgedünnt

### SIM- und TETRA-Karten
- **Mobilfunk** mit Rufnummer, ICCID und Vertrag oder **TETRA-Sicherheitskarte**
  mit ISSI und OPTA
- Vertrag und PIN/PUK nur, wenn die Karte sie hat – beides wird zugeschaltet
- Anbieter, Tarif, Datenvolumen, Kosten und Vertragsende
- Zuordnung zu **Fahrzeug, Fachgruppe, Person** oder zum Ortsverband; in der
  Fahrzeugakte stehen die Karten des Fahrzeugs
- **Arten und Status frei pflegbar** (Datenkarte, Telefon, Fahrzeugrouter,
  Tablet, Telemetrie, Reserve …)
- PIN und PUK verdeckt und nur für die Leitung, CSV-Bestandsliste
- Warnung, wenn ein Vertrag ausläuft

### Kontakte
- Ansprechpartner mit eigenen Zusatzfeldern
- **Anrufen und mailen mit einem Tipp**; Rufnummern werden international
  gespeichert (+49 …)
- Verteiler für Einladungen mit Rückmeldung, CSV für den Serienbrief
- Import aus Excel/CSV, Outlook, Google Kontakte und vCard mit
  Dublettenerkennung

### Besprechungen
- Talking Points im Themenspeicher, Tagesordnung mit Zeitplan
- **Anmerkungen und Diskussion** zu jedem Punkt, solange er nicht abgeschlossen ist
- **Themenarchiv**: besprochene, beschlossene, abgelehnte und vertagte Themen
  mit Ergebnis und Aufgaben, durchsuchbar
- **Themen über Divera einreichen**: ein Divera-Formular füllt den Themenspeicher
- **Status-Rückmeldung an Divera**: Weitergeleitet → In Bearbeitung → Abgeschlossen
- **Aufgaben aus der Besprechung**: je Punkt vorausgefüllt, mehrere Punkte auf
  einmal übernehmen oder frei anlegen; Übersicht in Besprechung und Protokoll
- **Bezüge je Punkt** zu Terminen, Veranstaltungen (mit Budgettopf und
  Verpflegung im Protokoll), Fahrzeugen und Funkgeräten – umgekehrt in
  Fahrzeugakte, Gerät, Termin und Veranstaltung sichtbar
- **Budgetübersicht als Tagesordnungspunkt**: Stand und Verwendung nach
  Zweck mit Anteilen für Ausgaben und Einnahmen, auch im Protokoll
- **Wiederkehrende Termine** („alle 2 Wochen montags", „jeden 2. Montag im Monat")
- **Mehrere Tage** (Klausur, Lehrgang), **Fahrzeuge** mit Warnung vor
  Doppelbuchungen und **externe Teilnehmer** aus den Kontakten je Besprechung
- **In der Unterkunft, online oder außer Haus**: „Jetzt beitreten" mit dem
  Webex- oder Teams-Link, „Navigation starten" zur Anschrift
- **Anwesenheit**: teilgenommen, entschuldigt, nicht erschienen
- Tagesordnung und Protokoll zum Drucken

### Veranstaltungen
- Termin, Ort, Status und Beschreibung; Budgettopf und geplante Kosten
- **Arten**: Ausbildung, Übung, Helferversammlung, Empfang, Grillabend, Feier,
  Tag der offenen Tür, Jugendveranstaltung – erweiterbar, mit eigener Farbe
- **Ausgaben im Blick**: Buchungen aus dem Budgetmodul lassen sich der
  Veranstaltung zuordnen, die Seite zeigt geplant gegen tatsächlich
- **Dateien** wie Rechnungen, Angebote und Programm
- **Fahrzeuge** je Veranstaltung mit Warnung vor Doppelbuchungen – Fahrzeugakte
  und Startseite zeigen sie als verplant
- **Gästeliste aus den Kontakten** – einzeln, als ganzer Verteiler oder frei
  mit Namen
- **Oder nur eine Zahl**: Ausbildungen und Übungen kommen ohne Gästeliste aus –
  dort zählen nur Teilnehmer geplant und tatsächlich
- **Einladung per kurzer Adresse** (`https://i.example.de/AB23CD`): zusagen,
  absagen, Begleiter ankündigen oder eine Vertretung nennen – ohne Zugang zur
  Anwendung, verschlüsselt über einen Connector
- **Einlassliste zum Drucken** mit Kästchen zum Abhaken und der Summe der
  erwarteten Personen; Gästeliste als CSV für Excel
- **Einladungsliste für den Serienbrief**: Anschrift, Briefanrede,
  Einladungslink und QR-Code je Person
- Je Veranstaltung einstellbar: wie viele Begleiter erlaubt sind, ob
  Kommentare und Vertretungen erlaubt sind, bis wann zurückgemeldet wird und
  wie lang der Einladungscode ist
- **Verpflegung kalkulieren** für Ausbildung, Übung und Einsatz: **Tagessatz**
  je Person mit Gültigkeit von–bis, prozentual auf Frühstück, Mittag- und
  Abendessen verteilt; Dauer und Mahlzeiten je Kalendertag aus dem Zeitraum
  vorbelegt, Rechnung neben dem schon Gebuchten

### Fahrzeuge
- **Fahrzeugakte** mit Funkrufname, Kennzeichen, ISSI, OPTA, RIC und den
  Fristen HU, SP und UVV; Sitzplätze und maximale Zuladung
- **Kapazitätsliste** zum Planen von Fahrten: Personen und Zuladung je
  Fahrzeug, filterbar nach Art, Fachgruppe, Status und Mindestwerten, mit
  Summe der Auswahl
- **Journal, das sich nicht nachträglich ändern lässt** – jede Änderung, jeder
  Auftragsschritt, jede Meldung; eine Prüfsummen-Kette zeigt Manipulationen an
- **Instandsetzungsaufträge** mit eigener Nummer, der Nummer der
  THW-Verwaltung, Werkstatt, Kosten und Bearbeitungsstand
- **Sortierbare Liste und Kachelansicht** mit Stempeln „Nicht einsatzbereit",
  „In Wartung", „Bedingt einsatzbereit" und „Im Einsatz" (auch aus dem
  Divera-Funkstatus), Favoriten je Person
- **Wünsche je Fahrzeug**: Ersatzteile und Ausstattung hängen an der Akte
- **Karte in der Fahrzeugakte** (OpenStreetMap) mit der letzten Position
- **„Jetzt Position setzen"** am Handy: Standort des Geräts übernehmen
- **QR-Code je Fahrzeug**: Standort melden ohne Anmeldung, verschlüsselt über
  den mitgelieferten Connector; Parkpositionen landen im Journal
- **Bilder** (mit Titelbild) und **Dokumente** an Fahrzeugen
- **Fotogalerie je Auftrag**: Fotos beim Melden direkt aus der Kamera anhängen,
  Dokumente getrennt davon; Fotos verlieren beim Speichern den Aufnahmeort

### STAN
- Die **Stärke- und Ausstattungsnachweisung** als Dokumentenpaket: ein ZIP
  hochladen, jedes Paket zählt als neue Version; Dateien ausgepackt und
  einzeln abrufbar, ältere Versionen bleiben, einzelne PDFs nachreichbar
- **Durchsuchbar** nach Name, Inhalt (Volltext aus PDF und Office) und, mit
  KI-Auswertung, nach Zusammenfassung und Schlagwörtern je Dokument

### Connector
- Kleines PHP-Skript auf einem öffentlichen Webserver für alles, was ohne
  Anmeldung erreichbar sein muss: Einladungen, Standortmeldung, „Gerät ist
  da", Zählerstand per QR-Code
- Ende-zu-Ende verschlüsselt (ECDH, AES-GCM), der Connector sieht nur
  Chiffrate; die Anwendung holt die Meldungen alle zwei Minuten ab
- **Fußzeile mit Betreiber, Impressum und Datenschutz**, aus den
  Einstellungen der Anwendung an alle Connectoren verteilt
- **„Ist der Connector sauber?"**: Prüfsummen aller Dateien und Suche nach
  fremden Dateien, täglich geprüft, Meldung an Home Assistant bei Befund
- **Störungsmeldung**, wenn ein Connector länger nicht antwortet, und
  Entwarnung, wenn er wieder da ist

### Home Assistant
- **Kennzahlen über MQTT** mit Auto-Discovery aus jedem Modul: Budget mit
  Jahresbudget, Ausgaben und Einnahmen, Wünsche, Aufgaben, Themen, nächste
  Besprechung und Veranstaltung, Fahrzeuge und Fristen, Funkgeräte,
  SIM-Karten, Verbrauch und Solar, Sicherungen und Connectoren
- Nur Zahlen, Zeitpunkte und Beträge – keine Namen, Kennungen oder Rufnummern
- Optional **je Fahrzeug eigene Entitäten** samt Standort auf der Karte
- Zugang kommt vom Mosquitto-Add-on oder aus den Einstellungen
- **Web Push im Browser** – ohne Companion-App, auch bei geschlossener Seite
- **Benachrichtigungen aufs Handy** über die Companion-App: neue Aufgabe,
  fällige Aufgaben, neuer Wunsch zur Freigabe, Instandsetzungsmeldung,
  Fahrzeugausfall, ablaufende Fristen, Besprechung am nächsten Tag

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
- **THW-Kontakte** als eigener Menüpunkt: Dienststellen als Baum
  (Landesverband › Regionalstelle › Ortsverbände) mit Anschrift und
  Erreichbarkeit, dazu Funktionspostfächer und zentrale Rufnummern mit Zweck
  und den Haken „offizielles Netz" / „Behördennetz"
- **Stell- und Lagerplätze** als Baum: Gebäude, Hallen und Höfe mit
  Stockwerken, Räumen, Stellplätzen, Schränken und Regalen, Serien wie
  „Raum 1–16" auf einmal – je Platz mit Bildern, GPS-Koordinaten und Karte;
  Fahrzeuge, Zähler, Funkgeräte und Funkgruppen werden Plätzen zugeordnet;
  **Standortübersicht** für alle zum Durchklicken, was wo ist, mit
  **Hallenplan**: Foto oder Skizze, auf der jedes Fahrzeug an seinem Platz steht
- **Entfernungen (!! TEST !!)**: Luftlinie und Fahrstrecke von der Unterkunft
  zu Terminen, Veranstaltungen und THW-Dienststellen – über OpenStreetMap
  und den OSRM-Demoserver, standardmäßig aus, eine Anfrage je Abruf
- **Zweiter Faktor** mit Authenticator-App und Backup-Codes, als Pflicht je
  Rolle einstellbar
- Alle Auswahllisten, Texte und Regeln frei einstellbar; **Menüleiste in
  eigener Reihenfolge**
- Protokoll aller Änderungen; unten auf jeder Seite steht die laufende
  Version, ein Klick zeigt, was neu ist
- Jede Veröffentlichung hat eine eigene vierstellige Fassungsnummer

Die ausführliche Anleitung steht im Reiter **Dokumentation**.

*Privates Projekt, kein offizielles Angebot des THW.*
