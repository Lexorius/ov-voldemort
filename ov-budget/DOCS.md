# OV-Multitool

Verwaltung für einen THW-Ortsverband: Wünsche und Budget, Aufgaben, Kontakte,
Besprechungen und Fahrzeugakten – mit Anbindung an Stein.APP und Divera 24/7.
Das Add-on bringt **alles mit** – Datenbank, Webserver und Anwendung stecken im
Container. Weitere Add-ons sind nicht nötig.

Unten auf jeder Seite steht die laufende Version; ein Klick darauf zeigt, was
sich zuletzt geändert hat.

## Installation

1. **Einstellungen → Add-ons → Add-on Store → ⋮ → Repositories** und
   `https://github.com/Lexorius/ov-voldemort` hinzufügen.
2. In der Liste erscheint *OV-Multitool*. **Installieren** – der erste Build dauert
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
| `zeitzone` | Zeitzone für Anwendung und Datenbank, Vorgabe `Europe/Berlin`. Leer = die von Home Assistant. |
| `admin_username` | Benutzername des ersten Zugangs. Nur beim allerersten Start angelegt. |
| `admin_password` | Mindestens 10 Zeichen. Leer lassen: dann erzeugt der erste Start ein Zufallspasswort und schreibt es ins Protokoll. |
| `db_name` | Name der Datenbank, Vorgabe `ovbudget`. |
| `db_host`, `db_port`, `db_user`, `db_password` | **Leer lassen.** Nur ausfüllen, wenn statt der mitgelieferten Datenbank eine vorhandene genutzt werden soll. |
| `debug` | PHP-Meldungen im Browser. Nur zur Fehlersuche. |

### Beispiel

