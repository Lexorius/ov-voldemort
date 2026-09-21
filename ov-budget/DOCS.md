# OV-Budget

Verwaltung für einen THW-Ortsverband: Wünsche und Budget, Aufgaben, Kontakte,
Besprechungen und Fahrzeugakten – mit Anbindung an Stein.APP und Divera 24/7.
Das Add-on bringt **alles mit** – Datenbank, Webserver und Anwendung stecken im
Container. Weitere Add-ons sind nicht nötig.

Unten auf jeder Seite steht die laufende Version; ein Klick darauf zeigt, was
sich zuletzt geändert hat.

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
* **Fahrzeuge** – Fahrzeugstamm mit Funkrufname, **ISSI**, Kennzeichen und
  Fristen (HU, SP, UVV) und je Fahrzeug eine
  **Fahrzeugakte**. Ihr **Journal** wird nur ergänzt: Einträge lassen sich weder
  ändern noch löschen, jeder trägt die Prüfsumme des vorherigen, und
  „Journal auf Veränderungen prüfen" rechnet die Kette nach. Schadensmeldungen
  und **Instandsetzungsaufträge** mit fortlaufender Nummer, Werkstatt, Kosten
  und Bearbeitungsstand – jeder Schritt landet in der Akte.
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
Gerät „OV-Budget" zusammen.

* **Broker:** Ist das Mosquitto-Add-on installiert, holt sich das Add-on die
  Zugangsdaten beim Start automatisch. Sonst Host, Port, Benutzer und Passwort
  in den Einstellungen eintragen.
* **Gemeldet werden:** Budget (gesamt, verplant, frei, Auslastung), offene
  Wünsche und deren Summe, zur Bestellung freigegebene Wünsche, offene und
  überfällige Aufgaben, Themen im Themenspeicher, die nächste Besprechung als
  Zeitstempel, Fahrzeuge (gesamt, einsatzbereit, im Ausfall), offene
  Instandsetzungsaufträge und fällige Fristen (HU, SP, UVV). Dazu als Diagnose
  die Zeitpunkte der letzten Abrufe.
* **Je Fahrzeug** (abschaltbar) ein eigenes Gerät mit Status, Funkstatus, HU,
  SP, Kilometerstand und offenen Aufträgen; mit der Option *Fahrzeugstandort*
  zusätzlich ein `device_tracker` mit der Position aus Divera.
* **Takt:** Standardmäßig alle 5 Minuten; die Anmeldung der Entitäten wird
  einmal täglich wiederholt, damit neue Fahrzeuge ankommen. Der Knopf
  *Jetzt senden* macht es sofort.
* **Aufräumen:** *Entitäten entfernen* nimmt alle Anmeldungen zurück.

Personenbezogenes wird nicht gemeldet – nur Zahlen, Zeitpunkte und
Fahrzeugdaten.

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