```yaml
ov_name: THW Ortsverband Musterstadt
zeitzone: Europe/Berlin
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

### Sicherung und Wiederherstellung

Unter *Verwaltung → Sicherung* (nur Administration) entsteht auf Knopfdruck
eine ZIP-Datei mit der ganzen Datenbank und der Dateiablage. Sie lässt sich
herunterladen, auf dem Server aufheben und jederzeit wiederherstellen.

* **Inhalt:** `datenbank.sql` (alle Tabellen mit Struktur und Inhalt),
  `dateien/` (Angebote, Fahrzeug- und Veranstaltungsdateien) und
  `sicherung.json` (Fassung, Zeitpunkt, Anlass, Zahlen). Der Auszug ist
  gewöhnliches SQL und ließe sich notfalls auch mit dem `mysql`-Befehl
  einspielen.
* **Ablage:** im Datenordner unter `sicherungen/` (im Add-on
  `/data/uploads/sicherungen`). Damit sind die Sicherungen zugleich Teil eines
  Home-Assistant-Backups.
* **Automatisch:** unter *Einstellungen → Sicherung* alle *n* Tage, nachts
  mit dem Abruf über `cron.php`. Es bleiben so viele, wie dort eingestellt;
  von Hand angelegte Sicherungen werden nie automatisch gelöscht.
* **Wiederherstellen** ersetzt Datenbank und Dateiablage vollständig durch
  den gesicherten Stand. Es verlangt das eigene Passwort und ein getipptes
  Bestätigungswort. Vorher legt die Anwendung selbst eine Sicherung des
  aktuellen Stands an (*vor-wiederherstellung*), danach laufen die
  Wanderungen, damit auch eine Sicherung aus einer älteren Fassung passt.
  Nach dem Einspielen gelten die Benutzer aus der Sicherung – auch der
  eigene Zugang.
* **Hochladen:** Eine früher heruntergeladene Sicherung lässt sich wieder auf
  den Server bringen, etwa nach einem Umzug; sie erscheint dann in der Liste.
* **Vertraulich behandeln:** Passwörter stehen darin nur als Hash, aber die
  Zugangsdaten zu Divera, Stein.APP und MQTT sowie PIN und PUK der SIM-Karten
  im Klartext.

Bei einer Handinstallation braucht PHP die Erweiterung `zip`; im Add-on ist
sie enthalten.

### Sicherheit im Betrieb

* **`public/install.php` nach der Einrichtung löschen.** Der Assistent
  verweigert zwar die Arbeit, sobald eine Konfiguration mit erreichbarer
  Datenbank oder Umgebungsvariablen da sind – aber eine Datei, die es nicht
  gibt, kann niemand versuchen. Im Add-on ist sie gar nicht erst enthalten.
* **Nur über HTTPS betreiben.** Das Sitzungscookie wird dann als `secure`
  gesetzt; dahinter darf ein Proxy stehen, der `X-Forwarded-Proto` setzt.
* **Der Token für `cron.php` steht in der Adresse** und damit im
  Zugriffsprotokoll des Webservers. Ihn nur dort verwenden, wo das Protokoll
  nicht in fremde Hände kommt, oder den Abruf über die Kommandozeile laufen
  lassen (`php public/cron.php`).
* **Exporte sind gegen Formeln geschützt.** Eine Zelle, die mit `=`, `+`,
  `-` oder `@` beginnt und keine Rufnummer oder Zahl ist, bekommt im CSV ein
  Hochkomma davor. Tabellenkalkulationen führen sie dann nicht aus.
* **Anmeldesperre:** Nach acht Fehlversuchen (einstellbar) ist ein
  Benutzername 15 Minuten gesperrt. Wer die Sperre absichtlich auslöst, kann
  damit eine Person kurz aussperren – die Zahlen lassen sich in den
  Einstellungen anpassen.

## Was kann die Anwendung?

* **Wünsch dir was** – Bedarfe mit Bezeichnung, Anzahl, Betrag,
  Dringlichkeit, Fachgruppe, Kategorie, Status, „nice to have", Frist,
  Angebots-Uploads, Abstimmung und Kommentaren. CSV-Export inklusive.
* **Aufgaben** – für den Ortsverband, einzelne Fachgruppen, Funktionen oder
  Personen. Wer angemeldet ist, sieht unter „Für mich" alles aus dem eigenen
  Zuständigkeitsbereich.
* **Kalender** – alle Termine an einem Ort, siehe den eigenen Abschnitt
  unten.
* **Budget** – Gesamtbudget je Haushaltsjahr, **Ausgaben** (Haus, Nebenkosten,
  Getränke, Tanken ...) und **Einnahmen** (Kostenerstattung für Einsätze,
  technische Hilfeleistung, Spenden ...) mit Einsatz- oder Auftragsnummer.
  Die Übersicht rechnet Budget plus Einnahmen minus Ausgaben und schlüsselt
  nach Kategorie und Monat auf. Mehrwertsteuer spielt dabei keine Rolle: Es
  gibt je Buchung und je Wunsch einen Betrag – den, der tatsächlich fließt.
  Jede Ausgabe hat einen **Stand**: *bezahlt*, *gebucht* (Rechnung liegt
  vor oder ist bestellt, noch nicht bezahlt – zählt schon als Ausgabe und
  wird in den Kacheln gesondert genannt) oder *geplant* (noch keine Rechnung
  – zählt nur in der Planung). Ein Zahlungsdatum setzt den Stand auf
  bezahlt. Übersicht, Mittel-Balken und Verlauf zeigen gebucht und geplant
  getrennt. Das Jahresbudget kann einen **Stichtag** haben: Bis
  einschließlich diesem Tag darf Geld auf das Jahresbudget ausgegeben
  werden, spätere Ausgaben lehnt die Anwendung ab – nur geplante lassen sich
  noch vormerken. Übersicht und Startseite zählen die Tage herunter, die
  Leitung wird 30, 14, 7 und 1 Tag vorher und am Tag selbst erinnert.
  **Einnahmen gehen einen längeren Weg:** *erwartet* (Einsatz gelaufen,
  Abrechnung noch nicht erstellt) → *abgerechnet* (Einsatzabrechnung
  eingereicht) → *gestellt* (die Regionalstelle hat Rechnung oder
  Gebührenbescheid gestellt) → *zugesagt* (Mittel sind dem OV versprochen)
  → *eingegangen / zugewiesen*. Je Stufe lassen sich Datum, Betrag und beim
  Bescheid die Nummer eintragen; jedes Datum hebt den Stand auf seine Stufe.
  Nur Eingegangenes zählt als Einnahme und ins verfügbare Budget; Zugesagtes
  steht darunter getrennt („mit Zusagen … verfügbar"), abgerechnet und
  gestellt sind **Forderungen**, erwartet ist Planung. Der Verlauf über das
  Jahr stapelt je Monat eingegangen, zugesagt und Forderungen sowie bezahlt
  und offene Rechnungen. Die Einnahmenliste zeigt oben den **Stand der Einnahmen**: je
  Stufe Anzahl und Summe (Klick filtert), was der OV haben sollte, und wo
  Beträge bekannt sind, um wie viel die Bescheide hinter den Abrechnungen
  zurückbleiben. Jede Zeile nennt ihren Weg mit Daten, Nummer und Beträgen.
  **Ampel:** Eine Abrechnung, die 30 Tage nach dem Abrechnungsdatum (sonst
  Bescheid, sonst Buchung) noch nicht eingegangen ist, wird gelb, ab 60
  Tagen orange, ab 90 rot – in der Liste, im Stand der Einnahmen, in der
  Budgetübersicht und auf der Startseite. Die Leitung bekommt je Stufe eine
  Meldung. **Anfrage an die Regionalstelle:** Der Knopf im Stand der
  Einnahmen baut eine E-Mail, die alle wartenden Abrechnungen mit Datum,
  Betrag, Einsatz- und Bescheidnummer aufzählt und um den Bearbeitungsstand
  bittet – wahlweise nur die über 30 Tage, einzelne lassen sich abwählen.
  Text kopieren, drucken oder im Mailprogramm öffnen; Anrede und Adresse der
  Regionalstelle stehen unter *Einstellungen → Budget*. Der Abschnitt **Offen und
  geplant** der Übersicht listet alles ohne Zahlung, dazu die
  **Veranstaltungen des Jahres** mit geplanten Kosten und kalkulierter
  Verpflegung abzüglich dessen, was schon gebucht ist; die Kachel „Noch frei"
  sagt, was nach dieser Planung bleibt. Gibt es Zähler im Verbrauchsmodul,
  zeigen Übersicht und Jahresbudget die **Nebenkosten des Vorjahres** – im
  laufenden Jahr aufs ganze Jahr hochgerechnet – als Anhalt für Strom, Gas
  und Wasser. Optional unterteilen **Budgettöpfe** das Jahr;
  sie werden unter *Budget → Budgettöpfe verwalten* gepflegt – anlegen,
  ändern, stilllegen und zum Jahreswechsel aus dem Vorjahr übernehmen.
  Mit **„Freigegeben, bitte bestellen"** wird ein Wunsch zur Bestellung
  freigegeben; die Übersicht listet alles Freigegebene, bis es als bestellt
  markiert ist. Mehrere freigegebene Wünsche beim selben Lieferanten werden
  angehakt und als **eine Bestellung** zusammengefasst (*Budget →
  Bestellungen*): Sie bekommt eine Nummer (B-2026-003), Lieferant, Datum und
  Auftragsnummer, die Wünsche werden „bestellt". „Rechnung erfassen" legt
  die Buchung mit allen Wünschen und ihren Fahrzeugen vor; „geliefert" setzt
  die Wünsche auf beschafft, „storniert" gibt sie wieder frei. Jede Buchung
  lässt sich außerdem **mehreren Wünschen und mehreren Fahrzeugen** zuordnen
  – Wunsch und Fahrzeugakte zeigen ihre Buchungen samt Summe. Wer freigeben (optional bis zu einem Betrag) und wer
  bestellen darf, wird unter *Verwaltung → Bestellberechtigungen* je Rolle und
  je Funktion festgelegt – etwa Ortsbeauftragte:r unbegrenzt, Zugführer:in bis
  500 €, Verwaltungsbeauftragte:r bestellt.
* **Fahrzeuge** – Fahrzeugstamm mit Funkrufname, **ISSI**, Kennzeichen und
  Fristen (HU, SP, UVV) und je Fahrzeug eine
  **Fahrzeugakte**. Ihr **Journal** wird nur ergänzt: Einträge lassen sich weder
  ändern noch löschen, jeder trägt die Prüfsumme des vorherigen, und
  „Journal auf Veränderungen prüfen" rechnet die Kette nach. Schadensmeldungen
  und **Instandsetzungsaufträge** mit fortlaufender Nummer, Werkstatt, Kosten
  und Bearbeitungsstand – jeder Schritt landet in der Akte. Dazu die **Nummer
  der THW-Verwaltung**: Sie vergibt die Nummern, hier werden sie übernommen
  und sind durchsuchbar; jede Änderung steht im Journal.
  Die Fahrzeugliste lässt sich **sortieren** (Name, Status, Fachgruppe,
  Kennzeichen, nächste Frist, offene Aufträge, Kilometerstand, Funkstatus,
  zuletzt angelegt), als **Liste oder Kacheln** anzeigen, und einzelne
  Fahrzeuge lassen sich mit dem Stern **anheften** – sie stehen dann oben und
  lassen sich allein anzeigen. Sortierung und Ansicht merkt sich die Sitzung.
  In der Kachelansicht liegt bei nicht einsatzbereiten Fahrzeugen ein schräger
  Stempel über dem Bild (auch bei „In Wartung" und „Ausgemustert").
  **Wünsche** lassen sich einem Fahrzeug zuordnen – etwa Ersatzteile oder
  Ausstattung. Sie erscheinen dann in der Fahrzeugakte, und in der Wunschliste
  lässt sich nach Fahrzeug filtern.
  **Bilder** je Fahrzeug (mit Titelbild in Liste und Akte) und **Dokumente**
  wie Fahrzeugschein oder Prüfberichte. Jeder Instandsetzungsauftrag hat eine
  eigene **Fotogalerie** – Fotos lassen sich schon beim Melden anhängen (am
  Handy direkt aus der Kamera) und später jederzeit ergänzen – dazu eine
  getrennte Liste für Dokumente wie Kostenvoranschlag, Werkstattbericht oder
  Rechnung. In der Auftragsliste des Fahrzeugs steht, wie viele Fotos und
  Dokumente hängen. Fotos werden
  neu gespeichert, damit der Aufnahmeort aus den Metadaten verschwindet.
* **Stein.APP** – Abgleich der Fahrzeuge über die Schnittstelle der Stein.APP
  (gleicher API-Schlüssel wie für die Home-Assistant-Integration). Alle zehn
  Minuten wird der komplette Stand geholt und jede Änderung an Status, HU, SP,
  Kennzeichen, Bemerkung oder Einsatzvorbehalt in die Fahrzeugakte geschrieben.
  Auf Wunsch sofort per Webhook; Mitschnitt der Antworten zur Fehlersuche.
* **Divera 24/7 – Fahrzeuge** – Funkstatus (FMS) als Fahrtenbuch im Journal,
  letzte Position mit Kartenlink, Besatzung sowie OPTA und RIC.
* **Funkgeräte** – HRT, MRT, Feststationen und Meldeempfänger mit
  Seriennummer, Inventarnummer, Funkrufname und Prüffrist. Karten werden in
  Geräte **gebucht**, Geräte einem **Fahrzeug** (oder einer Fachgruppe, einer
  Person) zugeordnet – von der Fahrzeugakte bis zur ISSI durchsehbar. Geräte
  lassen sich zu **Gruppen** bündeln (Koffer, Ladeschale, Satz), und auf Gerät
  wie Gruppe kann ein **QR-Code** für die Bestandskontrolle hängen. Siehe
  *Funkgeräte* weiter unten.
* **SIM- und TETRA-Karten** – Bestand mit Rufnummer, Kartennummer (ICCID), Anbieter,
  Tarif, Datenvolumen, Kosten und Vertragsende. Jede Karte gehört zu einem
  **Fahrzeug**, einer **Fachgruppe**, einer **Person** oder allgemein zum
  Ortsverband; in der Fahrzeugakte stehen die Karten des Fahrzeugs. Art und
  Status kommen aus den Auswahllisten und lassen sich erweitern. PIN und PUK
  stehen verdeckt und nur für die Leitung. Siehe *SIM-Karten* weiter unten.
* **Kontakte** – Ansprechpartner bei Kommune, Feuerwehr, Presse, Firmen und
  Förderern, dazu Verteiler für Einladungen mit Rückmeldungen und einer
  CSV-Ausgabe für den Serienbrief. Neben den Standardfeldern lassen sich
  eigene Felder definieren. Import aus Excel, Outlook, Google Kontakte und vCard
  mit Vorschau und Dublettenerkennung. **Rufnummern und E-Mail-Adressen sind
  anklickbar** – am Handy wählt ein Tipp direkt.

  Rufnummern werden beim Speichern und beim Import international geschrieben:
  aus „0151 123456" wird „+49 151 123456", aus „0201/1234567" wird
  „+49 2011234567". Die Landesvorwahl steht unter *Einstellungen → Allgemein*
  (Vorgabe +49) und gilt für Nummern mit führender 0. Nummern, die schon mit +
  oder 00 beginnen, bleiben bei ihrem Land. Steht im Feld ein Hinweis statt
  einer Nummer („über das Büro"), bleibt der Text unverändert stehen.
  Bestehende Kontakte werden beim nächsten Start einmalig umgeschrieben.
* **Veranstaltungen** – Termin, Ort, **Art** (Ausbildung, Übung,
  Helferversammlung, Empfang, Grillabend, Feier, Tag der offenen Tür,
  Jugendveranstaltung – in der Verwaltung erweiterbar) und Status, dazu ein Budgettopf mit
  geplanten Kosten. Buchungen aus dem Budgetmodul lassen sich der Veranstaltung
  zuordnen; die Seite stellt geplant und tatsächlich nebeneinander und listet
  die Buchungen. **Rechnungen, Angebote und das Programm** hängen als Datei
  daran. Die **Gästeliste** kommt aus den Kontakten – einzeln, als ganzer
  Verteiler oder frei mit Namen. Jede eingeladene Person bekommt einen eigenen
  **Einladungscode**; über eine kurze Adresse sagt sie zu, ab, kündigt
  Begleiter an oder nennt eine Vertretung. Je Veranstaltung ist einstellbar,
  wie viele Begleiter erlaubt sind, ob Kommentare und Vertretungen erlaubt
  sind und bis wann zurückgemeldet wird. Für den Eingang gibt es die
  **Einlassliste zum Drucken** (mit Kästchen zum Abhaken) und die Gästeliste
  als **CSV**. Siehe *Veranstaltungen und Einladungen* weiter unten.
* **Besprechungen** – Talking Points im Themenspeicher sammeln, auf die
  Tagesordnung setzen, während der Sitzung Ergebnisse festhalten und daraus
  Aufgaben machen – einzeln über das vorausgefüllte Aufgabenformular, mehrere
  Punkte auf einmal mit gemeinsamer Zuständigkeit und Frist oder als freie
  Aufgabe der Besprechung. Die Besprechung listet alle ihre Aufgaben, die
  Aufgabe verweist zurück. Tagesordnung und Protokoll (mit Aufgabenliste) zum Drucken. Regelmäßige
  Termine wie „alle 2 Wochen montags" oder „jeden 2. Montag im Monat" als Serie.
  **Anwesenheit:** Eingeladene aus dem Ortsverband, aus den Kontakten (auch ganze
  Verteiler) oder als freier Name; je Person teilgenommen, entschuldigt oder
  nicht erschienen. Die Zählung steht über der Liste, die Namen im Protokoll.
* **Divera 24/7 – Formulare** – Formulare abrufen, Felder frei zuordnen und Einträge als
  Wünsche übernehmen. Mit Vorschau, Dubletten-Erkennung und optionalem
  automatischem Abruf. Ein Formular kann stattdessen auch **Themen für
  Besprechungen** sammeln: Jeder Eintrag wird ein Talking Point im
  Themenspeicher (Titel, Beschreibung, Fachgruppe, Priorität, Zeitbedarf,
  Einreicher). So lassen sich Themen direkt in Divera einreichen.

## Anmerkungen zu Talking Points

Jeder Talking Point hat eine eigene Seite – erreichbar über seinen Titel im
Themenspeicher, im Archiv und in der Besprechung. Dort lässt sich über den
Punkt diskutieren: Ergänzungen, Rückfragen, Gegenvorschläge.

* Anmerkungen gehen, **solange der Punkt nicht abgeschlossen ist**. Mit einem
  abschließenden Status (besprochen, beschlossen, abgelehnt, zurückgezogen)
  wird die Diskussion zu einem lesbaren Verlauf. Vertagte Punkte bleiben offen.
* Die eigene Anmerkung lässt sich entfernen, die Leitung kann jede entfernen –
  ebenfalls nur, solange der Punkt offen ist.
* Wer den Punkt eingebracht oder schon mitdiskutiert hat, wird benachrichtigt
  (Ereignis *Neue Anmerkung zu einem Talking Point*, einzeln abschaltbar).
* In der Besprechung steht bei jedem Punkt die Zahl der Anmerkungen.
* **Im Ausdruck** erscheinen die Anmerkungen klein unter dem jeweiligen Punkt.
  *Einstellungen → Besprechungen*: nur im Protokoll (Vorgabe), in Tagesordnung
  und Protokoll oder nie; dazu, ob Name und Tag mitgedruckt werden. Oben auf
  der Druckansicht lässt es sich für den einzelnen Ausdruck umschalten.

## Themenarchiv

Der Themenspeicher hat zwei Reiter: **Offen** (noch keiner Besprechung
zugeordnet oder vertagt) und **Archiv**. Das Archiv zeigt alle Themen, die auf
einer Besprechung standen, und abgeschlossene Themen ohne Besprechung – etwa
abgelehnte. Je Thema: Besprechung und Datum (verlinkt), Status, Ergebnis,
Verantwortliche und die daraus entstandenen Aufgaben; bei vertagten Themen,
woher sie kamen und wohin sie weitergingen. Filter: Suche (auch im Ergebnis),
Status, Fachgruppe und Zeitraum.

Ein Thema ablehnen: Status **Abgelehnt** – in der Besprechung beim Ergebnis
oder direkt über *Bearbeiten* im Themenspeicher. Es verschwindet dann aus den
offenen Themen und steht im Archiv.

## Themen über Divera einreichen

1. In Divera ein Formular anlegen, z. B. „Thema für die Leitungsrunde“ mit den
   Feldern *Thema*, *Beschreibung*, *Fachgruppe*, *Priorität*, *Zeitbedarf
   (Minuten)* und *Eingereicht von*. Nur *Thema* ist wirklich nötig.
2. Unter *Verwaltung → Divera 24/7* „Formulare aus Divera abrufen“ und beim
   Formular **Als Themenformular** wählen.
3. „Formularfelder abrufen“ – die Felder werden anhand ihrer Namen
   vorgeschlagen; bei Bedarf anpassen, dann „Speichern und Vorschau“.
4. Für den automatischen Abruf den Haken *Beim automatischen Abruf
   berücksichtigen* setzen. Im Themenspeicher gibt es zusätzlich den Knopf
   **Aus Divera abrufen**, etwa kurz vor der Besprechung.

## Bearbeitungsstand an Divera zurückmelden

Je Formular lässt sich in der Feldzuordnung *Status der Einträge in Divera
setzen* einschalten. Die Einreichenden sehen dann in Divera, wie weit ihr
Eintrag ist:

| Bei uns | Status in Divera |
|---|---|
| Eintrag übernommen | Weitergeleitet |
| Wunsch im Status *freigegeben* bzw. Thema auf einer Tagesordnung | In Bearbeitung |
| Wunsch *bestellt*, beschafft oder abgelehnt bzw. Thema besprochen, beschlossen oder zurückgezogen | Abgeschlossen |

Welche Wunsch-Status als „In Bearbeitung“ und „Abgeschlossen“ gelten, steht
unter *Einstellungen → Divera 24/7* (Slugs, durch Komma getrennt);
abschließende Status zählen immer als abgeschlossen. Es geht nur vorwärts: Was
in Divera schon weiter ist, wird nicht zurückgesetzt. Der Abgleich läuft mit dem
automatischen Abruf (höchstens 25 Meldungen je Durchlauf), per Knopf *Status
jetzt abgleichen* auch sofort. Der persönliche Divera-Schlüssel braucht dafür
Bearbeitungsrechte am Formular; lehnt Divera ab, steht das im Divera-Protokoll.

Stimmt der Einreichername mit einem Benutzer überein, gilt das Thema als von
dieser Person eingebracht (sie kann es dann auch bearbeiten); sonst steht der
Name beim Thema. Nicht zugeordnete Felder landen in der Beschreibung.
* **Verwaltung** – Benutzer und Rollen (Mitglied, Leitung, Administration) sowie
  *alle* Auswahllisten, Texte und Regeln frei konfigurierbar.

## Funkgeräte

Das Modul führt die Geräte selbst – die Karten darin stehen im Kartenmodul.

* **Je Gerät:** Bezeichnung, Art (HRT, MRT, FRT, Meldeempfänger, Analogfunk …),
  Status, Hersteller, Modell, Seriennummer, Inventarnummer, Funkrufname,
  Standort, Beschaffung und Prüffrist.
* **Karten buchen:** Auf der Geräteseite lässt sich eine freie Karte in das
  Gerät buchen – auf Wunsch übernimmt sie dabei die Zuordnung des Geräts.
  *Entnehmen* legt sie wieder frei. Im Kartenformular steht dasselbe unter
  „Steckt in einem Funkgerät".
* **Zuordnung:** wie bei den Karten zu Fahrzeug, Fachgruppe, Person oder zum
  Ortsverband. Die Fahrzeugakte zeigt die Geräte des Fahrzeugs.
* **Gruppen** sind Lagereinheiten: Koffer, Ladeschale, Regal, der Satz im
  Fahrzeug. Ein Gerät gehört zu höchstens einer Gruppe; die Gruppenseite zeigt
  alle Geräte darin. Die Fachgruppe steht am Gerät unter „Gehört zu" – dafür
  braucht es keine Gruppe.
* **Fest verbaut:** Ein MRT im Fahrzeug oder eine Feststation liegt nie im
  Regal. Das Kennzeichen (Vorgabe aus der Art, änderbar) lässt das Gerät in
  seiner Gruppe stehen, nimmt es aber aus der Zählung „alle Geräte sind da"
  heraus. Gehört es zu einem Fahrzeug, gilt es als gesehen, sobald das
  Fahrzeug einen Standort meldet.

### „Ist am Lagerort" per QR-Code

Auf einem Gerät **oder** auf einer Gruppe lässt sich ein QR-Code erzeugen.
Wer ihn scannt, bekommt eine kleine Seite mit einem Knopf:

* am Gerät: **„Gerät ist am Lagerort"**
* an der Gruppe: **„Alle 8 Geräte sind da"** – oder *Nicht alle*, dann trägt
  man die Zahl ein. Gezählt werden nur die meldbaren Geräte, fest verbaute
  nicht.

Die Meldung geht über den **Connector** und ist im Browser verschlüsselt: Der
Server weiß, dass jemand gemeldet hat, aber weder was noch von wem. Er kennt
nur die Prüfsumme des Zugangs, ob es ein Gerät oder eine Gruppe ist und wie
viele Geräte dazugehören. Bezeichnung und Lagerort stehen im Anker der Adresse
(hinter dem `#`) und erreichen ihn nie.

In OV-Multitool steht danach je Gerät und Gruppe **zuletzt gesehen** samt Namen,
wenn er eingetragen wurde. Meldet jemand eine Gruppe **vollzählig**, gelten
alle Geräte darin als gesehen; bei einer kleineren Zahl bleibt es bei der
Gruppe – dann steht dort „6 von 8 Geräten gemeldet".

Dafür braucht der Connector die Verwendung **Funkgeräte** (*Verwaltung →
Connectoren*). Abgeholt wird im Takt des Standort-Intervalls und auf Knopfdruck.

## Kalender

Ein Monat auf einen Blick oder die nächsten 60 Tage als Liste – mit allem,
was im Ortsverband ein Datum hat. Die Quellen lassen sich oben einzeln ein-
und ausblenden:

* **Eigene Termine:** Jede Person legt Termine **für sich**, für die **eigene
  Fachgruppe** und für die **eigenen Funktionen** an (abschaltbar in den
  Einstellungen); die Leitung auch für den ganzen **Ortsverband** und für
  andere. Ein Termin hat Datum, Uhrzeit oder „ganztägig", optional ein Ende
  (auch mehrtägig), Ort, Beschreibung und Farbe. Sichtbar ist er für die, für
  die er gilt – und für die Leitung. Ein Klick auf eine Tageszahl legt einen
  Termin an diesem Tag an.
* **Aus den Modulen:** Besprechungen, Veranstaltungen (in der Farbe ihrer
  Art), fällige Aufgaben aus dem eigenen Bereich, Fristen (HU, SP, UVV der
  Fahrzeuge, Prüfungen der Funkgeräte, Vertragsenden der SIM-Karten) und der
  Stichtag des Jahresbudgets. Nichts davon wird kopiert – ein Klick führt
  zum Vorgang.
* **Feiertage:** Die gesetzlichen Feiertage des Bundeslands (*Verwaltung →
  Kalender*) werden gerechnet, auch für kommende Jahre, und markieren den Tag
  wie ein Wochenende.
* **Kalender aus Home Assistant:** Unter *Verwaltung → Kalender* stehen alle
  Kalender, die Home Assistant kennt – lokale Kalender, CalDAV, Google,
  Schulferien-Integrationen. Ausgewählte werden im eingestellten Takt geholt
  (30 Tage zurück, ein Jahr voraus) und erscheinen für alle; ohne Home
  Assistant lassen sich Entitäten auch von Hand eintragen.
* **In Besprechungen mitnehmen:** Eine geplante Besprechung zeigt die
  Termine der nächsten Wochen (Einstellung, Vorgabe sechs). Ausgewählte
  kommen als Punkt „Termin: … (Datum)" auf die Tagesordnung – mit Quelle und
  Ort in der Beschreibung –, werden besprochen und stehen im Protokoll.
* **Bezüge am Tagesordnungspunkt:** Jedes Thema kann auf **Termine**,
  **Veranstaltungen**, **Fahrzeuge** und **Funkgeräte** verweisen (Abschnitt
  „Bezüge" im Formular, mehrere je Art). Sie erscheinen als Kennzeichen am
  Punkt, auf der Themenseite und als Zeile im Protokoll; umgekehrt zeigen
  Fahrzeugakte, Gerät, Termin und Veranstaltung unter „In Besprechungen",
  wo sie besprochen wurden – mit Datum, Status und Ergebnis. Eine verknüpfte
  Veranstaltung bringt ihren **Kurzbericht** mit: Wann und wo, Budgettopf,
  geplante und gebuchte Kosten und die Verpflegungskalkulation (Personen,
  Mahlzeiten, Summe) – in der Tagesordnung, auf der Themenseite und im
  gedruckten Protokoll.
* **Budgetübersicht als Tagesordnungspunkt:** „+ Budgetübersicht" in der
  Tagesordnung (oder der Bezug „Budget" am Thema) erklärt in ganzen Sätzen,
  wie viel Geld da war (Zuweisung, eingegangene und zugesagte Einnahmen),
  wie viel ausgegeben ist und was übrig bleibt – dazu **wofür das Geld
  ausgegeben wurde** und **woher die Einnahmen kamen**, je mit prozentualen
  Anteilen, die vorab verteilten Töpfe und der letzte Tag für Ausgaben. So,
  dass es auch Gäste ohne Vorwissen verstehen. Der Stand wird beim Aufruf
  gerechnet; im Protokoll steht er als Text.
* Die Startseite zeigt die nächsten Termine der kommenden 14 Tage.

## Verbrauch: Strom, Gas und Wasser

Das Modul führt Zähler und ihre Stände und rechnet daraus Verbrauch und Kosten.

* **Zähler:** Art (Strom, Gas, Wasser), Name, Zählernummer, Standort, Einheit
  des Zählers. Für Gas zusätzlich eine **Umrechnung** m³ → kWh (Brennwert ×
  Zustandszahl, meist rund 10), wenn der Tarif je kWh gilt.
* **Rolle je Zähler:** Ein **Hauptzähler** misst den Bezug vom Versorger.
  Ein **Unterzähler** misst einen Teil davon (Stockwerk, Halle) – er hat
  keine eigenen Kosten und zählt nicht doppelt; die Zählerseite des
  Hauptzählers zeigt die Aufteilung mit Anteilen und dem nicht zugeordneten
  Rest. Für eine **Solaranlage** gibt es die Rollen **Erzeugung** (was der
  Wechselrichter liefert) und **Einspeisung** (was ins Netz zurückgeht,
  Zählwerk 2.8.0). Daraus rechnet die Übersicht: Eigenverbrauch = Erzeugung
  − Einspeisung, Gesamtverbrauch = Bezug + Eigenverbrauch, Autarkie =
  Eigenverbrauch / Gesamtverbrauch. Mit einem Tarif der Art
  **Einspeisevergütung** wird der Erlös ausgewiesen und von den Kosten
  abgezogen.
* **Auffälligkeiten:** Die Übersicht warnt, wenn ein Zähler in den letzten
  sieben Tagen je Tag mehr als das Eineinhalbfache (einstellbar) der bis zu
  acht Wochen davor verbraucht hat – das geht schon mit 14 Tagen Vergleich,
  also auch bei neuen Zählern – und bei Wasser, wenn zwischen 1 und 5 Uhr in
  jeder Stunde etwas lief (das braucht stündliche Stände, etwa aus Home
  Assistant). Beides geht auch als Meldung an die Leitung, dazu die
  Erinnerung „Zähler bitte ablesen" für Handzähler ohne frischen Stand.
* **Quelle je Zähler:**
  * **Home Assistant** – eine Entität aus der Liste wählen (Sensoren mit
    Energie-, Gas- oder Wassereinheit) oder die Kennung eintippen. Der Abruf
    über `cron.php` liest den Stand alle *n* Minuten (Vorgabe 60, mindestens
    5); gespeichert wird nur, was sich geändert hat. Ein
    Faktor rechnet um, wenn der Sensor etwa Wh statt kWh liefert. Der Sensor
    muss einen **Zählerstand** liefern, keinen Momentanwert.
  * **Von Hand** – am Bildschirm (Übersicht oder Zählerseite) oder per
    **QR-Code am Zähler**: Wer ihn scannt, tippt den Stand ein – ohne
    Anmeldung, verschlüsselt über den Connector (Verwendung *Zähler*).
* **Rechnung:** Zwischen zwei Ablesungen wird der Verbrauch gleichmäßig auf
  die Tage verteilt; so entstehen Monatswerte auch aus unregelmäßigen
  Ablesungen. Ein Rücklauf (Zählerwechsel) zählt nicht als Verbrauch.
* **Tarife** je Art mit Zeitraum von–bis, Arbeitspreis je Einheit und
  Grundpreis je Monat. Für jeden Monat gilt der Tarif, der am 15. gültig ist.
  Kosten = Arbeitspreis × Verbrauch (nach Umrechnung) + Grundpreis anteilig.
* **Übersicht:** je Zähler letzter Stand, Verbrauch in 30 Tagen und im Jahr,
  Kosten; je Art die Jahressumme. Zähler ohne Stand seit über 35 Tagen werden
  angemahnt. CSV-Export aller Stände.
* **Berichte** zum Drucken und für Präsentationen: der **Jahresbericht**
  (Knopf in der Übersicht) mit Kennzahlen je Art, Monatsbalken für Jahr und
  Vorjahr und einer Tabelle aller Zähler; der **Bericht je Zähler** (Knopf
  auf der Zählerseite) mit Verbrauch, Vorjahr, Kosten, Tagesdurchschnitt,
  stärkstem Monat, der Monatstabelle und dem **Verbrauchsprofil**: der
  Durchschnitt je Wochentag und je Tagesstunde, dazu der stärkste Wochentag,
  die stärksten Stunden am Tag und die stärksten Zeiten in der Woche. Das
  Profil steht auch auf der Zählerseite. Eine Tageskurve entsteht erst mit
  stündlichen Ständen (Home Assistant), ein Wochenprofil ab einem Stand je
  Tag; bei selteneren Ablesungen sagt die Seite das dazu. Im laufenden Jahr wird das Vorjahr
  bis zum selben Tag verglichen. Beide Seiten drucken sich sauber auf A4 –
  „Drucken / PDF" nutzt den Browserdruck.
* **Rechte:** Sehen nur Leitung und Administration, per Einstellung alle
  Mitglieder; wer sieht, darf auch Stände eintragen (abschaltbar).

## SIM-Karten

Wer im Ortsverband Karten für Router, Tablets und Diensthandys verwaltet,
kennt den Zettel im Schrank. Das Modul führt sie stattdessen sauber:

* **Zwei Kartenwelten:** **Mobilfunk** mit Rufnummer, ICCID und Vertrag –
  oder **TETRA-Sicherheitskarte** mit **ISSI** und **OPTA** statt Rufnummer.
  Die Auswahl steht oben im Formular; danach richten sich die Felder.
* **Angaben je Karte:** Rufnummer (international geschrieben wie im
  Kontaktmodul) beziehungsweise ISSI und OPTA, Kartennummer, Art, Status,
  Gerät („steckt in"), und – wenn vorhanden – Vertrag sowie PIN und PUK.
* **Vertrag nur, wenn es einen gibt:** Der Haken *Diese Karte hat einen
  Vertrag* blendet Anbieter, Tarif, Datenvolumen, Kosten und Laufzeit ein.
  Ohne Haken bleiben sie weg – TETRA-Karten und Karten aus dem Bestand des
  Landesverbands haben meist keinen eigenen Vertrag. Dasselbe gilt für
  *PIN und PUK hier hinterlegen*: ohne Haken werden beide Felder beim
  Speichern geleert.
* **Zuordnung:** zu einem **Fahrzeug**, einer **Fachgruppe**, einer **Person**
  oder – ohne feste Zuordnung – zum Ortsverband. Die Fahrzeugakte zeigt die
  Karten des Fahrzeugs gleich mit; von dort lässt sich eine neue Karte mit
  bereits gesetztem Fahrzeug anlegen.
* **Arten und Status** stehen unter *Verwaltung → Auswahllisten*
  (SIM-Kartenarten, Status (SIM-Karten)) und lassen sich erweitern,
  umbenennen und einfärben. Mitgeliefert: Datenkarte, Telefon,
  Fahrzeugrouter, Tablet, Telemetrie (M2M), Reservekarte sowie für den Funk
  TETRA HRT, MRT, FRT und Reservekarte – und die Status Im Einsatz, Reserve,
  Gesperrt, Gekündigt.
* **Vertragsende:** Karten, deren Vertrag in den nächsten 60 Tagen (Vorgabe,
  einstellbar) ausläuft oder schon abgelaufen ist, stehen oben in der
  Zählung und farbig in der Liste.
* **PIN und PUK** sieht nur die Leitung, und auch dort verdeckt – ein Klick
  auf *PIN* blendet den Wert ein. Ob alle Mitglieder die Karten überhaupt
  sehen, steht in den Einstellungen (Vorgabe: nein).
* **CSV** mit allen Angaben einschließlich PIN und PUK – nur für die Leitung.

Eine Rufnummer und eine ISSI können jeweils nur einmal vergeben werden; wer eine Karte ausmustert,
nimmt sie aus dem Bestand, statt sie zu löschen – dann bleibt sie zum
Nachschlagen erhalten.

## Impressum und Datenschutz auf dem Connector

Die offenen Seiten des Connectors (Standort melden, Einladung, Lagerort,
Zählerstand, Startseite) zeigen unten Betreiber, Impressum und Datenschutz.
Gepflegt wird das unter *Verwaltung → Einstellungen → Connector*: der Name
des Betreibers und zwei Adressen, etwa die Impressums- und Datenschutzseite
der Vereins-Website. Beim Speichern gehen die Angaben signiert an alle
gekoppelten Connectoren; ein gerade nicht erreichbarer bekommt sie beim
nächsten Abruf. Solange nichts eingetragen ist, bleibt die Fußzeile leer.

## Wenn der Connector nicht antwortet

Jeder Aufruf eines Connectors, der fehlschlägt, wird als Störung gemerkt –
mit Zeitpunkt und Grund, zu sehen unter *Verwaltung → Connectoren*. Dauert
sie länger als die Karenz (*Einstellungen → Connector*, Vorgabe 15 Minuten),
geht eine Meldung an die Leitung, einmal je Ausfall; antwortet der Connector
wieder, kommt eine zweite Meldung mit der Dauer. Home Assistant sieht die
Zahl gestörter Connectoren als Diagnose-Entität.

## Ist der Connector sauber?

Ein Connector steht auf einem öffentlichen Webserver – der einzige Teil, der
nicht hinter Home Assistant liegt. Deshalb prüft OV-Multitool ihn:
*Verwaltung → Connectoren → Connector → Connector prüfen*, und ohne Zutun
einmal täglich mit dem Abruf (abschaltbar unter *Einstellungen → Fahrzeuge*).

* **Maßstab:** OV-Multitool bringt die Prüfsummen (SHA-256) aller Dateien der
  Connector-Fassung mit, die es kennt. Der Connector meldet signiert, was bei
  ihm liegt. Verglichen wird: geändert, fehlt, fremd.
* **Über das Netz:** Die Skripte, die Besucher im Browser ausführen
  (`assets/*.js`), holt OV-Multitool zusätzlich selbst ab und vergleicht sie.
  Ein manipulierter Server kann in der Selbstprüfung lügen – nicht aber
  darüber, was er tatsächlich ausliefert. Dazu Stichproben: Ist `daten/`
  erreichbar, nimmt `?p=zustand` unsignierte Anfragen an, fehlen
  Sicherheitskopfzeilen oder HSTS?
* **Selbstprüfung dort:** Ausführbares in `daten/`, eine Ablage im
  Dokumentenverzeichnis, beschreibbare Programmdateien, `display_errors`.
* **Ergebnis:** *sauber*, *Hinweise* oder *ALARM*, mit jedem Befund im
  Klartext. Bei Alarm geht eine Nachricht an die Leitung (Ereignis
  *Connector-Prüfung schlägt an*), und die Seite sagt, was zu tun ist:
  Dateien ersetzen, `daten/` durchsehen, neu koppeln, QR-Codes neu erzeugen.

Beste Vorsorge: Der Webserver darf `public/` und `src/` des Connectors nicht
beschreiben, nur `daten/`. Dann kann eine Lücke keine Datei dort ablegen –
und die Prüfung sagt, ob das so eingerichtet ist.

## Veranstaltungen und Einladungen

Anlegen und ändern darf die **OV-Leitung**; ob alle Mitglieder Veranstaltungen
sehen, steht unter *Einstellungen → Veranstaltungen*.

### Gästeliste

Eingeladen wird aus dem Kontaktmodul: einzeln über die Suche, als ganzer
Verteiler oder – für alle, die dort nicht stehen – frei mit Namen. Jede Person
bekommt dabei einen **Einladungscode** aus Ziffern und Großbuchstaben, ohne
die leicht verwechselbaren (0/O, 1/I/L). Die Länge steht in den Einstellungen
und lässt sich je Veranstaltung ändern; sechs Zeichen sind die Vorgabe.

Rückmeldungen lassen sich jederzeit von Hand eintragen – für alle, die anrufen
statt zu klicken. In der Übersicht stehen Zusagen, Absagen, Offene und die
**Gesamtzahl der Personen**: die eingeladene Person (oder ihre Vertretung) plus
ihre Begleiter.

### Arten von Veranstaltungen

Jede Veranstaltung hat eine **Art**: Ausbildung, Übung, Helferversammlung,
Empfang, Grillabend, Feier, Tag der offenen Tür, Jugendveranstaltung oder
Sonstiges. Die Liste steht unter *Verwaltung → Auswahllisten →
Veranstaltungsarten* und lässt sich beliebig erweitern, umbenennen und
einfärben – die Farbe erscheint als Streifen und Plakette in der Übersicht.

In der Liste der Veranstaltungen lässt sich nach der Art filtern; auf den
Ausdrucken steht sie im Kopf.

### Mit Gästeliste oder nur mit einer Zahl

Nicht jede Veranstaltung braucht Namen. Bei einer **Ausbildung oder Übung**
reicht meist, wie viele gekommen sind. Deshalb hat jede Veranstaltung den
Schalter **„Gästeliste mit Einladungen führen"**:

* **an** – Gästeliste aus den Kontakten, Einladungscodes, Rückmeldungen,
  Einlassliste. So wie oben beschrieben.
* **aus** – keine Namen, keine Einladungen. Stattdessen zwei Zahlen:
  **geplant** und **tatsächlich**. Die tatsächliche Zahl lässt sich direkt auf
  der Veranstaltungsseite nachtragen, wenn es vorbei ist.

Welche Arten gleich ohne Liste anfangen, steht unter *Einstellungen →
Veranstaltungen → Arten ohne Gästeliste* (Vorgabe: Ausbildung und Übung).
Beim Anlegen folgt der Schalter der gewählten Art; danach entscheidet, wer die
Veranstaltung anlegt. Umstellen geht jederzeit – eine schon geführte
Gästeliste bleibt dabei erhalten und taucht wieder auf, sobald der Schalter
wieder an ist.

### Gästeliste auf Papier und als Datei

Aus der Veranstaltung heraus:

* **Einlassliste drucken** – alle, die zugesagt haben (Zusagen und
  Vertretungen), mit Kästchen zum Abhaken, Begleiterzahl und der Summe der
  erwarteten Personen unten. Das ist die Liste für den Eingang.
* **Ganze Liste drucken** – alle Eingeladenen mit ihrer Rückmeldung. Über die
  Leiste oben rechts lässt sich auf *nur Zusagen* oder *nur Offene*
  umschalten (praktisch zum Hinterhertelefonieren) und die Nachrichten der
  Gäste aus- und wieder einblenden.
* **Einladungsliste (Serienbrief)** – je eingeladener Person eine Karte mit
  **Anschrift** für den Umschlag, **Briefanrede**, dem Einladungslink als Text
  und einem **QR-Code**, der auf genau diese Einladung zeigt. Umschaltbar auf
  *nur mit Anschrift* (für den Versand) oder *nur Offene* (für die
  Erinnerung), auf Wunsch ohne QR-Codes. Wer im Kontaktmodul keine Anschrift
  hat, steht gestrichelt umrandet dabei – dann weiß man, wen man anders
  einladen muss.
* **CSV** – dieselbe Liste für Excel und den Serienbrief: Anrede, Titel,
  Vorname, Nachname, Organisation, Position, Straße, PLZ, Ort, Land,
  **Briefanrede**, E-Mail, Rufnummern, Rückmeldung sowie **Einladungscode und
  fertiger Einladungslink**. Weil darin Rufnummern und Codes stehen, gibt es
  die Datei nur für die Leitung.

Für einen Serienbrief in Word oder LibreOffice ist die CSV die Datenquelle:
Anschriftfeld aus Straße/PLZ/Ort, Briefanrede als Feld, und den Einladungslink
in den Brieftext. Wer den QR-Code im Brief haben möchte, nimmt die gedruckte
Einladungsliste als Vorlage.

### Einladung im Netz

Damit die Einladung eine Adresse bekommt, braucht es einen **Connector** mit
der Verwendung *Veranstaltungen* (siehe `connector/README.md`). Er wird beim
Bearbeiten der Veranstaltung ausgewählt. Die Adresse ist so kurz wie möglich:

```
https://i.example.de/AB23CD
```

Auf der Seite stehen Titel, Zeitpunkt, Ort und der Hinweistext, den man im
Formular mitgibt. Zur Auswahl stehen *Ich nehme teil*, *Ich kann leider nicht
teilnehmen* und – wenn erlaubt – *Es kommt jemand für mich*, dazu „Mit wie
vielen Begleitern kommen Sie?" und ein Feld für eine Nachricht.

Was der Connector dabei erfährt und was nicht:

| Liegt dort | Liegt dort nicht |
|---|---|
| Titel, Zeitpunkt, Ort, Hinweistext | Namen und Anschriften der Eingeladenen |
| Prüfsummen der Einladungscodes | die Codes selbst |
| verschlüsselte Rückmeldungen | Zusagen, Absagen, Kommentare im Klartext |

OV-Multitool meldet Veranstaltungen und Codes von allein an und holt die
Rückmeldungen ab – im Takt von *Rückmeldungen abholen alle (Minuten)*, Vorgabe
zehn. Dafür muss der automatische Abruf (`cron.php`) laufen; im Add-on tut er
das ohnehin. Auf der Veranstaltungsseite gibt es beide Knöpfe auch von Hand.

Steht bei der Veranstaltung eine **Rückmeldefrist**, nimmt die Seite danach
nichts mehr an. Ist die Veranstaltung abgesagt, sagt sie das.

### Wenn ein Code in falsche Hände gerät

*Code neu* in der Gästeliste erzeugt einen neuen; der alte Link führt beim
nächsten Abgleich ins Leere. Wer ganz von der Liste genommen wird, verliert
seinen Zugang ebenso.

### Verpflegung kalkulieren

Für Ausbildungen, Übungen und Einsätze (einstellbar, welche Arten) lässt sich
je Veranstaltung die Verpflegung rechnen. Grundlage ist ein **Tagessatz** je
Person (*Veranstaltungen → Tagessätze*), gültig von–bis wie ein Tarif und
prozentual auf Frühstück, Mittag- und Abendessen verteilt, üblich 20/40/40.
Ändert sich der Satz im Jahr, bekommt der alte ein Ende und der neue beginnt
am Tag danach; eine Veranstaltung rechnet mit dem Satz, der an ihrem Beginn
gilt. Das Formular zeigt die Dauer und je Kalendertag drei Häkchen für
Frühstück, Mittag- und Abendessen, vorbelegt aus dem Zeitraum: Frühstück,
wenn die Veranstaltung um 8 Uhr schon läuft, Mittag um 12:30, Abend um 18:30.
Vom 1.10. 17:00 bis 3.10. 11:00 sind das ein Abendessen, ein voller Tag und
ein Frühstück – jedes Häkchen lässt sich ändern. Gerechnet wird für die Teilnehmer, also die
Zusagen der Gästeliste oder die geplante Zahl; eine eigene Personenzahl geht
auch. Die Veranstaltungsseite zeigt die Rechnung je Mahlzeit, die Summe und
daneben, was für die Veranstaltung schon gebucht ist. Im Budget zählt die
Kalkulation als **geplante Ausgabe**, bis sie gebucht ist; „Als geplante
Buchung vormerken" legt sie als Buchung mit Stand *geplant* an, die nach der
Rechnung auf *bezahlt* gesetzt wird.

## Zeitzone

Das Add-on stellt die Zeitzone selbst ein: zuerst die Option `zeitzone` der
Add-on-Konfiguration, sonst die von Home Assistant übergebene `TZ`, sonst
`Europe/Berlin`. Sie gilt für die Anwendung **und** die Datenbank – sonst
stehen in den Protokollen Zeiten aus einer anderen Zeitzone.

Bei einer externen Datenbank stellt die Anwendung die Zeitzone zusätzlich je
Verbindung passend ein. Ob beides zusammenpasst, zeigt die Startseite der
Verwaltung: Gehen Anwendung und Datenbank auseinander, steht dort ein Hinweis.

## Kennzahlen an Home Assistant melden

Unter *Verwaltung → Home Assistant* und *Einstellungen → Home Assistant*
lässt sich der Export einschalten. Die Anwendung meldet die Werte über MQTT;
Home Assistant legt die Entitäten selbst an (Auto-Discovery) und fasst sie zum
Gerät „OV-Multitool" zusammen.

* **Broker:** Ist das Mosquitto-Add-on installiert, holt sich das Add-on die
  Zugangsdaten beim Start automatisch. Sonst Host, Port, Benutzer und Passwort
  in den Einstellungen eintragen.
* **Gemeldet werden:**
  * Budget: Jahresbudget (Zuweisung), verfügbar (mit Einnahmen), Töpfe
    gesamt, verplant (offene Wünsche), frei, Auslastung; Ausgaben, Einnahmen,
    in Rechnung gestellte Einnahmen, offene Rechnungen, geplante Ausgaben
    und Saldo im Haushaltsjahr
  * Wünsche: offen, zur Bestellung freigegeben, Summe der offenen
  * Aufgaben: offen, überfällig; Themen im Themenspeicher
  * Besprechungen: die nächste als Zeitstempel mit Titel und Ort
  * Veranstaltungen: die nächste als Zeitstempel mit Titel, Art und Ort;
    dazu Zusagen, offene Rückmeldungen und erwartete Personen; Anzahl in den
    nächsten 30 Tagen
  * Fahrzeuge: gesamt, einsatzbereit, im Ausfall, offene Aufträge, fällige
    Fristen (HU, SP, UVV)
  * Funkgeräte: gesamt, mit Karte, ohne Zuordnung, Prüfung fällig, länger
    nicht gemeldet
  * SIM- und TETRA-Karten: gesamt, TETRA, ohne Zuordnung, Vertrag läuft aus,
    Kosten je Monat
  * Verbrauch: Strom, Gas und Wasser im Jahr, Kosten, Zähler ohne aktuellen
    Stand
  * Diagnose: gekoppelte Connectoren, letzte Abrufe (Connector, Stein.APP,
    Divera), letzte Sicherung und Anzahl der Sicherungen
* **Je Fahrzeug** (abschaltbar) ein eigenes Gerät mit Status, Funkstatus, HU,
  SP, UVV, Kilometerstand und offenen Aufträgen; mit der Option
  *Fahrzeugstandort* zusätzlich ein `device_tracker` mit der Position aus
  Divera oder aus den QR-Standortmeldungen.
* **Takt:** Standardmäßig alle 5 Minuten; die Anmeldung der Entitäten wird
  einmal täglich wiederholt, damit neue Fahrzeuge ankommen. Der Knopf
  *Jetzt senden* macht es sofort.
* **Aufräumen:** *Entitäten entfernen* nimmt alle Anmeldungen zurück.

**Was bewusst nicht gemeldet wird:** alles, was jemandem nützt, der den
Broker mitliest. Keine Namen, keine Gästelisten, keine Rufnummern, keine
Funkkennungen (ISSI, OPTA), keine Karten- oder Seriennummern, keine
Zugangsdaten, keine QR-Zugänge – und nicht, welches Funkgerät oder welcher
Koffer gerade am Lagerort ist oder fehlt. Von den Funkgeräten gehen nur
Summen hinaus. Einzig der Fahrzeugstandort ist ein Standort, deshalb ist er
eine eigene Einstellung und in der Vorgabe aus.

## Benachrichtigungen aufs Handy

Die Anwendung ruft in Home Assistant den Dienst `notify.<Ziel>` auf; auf dem
Handy erscheint die Meldung über die Companion-App. Einschalten unter
*Einstellungen → Home Assistant*, prüfen unter *Verwaltung → Home Assistant*.

1. **Ziel hinterlegen:** Jede Person trägt im eigenen Profil ihr Ziel ein, etwa
   `mobile_app_pixel_8` (also `notify.` weglassen). Die Auswahl kommt aus Home
   Assistant. Für andere setzt es die Benutzerverwaltung.
2. **Adresse für den Link** (optional): Am besten der Panel-Link der
   Anwendung, also `https://DEINE-HA-ADRESSE/hassio/ingress/<add-on-slug>`
   (den Slug zeigt die Adresszeile, z. B. `6c0cd8ac_ov_budget`). Für die
   Companion-App geht auch
   `homeassistant://navigate/hassio/ingress/<add-on-slug>`.

   Die Adresse aus der Browserzeile (`…/api/hassio_ingress/<Token>/…`) taugt
   **nicht**: Das Token wechselt, der Link wäre bald tot. Beim Panel-Link
   führt die Meldung auf die Startseite, weil Home Assistant angehängte
   Parameter nicht in den Ingress-Rahmen weiterreicht. Wer die Anwendung
   zusätzlich unter einer eigenen Adresse erreicht, kann sie hier eintragen –
   dann führt der Link direkt zum Vorgang, wahlweise mit dem Platzhalter
   `{pfad}` an der passenden Stelle.
3. **Testnachricht** verschicken – der Knopf steht auf der Verwaltungsseite.

Gemeldet wird:

| Ereignis | An wen |
|---|---|
| Neue Aufgabe | Zuständige Person, Fachgruppe oder Funktion |
| Aufgabe heute fällig oder überfällig | Zuständige, einmal täglich |
| Neuer Wunsch zur Freigabe | Alle, die ihn in dieser Höhe freigeben dürfen |
| Wunsch freigegeben | Person, die ihn eingetragen hat |
| Neue Instandsetzungsmeldung | Leitung |
| Fahrzeug steht still | Leitung |
| Fristen HU, SP, UVV | Leitung, einmal täglich |
| Besprechung am nächsten Tag | Alle mit eingeschalteten Meldungen, einmal täglich |
| Connector-Prüfung schlägt an | Leitung |
| Connector nicht erreichbar | Leitung, nach der Karenz (Vorgabe 15 Minuten) einmal je Ausfall – und einmal, wenn er wieder antwortet |
| Zähler bitte ablesen | Leitung, einmal täglich; je Zähler höchstens wöchentlich |
| Auffälliger Verbrauch | Leitung, einmal täglich; je Zähler höchstens alle drei Tage |
| Abrechnung wartet auf Geld | Leitung, wenn eine Einnahme 30, 60 oder 90 Tage wartet – je Stufe einmal |
| Stichtag des Jahresbudgets | Leitung, 30, 14, 7 und 1 Tag vorher und am Tag selbst |

Jedes Ereignis lässt sich einzeln abschalten, jede Person kann Meldungen für
sich ganz ausschalten. Die tägliche Runde läuft ab der eingestellten Stunde
(Vorgabe 7 Uhr). Gesendet wird aus dem Minutenlauf heraus: Ereignisse landen
zuerst in einer Warteschlange, deshalb wartet niemand im Browser auf Home
Assistant. Fehlgeschlagene Versuche stehen mit Grund auf der
Verwaltungsseite und werden bis zu dreimal wiederholt.

## Benachrichtigungen im Browser (Web Push)

Zweiter Weg neben der Companion-App: Der Browser bekommt die Meldung direkt,
auch wenn die Seite geschlossen ist. Wer beides eingerichtet hat, bekommt die
Meldung über beide Wege.

1. *Einstellungen → Home Assistant*: **Benachrichtigungen im Browser (Web Push)**
   einschalten, gern mit einer Kontaktadresse für die Push-Dienste.
2. *Verwaltung → Home Assistant*: **Schlüssel erzeugen** – das passiert einmalig
   (VAPID). Tauscht man sie später aus, müssen sich alle Browser neu anmelden.
3. Jede Person meldet ihre Geräte selbst an: *Mein Profil* →
   **In diesem Browser anmelden**, dann die Nachfrage des Browsers bestätigen.
4. Prüfen mit **Testnachricht schicken** – der Knopf steht im Profil direkt
   neben der Anmeldung, sobald dieser Browser angemeldet ist. Für die
   Companion-App gibt es im Profil einen eigenen Testknopf.

Voraussetzungen: eine Verbindung über HTTPS – über den Ingress von Home
Assistant also von selbst, über den direkten Port 8099 nicht. Auf dem iPhone
muss die Seite über *Teilen → Zum Home-Bildschirm* hinzugefügt sein (ab
iOS 16.4). Der Container muss die Push-Dienste von Google und Mozilla
erreichen können.

Gemeldet werden dieselben Ereignisse wie oben. Ein Tipp auf die Meldung öffnet
den Vorgang, sofern die Adresse der Anwendung hinterlegt ist. Läuft ein Abo ab
(Browser neu aufgesetzt, App gelöscht), wird es beim nächsten Versand
automatisch entfernt.

## Fahrzeugdaten aus Divera

Unter *Einstellungen → Divera 24/7* lässt sich *Fahrzeugdaten aus Divera
abgleichen* einschalten (die Divera-Anbindung selbst muss dafür aktiv sein).
Dann holt die Anwendung:

* **Funkstatus** (FMS) mit Zeitpunkt und Freitext – jeder Wechsel steht als
  Fahrtenbuch im Journal der Fahrzeugakte (Rubrik *Funkstatus*), etwa
  „Status 3 – Einsatz übernommen um 14:32 Uhr". Abruf alle 2 Minuten,
  einstellbar. Wechsel zwischen zwei Abrufen sieht die Anwendung nicht.
* **Letzte Position** mit Link auf die Karte (OpenStreetMap), abschaltbar.
* **Besatzung**, die in Divera dem Fahrzeug zugeordnet ist, abschaltbar.
* **Standort von Hand setzen:** In der Fahrzeugakte gibt es unter *Standort*
  den Knopf **Jetzt Position setzen**. Er übernimmt den Standort des Geräts –
  gedacht fürs Handy, wenn ein Fahrzeug irgendwo abgestellt wurde. Der Browser
  fragt dabei um Erlaubnis; nötig ist eine Verbindung über HTTPS (über den
  Ingress gegeben). Der Standort landet mit Genauigkeit im Journal.
  Wer den Auftrag hat, Meldungen zu schreiben, darf das.

  Achtung bei Fahrzeugen, die in Divera hängen: Der nächste Abgleich
  überschreibt die Position wieder. Im Journal bleibt der Eintrag stehen. Mit
  *Standort erneuern alle (Minuten)* lässt sich festlegen, wie lange die von
  Hand gesetzte Position stehen bleibt.
* **Standort melden per QR-Code:** In jedem Fahrzeug kann ein QR-Code hängen.
  Wer ihn scannt, meldet den Standort – ohne Anmeldung und ohne Einblick in die
  Akte. Dafür läuft der mitgelieferte *Connector* auf einem öffentlich
  erreichbaren Webserver; die Meldung wird auf dem Handy verschlüsselt, der
  Connector kann sie nicht lesen. Einrichtung unter *Verwaltung → Standort&shy;meldung
  per QR-Code*, Code je Fahrzeug in der Fahrzeugakte (erzeugen, drucken,
  zurückziehen). Auf dem Connector liegen dabei **nur Prüfsummen** der Zugänge –
  Bezeichnung und Kennzeichen stehen im Anker der Adresse (hinter dem `#`) und
  erreichen den Server nie. Auf der Karte erscheint jede Meldung sofort; ins Journal kommt
  nur eine **Parkposition**, wenn das Fahrzeug länger als die eingestellte Zeit
  im selben Umkreis steht.
* **Karte in der Fahrzeugakte:** Zur letzten Position zeigt die Akte eine
  Karte von OpenStreetMap. Der Browser lädt sie direkt von dort; wer das nicht
  möchte, schaltet sie unter *Einstellungen → Fahrzeuge* ab
  (*Karte in der Fahrzeugakte zeigen*) – der Verweis auf die große Karte bleibt.
* **Wie oft der Standort erneuert wird:** *Einstellungen → Divera 24/7* →
  *Standort erneuern alle (Minuten)*. Vorgabe 0 heißt: bei jedem Abruf des
  Funkstatus (alle 2 Minuten). Ein größerer Wert lässt die letzte Position
  stehen, bis die Zeit um ist.
* **Standort im Journal:** Bei jedem Funkstatuswechsel stehen die Koordinaten
  im Journaleintrag. Reine Positionsmeldungen landen nur dort, wenn unter
  *Einstellungen → Divera 24/7* eine Strecke eingetragen ist
  (*Standortwechsel ins Journal ab (Meter)*, Vorgabe 0 = aus) – sonst gäbe es
  alle paar Minuten einen Eintrag.
* **OPTA, RIC**, dazu Kennzeichen und ISSI, falls bei uns noch nichts steht –
  aus der v3-Schnittstelle, die Divera noch als Beta führt. Sie braucht einen
  **persönlichen Accesskey** mit Verwaltungsrechten
  (*Persönlicher Accesskey für /api/v3*); Funkstatus, Position und Besatzung
  kommen mit dem normalen Accesskey.

Divera-Fahrzeuge werden über ISSI, Kennzeichen oder Funkrufname erkannt – nur
wenn genau eines passt. Was offen bleibt, steht unter *Verwaltung →
Divera-Fahrzeuge* zum Zuordnen.

## Stein.APP einrichten

1. In der Stein.APP einen API-Schlüssel erzeugen und die **BU-ID** des
   Ortsverbands notieren – dieselben Angaben wie für die
   Home-Assistant-Integration *THW Stein*.
2. In der Anwendung unter *Verwaltung → Einstellungen → Stein.APP* Schlüssel
   und BU-ID eintragen und den Abgleich einschalten.
3. Unter *Verwaltung → Stein.APP* die Fahrzeuge zuordnen: entweder einer
   bestehenden Fahrzeugakte oder als neue Akte.

**Automatisch anlegen** (Einstellung *Unbekannte Fahrzeuge selbst anlegen*)
gilt nur für Einträge mit erkennbarem **Kennzeichen** – entweder in einem
eigenen Feld der Stein.APP oder im Text (z. B. „GKW 1 (THW-84321)" oder
„HB-XY 456"). Anhänger, Aggregate und Geräte ohne Kennzeichen landen
stattdessen in der Liste zum Zuordnen. Typbezeichnungen wie „MLW-IV 2" gelten
nicht als Kennzeichen.

**Grenzen der Schnittstelle** (laut <https://stein.app/api/api/doc/intro>):

* höchstens **20 Anfragen je Minute und IP-Adresse**; wer darüber liegt, wird
  **eine Stunde gesperrt**
* der Zugriff ist auf **IP-Adressen aus Deutschland** beschränkt, von außerhalb
  antwortet die Stein.APP mit 404
* regelmäßiges Abfragen im Minutentakt ist unerwünscht, empfohlen werden
  Webhooks

Der Abgleich macht deshalb je Durchgang genau **einen** Aufruf (die Liste aller
Fahrzeuge der BU-ID) und hält den eingestellten Abstand ein (Vorgabe 10
Minuten). Bei HTTP 429 pausiert er eine Stunde. Im Add-on ruft der Container
`cron.php` jede Minute auf; ob wirklich abgerufen wird, entscheidet das
Intervall. Außerhalb des Add-ons: `cron.php` per Cron aufrufen (Token unter
*Einstellungen → Stein.APP*) oder den Abgleich beim Öffnen des Fahrzeugmoduls
laufen lassen.

**Webhook statt Abfragen:** Die Stein.APP kann Änderungen selbst melden. Dazu in
der Stein.APP bei den Einstellungen des Ortsverbands die Adresse dieser
Anwendung mit dem Pfad `/webhook.php` eintragen und das dort angezeigte
**Webhook-Secret** unter *Einstellungen → Stein.APP* hinterlegen. Die Anwendung
prüft den Header `X-Secret` und gleicht dann ab, höchstens alle 30 Sekunden.
Die Adresse muss von außen erreichbar sein – über Ingress allein ist sie das
nicht.

**Kennzeichen:** Das Feld *THW-Kennzeichen* der Oberfläche heißt in der
Schnittstelle `name` (`label` ist „Fahrzeug / Bez.", `radioName` der
Funkrufname). Die Anwendung nimmt den Inhalt von `name` unverändert, wenn dort
nur ein Kennzeichen steht – auch „THW 99020" mit Leerzeichen –, und sucht sonst
in Bezeichnung, Funkrufname und Bemerkung danach. Bei allem, was kein Fahrzeug
ist (Fachgruppen, Anbaugeräte, Zelte), ist `name` leer; solche Einträge werden
daher nicht automatisch angelegt.

Ein von Hand eingetragenes Kennzeichen bleibt stehen. Kam es dagegen aus der
Stein.APP und ändert sich dort, zieht es nach – der Wechsel steht in der
Fahrzeugakte. Welches Feld bei euch was enthält, zeigt in der Akte der
aufklappbare Punkt *Rohdaten aus der Stein.APP* (nur für die Administration).

**Was der Abgleich sonst führt:** Status, Bemerkung, HU, SP, Einsatzvorbehalt,
Funkrufname, **ISSI**, Kategorie sowie die Löschung eines Fahrzeugs in der
Stein.APP. Funkrufname und ISSI landen im Fahrzeug, solange dort nichts Eigenes
steht; ändern sie sich in der Stein.APP, ziehen sie nach. Der volle Stand steht in der Fahrzeugakte unter *Stand in der
Stein.APP*.

Mit **Verbindung testen** in *Verwaltung → Stein.APP* lässt sich der Schlüssel
prüfen, ohne die Fahrzeuge abzurufen.

**Wenn etwas nicht stimmt:** Unter *Einstellungen → Stein.APP* lässt sich
*Antworten der Stein.APP mitschneiden* einschalten. Dann wird jede Antwort –
auch eine Fehlerantwort – als Datei abgelegt und kann unter *Verwaltung →
Stein.APP* heruntergeladen werden. Der API-Schlüssel steht nicht darin, und
Felder, deren Name nach einem Geheimnis klingt (etwa `webhookSecret`), sind
durch `***` ersetzt. Aufbewahrt werden die letzten 20 Dateien; sie lassen sich
dort auch alle löschen. Der Mitschnitt ist für den Dauerbetrieb nicht gedacht –
die Dateien enthalten alle Fahrzeugdaten im Klartext.

## Zweiter Faktor (Authenticator-App)

Zusätzlich zum Passwort fragt die Anmeldung einen sechsstelligen Code aus
einer Authenticator-App ab (TOTP nach RFC 6238 – Aegis, 2FAS, Google oder
Microsoft Authenticator, Bitwarden, 1Password …). Ein gestohlenes Passwort
allein reicht dann nicht mehr.

* **Einrichten** im eigenen Profil unter *Zweiter Faktor*: QR-Code mit der
  App scannen (oder den Schlüssel abtippen), den ersten Code eintragen. Dann
  erscheinen einmalig **acht Backup-Codes** – ausdrucken oder sicher
  ablegen. Jeder gilt einmal und ersetzt die App, wenn das Handy fehlt.
* **Anmelden:** erst Benutzername und Passwort, dann der Code. Fehlversuche
  zählen wie falsche Passwörter und führen zur selben Sperre. Ein Code gilt
  nur einmal; eine Uhr, die bis 30 Sekunden abweicht, geht noch.
* **Pflicht** unter *Verwaltung → Einstellungen → Sicherheit → Zweiter
  Faktor*: freiwillig, Pflicht für die Administration, für Administration und
  Leitung (Vorgabe) oder für alle. Wer pflichtig ist und ihn noch nicht hat,
  kommt nach der Anmeldung nur ins Profil, bis er eingerichtet ist – wie beim
  Startpasswort. Pflichtige können ihn nicht selbst abschalten.
* **Handy verloren:** Backup-Code verwenden und im Profil neue Codes erzeugen
  oder die App neu einrichten. Sind Handy und Codes weg, setzt die
  Administration unter *Verwaltung → Benutzer* den zweiten Faktor zurück –
  am besten nach Rückruf, danach gilt vorübergehend nur das Passwort. Die
  Benutzerliste zeigt, wer ihn hat und wem er noch fehlt.
* Das Geheimnis liegt in der Datenbank des Add-ons und wandert mit der
  Sicherung; Backup-Codes sind nur als Hash gespeichert.

## Stell- und Lagerplätze

Unter *Verwaltung → Stell- und Lagerplätze* beschreibt der Ortsverband, wo
etwas steht oder liegt – als Baum. Ganz oben stehen **Gebäude**, **Hallen**
und **Höfe**; darunter hängen **Stockwerke** und **Räume** (in Gebäuden),
**Fahrzeugstellplätze** (in Hallen), **Stellplätze auf dem Hof**,
**Schränke** (in Räumen) und **Regale** (in Schränken). Die Anwendung
achtet darauf, dass die Art zum übergeordneten Platz passt.

* **Serie anlegen:** Beim Anlegen „Wie viele auf einmal" und „Erste Nummer"
  setzen – aus „Raum", 16, 1 werden „Raum 1" bis „Raum 16", ein
  Kurzzeichen „R" wird zu R1 … R16. So ist ein Haus mit drei Stockwerken
  und 16 Räumen je Stockwerk in vier Schritten erfasst.
* **Pflege:** Plätze lassen sich innerhalb ihrer Ebene verschieben,
  umhängen (nie unter einen eigenen Unterplatz), stilllegen (bleiben im
  Baum, sind aber nicht mehr auswählbar) und löschen, sobald keine
  Unterplätze mehr daran hängen. Leitung und Administration dürfen pflegen.
* **Lage und Bilder:** Jeder Platz kann **GPS-Koordinaten** tragen – von
  Hand als „Breite, Länge" eingetragen oder am Handy vor Ort mit „Jetzt
  Position setzen" vom Gerät übernommen (der Browser fragt um Erlaubnis).
  Mit Position zeigt die Platzseite eine Karte von OpenStreetMap und einen
  Kartenlink, im Baum steht ein 📍. Dazu lassen sich **Bilder** hochladen
  (vom Regal, der Tür, dem Stellplatz), eines davon als Titelbild, das im
  Baum als kleines Vorschaubild erscheint. Bilder werden verkleinert und
  verlieren den Aufnahmeort; sie liegen in der Dateiablage unter
  `standorte/` und wandern mit der Sicherung.
* **Fahrzeuge:** In der Fahrzeugakte lässt sich unter „Stellplatz
  (Standard)" einer der Plätze wählen – wo das Fahrzeug normalerweise steht.
  Die Akte zeigt den Pfad („Halle 1 › Stellplatz 3"), die Fahrzeugliste den
  Platznamen, und die Platzseite in der Verwaltung alle Fahrzeuge, die dort
  stehen. Das freie Feld „Standort" bleibt für Vorübergehendes wie „in der
  Werkstatt".
* **Zähler** haben zwei Plätze: **„Zuständig für"** ist der Bereich, dessen
  Verbrauch der Zähler misst – das ganze Haus, ein Stockwerk, ein Raum; er
  gilt auch für alles darunter. **„Zählerplatz (eingebaut in)"** ist der Ort,
  an dem der Zähler hängt, etwa der Hausanschlussraum. Zählerseite und
  Übersicht zeigen beides („für Haupthaus · in Hausanschlussraum"). Die
  Platzseite listet die Zähler, die für sie zuständig sind – auch geerbt
  über einen übergeordneten Platz, etwa der Hauszähler an jedem Stockwerk –
  und die hier eingebauten.
* **Standortübersicht:** Der Menüpunkt „Standorte" zeigt allen den Baum
  zum Durchklicken – erst Gebäude, Hallen und Höfe, dann Stockwerke, Räume,
  Stellplätze, Schränke, Regale. Jede Karte nennt, was darunter steckt
  („3 Fahrzeuge · 2 Zähler"), die Seite eines Platzes seine Fahrzeuge
  als Kacheln wie in der Fahrzeugliste – mit Titelbild, Stempel („In
  Wartung", „Nicht einsatzbereit"), Status und Fristen –, dazu Zähler,
  Funkgruppen und Funkgeräte mit Sprung in die jeweilige Akte.
  Gezeigt wird nur, was die Person in den Modulen auch sehen darf. Der
  Name des Menüpunkts ist in den Einstellungen änderbar.
* **Funkgeräte und Gruppen:** Eine Gruppe (Koffer, Ladeschale, Satz)
  bekommt ihren Platz – etwa „Funkraum › Regal 2" – und jedes Gerät kann
  einen eigenen haben, etwa die Feststation im Zugtruppraum. Ein Gerät
  ohne eigenen Platz gilt am Platz seiner Gruppe. Geräteseite, Gruppenseite
  und Liste zeigen den Pfad, die Platzseite ihre Gruppen und Geräte. Die
  freien Felder „Standort" und „Lagerort" bleiben für Hinweise wie
  „oberstes Fach". Material folgt.

## Rollen

| Rolle | Darf |
|---|---|
| Mitglied | Wünsche anlegen und die eigenen bearbeiten, abstimmen, kommentieren, Aufgaben im eigenen Zuständigkeitsbereich bearbeiten; je nach Einstellung Schäden melden und offene Aufträge bearbeiten, Standort und Journal der Fahrzeuge ergänzen |
| Leitung | zusätzlich: alle Wünsche bearbeiten, Status und Priorität setzen, Budgettöpfe pflegen, alle Aufgaben verwalten, Veranstaltungen anlegen und die Gästeliste führen |
| Administration | zusätzlich: Benutzerverwaltung, Auswahllisten, Einstellungen, Divera-Anbindung, Protokoll |

## Prüfungen für Entwickler

Im Ordner `tests/` liegen rund 1.900 Prüfungen ohne Datenbank: jede
`t_*.php` prüft ein Modul mit Attrappen für die Datenbank und schreibt am
Ende „N bestanden, M fehlgeschlagen". `php tests/alle.php` führt alle aus,
`php tests/alle.php verbrauch` nur die passenden. Braucht PHP 8.2 oder neuer
mit mbstring, openssl, zip und gd; auf Rechnern ohne mbstring hilft
`OVB_PHP_ARGS="-d auto_prepend_file=tests/mb_shim.php"`. Bei jedem Push
laufen die Prüfungen auf GitHub (Actions → Prüfungen), dazu ein
Syntaxlauf über alle PHP-Dateien der Anwendung und des Connectors.

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
