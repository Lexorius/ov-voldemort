# Änderungsverlauf

## 1.28.1

### Anmerkungen im Ausdruck

- Tagesordnung und Protokoll drucken die Anmerkungen zu den Talking Points
  jetzt klein unter dem jeweiligen Punkt mit.
- Einstellbar unter *Einstellungen → Besprechungen*: nur im Protokoll
  (Vorgabe), in Tagesordnung und Protokoll oder nie – und ob Name und Tag der
  Anmerkung dabeistehen.
- Oben in der Druckansicht lässt es sich für den einzelnen Ausdruck umschalten
  („mit Anmerkungen" / „ohne Anmerkungen"); der Schalter selbst wird nicht
  mitgedruckt.

## 1.28.0

### Talking Points: Anmerkungen und Diskussion

- Jeder Talking Point hat jetzt eine eigene Seite mit Beschreibung, Stand und
  einer **Diskussion**. Erreichbar über den Titel im Themenspeicher, im
  Archiv und auf der Besprechungsseite; dort steht auch die Zahl der
  Anmerkungen.
- Anmerkungen sind möglich, **solange der Punkt nicht abgeschlossen ist**
  (besprochen, beschlossen, abgelehnt oder zurückgezogen). Danach bleibt die
  Diskussion lesbar. Vertagte Punkte bleiben offen.
- Die eigene Anmerkung lässt sich wieder entfernen, die Leitung kann jede
  entfernen – beides nur, solange der Punkt offen ist.
- Neues Benachrichtigungsereignis: Wer den Punkt eingebracht oder schon
  mitdiskutiert hat, bekommt neue Anmerkungen gemeldet (über die
  Companion-App oder Web Push). Ein Tipp führt direkt zur Anmerkung.

## 1.27.9

### HTTP 401 beim Öffnen von Dateien am Handy

- Bilder, Dokumente und die Druckansichten öffneten sich in einem neuen Tab.
  Hinter dem Ingress von Home Assistant fehlt dort die Anmeldung – in der
  Companion-App kam deshalb „401". Diese Seiten öffnen jetzt im selben Tab;
  mit „Zurück" geht es weiter wie vorher.
- Links auf fremde Seiten (Produktseite eines Wunsches, Karte) öffnen weiter
  in einem neuen Tab.

## 1.27.8

### Am Handy: eine Datei auf einmal statt gar keine

- Wird auf einem Gerät mit Touch-Bedienung mehr als ein Foto ausgewählt,
  übernahmen manche Auswahldialoge gar nichts. Auf solchen Geräten fragt das
  Feld jetzt eine Datei auf einmal ab; ein Hinweis unter dem Feld sagt, dass
  weitere nach dem Hochladen hinzukommen können. Am Rechner bleibt die
  Mehrfachauswahl.

## 1.27.7

### Am Handy ließen sich keine Fotos auswählen

- Das Feld für Fotos hatte einen Filter (`accept="image/*"`). Manche
  Auswahldialoge auf dem Handy liefern damit gar keine Datei zurück: Die
  Galerie öffnet sich, man wählt Bilder aus, und im Formular bleibt alles
  leer. Der Filter ist jetzt weg – bei den Dokumenten gab es ihn nie, dort
  funktionierte die Auswahl deshalb. Welche Dateien erlaubt sind, prüft
  weiterhin die Anwendung beim Speichern.
- Die Größenprüfung im Browser leert die Auswahl nicht mehr stillschweigend.
  Stattdessen steht unter dem Feld, wie viele Dateien gewählt wurden und wie
  groß sie zusammen sind; ist etwas zu groß, wird es benannt und das
  Absenden gesperrt. Der bisherige Hinweis kam als Dialogfenster und konnte
  vom Browser unterdrückt werden.

## 1.27.6

### Dateien an Fahrzeugen: sagen, woran es hakt

- Schlägt das Hochladen fehl, weil der Ordner der Ablage fehlt oder für den
  Webserver nicht beschreibbar ist, steht das jetzt als Meldung da – mit Pfad
  und dem Benutzer, unter dem der Webserver läuft. Vorher passierte scheinbar
  nichts.
- Kommt gar keine Datei an (etwa weil sie zu groß für die Übertragung war),
  sagt die Meldung auch das.
- Beim Öffnen einer Datei wird jetzt unterschieden: Gibt es den Eintrag nicht,
  bleibt es bei „nicht gefunden". Ist der Eintrag da, aber die Datei fehlt in
  der Ablage, steht genau das da – vorher kam nur eine nackte 404.
- Neu in der Verwaltung: die Karte **Dateiablage** mit Pfad, Schreibrecht,
  Anzahl der Dateien und den Einträgen, zu denen keine Datei mehr existiert.

## 1.27.5

### Links in Benachrichtigungen: Panel-Adresse wird erkannt

- Zeigt die hinterlegte Adresse auf das Ingress-Panel (`…/hassio/ingress/…`)
  oder ist es ein App-Verweis (`homeassistant://…`), hängt die Anwendung
  keinen Pfad mehr an. Home Assistant reicht ihn ohnehin nicht weiter; der
  Link blieb dadurch nur unnötig lang.
- Neuer Platzhalter `{pfad}`: Wer die Anwendung unter einer eigenen Adresse
  erreicht, bestimmt damit, wo der Pfad eingesetzt wird.
- Der Hinweistext in den Einstellungen sagt jetzt, welche Adresse taugt – die
  aus der Browserzeile enthält ein wechselndes Token und gehört nicht dorthin.

## 1.27.4

### Klarere Meldungen beim Testversand

- Antwortet Home Assistant mit einem Fehler, steht jetzt der Grund aus der
  Antwort dabei statt nur „400: Bad Request", dazu der aufgerufene Dienst.
- Kennt Home Assistant das eingetragene Ziel nicht, sagt die Meldung das und
  zählt die vorhandenen notify-Dienste auf.
- Dienste, die mit dem Feld für den Link nichts anfangen können, bekommen die
  Nachricht automatisch ein zweites Mal ohne diesen Zusatz.
- Lässt sich eine Nachricht nicht als JSON verpacken, wird das gemeldet,
  statt eine leere Anfrage zu schicken.

## 1.27.3

### Ursache gefunden: Das Add-on sah die Container-Umgebung gar nicht

- Das Basisimage von Home Assistant startet die Anwendung über s6-overlay.
  Dienste erben dort die Umgebung des Containers **nicht** – sie liegt als
  einzelne Dateien unter `/run/s6/container_environment`. Deshalb fehlten
  `SUPERVISOR_TOKEN` (Benachrichtigungen, MQTT-Zugang vom Supervisor) und
  auch `TZ`. Das Startskript liest die Umgebung jetzt selbst ein.
- Zusätzlich liest die Anwendung das Token notfalls direkt aus dieser Ablage.
- Unter *Verwaltung → Home Assistant* steht, woher der Zugang stammt.

## 1.27.2

### „SUPERVISOR_TOKEN fehlt" beim Lesen der Benachrichtigungsziele

- Der Webserver-Prozess bekommt die Umgebungsvariablen des Containers nicht
  zuverlässig mit; dadurch fand die Seite kein Token für Home Assistant,
  obwohl das Add-on eines hat. Der Start legt es jetzt zusätzlich in einer
  Datei ab, die nur der Webserver lesen darf.
- Der ältere Name `HASSIO_TOKEN` wird ebenfalls akzeptiert.
- Unter *Verwaltung → Home Assistant* steht jetzt, ob ein Zugang vorliegt und
  woher er stammt.

## 1.27.1

### Testnachricht direkt im Profil

- Unter *Mein Profil* steht jetzt neben „Abmelden" ein Knopf
  **Testnachricht schicken** – er geht an die angemeldeten Browser dieser
  Person. Vorher ging das nur über die Verwaltung.
- Wer ein Ziel in Home Assistant hinterlegt hat, findet daneben einen eigenen
  Testknopf für die Companion-App.
- Nach dem An- oder Abmelden lädt die Seite neu, damit der Testknopf sofort
  passt. War die Anmeldung abgelaufen, sagt die Meldung das und der Eintrag
  wird entfernt.

## 1.27.0

### Benachrichtigungen im Browser (Web Push)

- Neben der Companion-App gibt es jetzt echten Web Push: Meldungen erscheinen
  auch bei geschlossener Seite, ohne dass Home Assistant auf dem Gerät nötig
  ist. Wer beide Wege eingerichtet hat, bekommt die Meldung über beide.
- **Einrichtung:** in den Einstellungen einschalten, unter *Verwaltung → Home
  Assistant* einmalig die VAPID-Schlüssel erzeugen, danach meldet sich jede
  Person im eigenen Profil mit ihren Geräten an. Dort steht auch, welche
  Geräte angemeldet sind und wann sie zuletzt erreicht wurden.
- Verschlüsselung und Anmeldung beim Push-Dienst sind nach RFC 8291 und
  RFC 8292 selbst umgesetzt – ohne Fremdbibliothek, nur mit OpenSSL.
- Abgelaufene Anmeldungen (Browser neu aufgesetzt) werden beim Versand
  automatisch entfernt.
- Nötig ist HTTPS; über den Ingress von Home Assistant ist das gegeben. Auf dem
  iPhone muss die Seite zum Home-Bildschirm hinzugefügt sein.

## 1.26.0

### Benachrichtigungen über Home Assistant

- Meldungen gehen über `notify.<Ziel>` an die Companion-App: neue Aufgabe,
  fällige und überfällige Aufgaben, neuer Wunsch zur Freigabe, freigegebener
  eigener Wunsch, neue Instandsetzungsmeldung, Fahrzeugausfall, ablaufende
  Fristen und die Besprechung am nächsten Tag.
- **Je Person ein Ziel:** im eigenen Profil oder über die Benutzerverwaltung;
  die Auswahl kommt aus Home Assistant. Wer nichts hinterlegt, bekommt nichts.
  Meldungen lassen sich je Person und je Ereignis abschalten.
- **Warteschlange statt Warten:** Ereignisse werden eingereiht, der
  Minutenlauf schickt sie los und wiederholt bis zu dreimal. Der Verlauf samt
  Fehlergrund steht unter *Verwaltung → Home Assistant*, dort gibt es auch
  eine Testnachricht.
- Mit hinterlegter Adresse führt ein Tipp auf die Meldung direkt zum Vorgang.
- Die täglichen Erinnerungen laufen ab der eingestellten Stunde (Vorgabe 7 Uhr).

## 1.25.2

### Home Assistant: Meldung im Änderungsprotokoll schlug fehl

- Nach *Jetzt senden* erschien die Meldung „audit(): Argument #2 ($entity)
  must be of type string, null given". Gesendet wurde trotzdem alles; nur der
  Protokolleintrag scheiterte. Jetzt steht dort ordentlich „mqtt".
- Neuer Test: Kein Aufruf von `audit()` oder `flash()` in der ganzen Anwendung
  übergibt `null`, wo Text erwartet wird.

## 1.25.1

### Start schlug fehl: Rufnummern-Wanderung fand ihre Funktion nicht

- Die Wanderung aus 1.24.0 lud `contacts.php`, um Rufnummern umzuschreiben.
  `phone_human()` war aber nach `util.php` umgezogen. Folge: `PHP Fatal error:
  Call to undefined function phone_human()` beim Start, der Container startete
  in einer Schleife neu. Jetzt wird die richtige Datei geladen.
- Neuer Test: Jede Funktion, die eine Wanderung aufruft, muss entweder
  eingebaut sein oder aus einer Datei stammen, die `migrate.php` selbst lädt.

## 1.25.0

### Home Assistant: Kennzahlen über MQTT

- Neue Seite *Verwaltung → Home Assistant*: Der Ortsverband erscheint in Home
  Assistant als Gerät „OV-Budget" mit Sensoren für Budget, Wünsche, Aufgaben,
  Themen, nächste Besprechung, Fahrzeuge, offene Instandsetzungsaufträge und
  fällige Fristen. Die Entitäten legt Home Assistant selbst an (MQTT
  Auto-Discovery).
- **Zugang ohne Tipparbeit:** Ist das Mosquitto-Add-on installiert, holt sich
  das Add-on Host, Benutzer und Passwort beim Start vom Supervisor. Ein eigener
  Broker lässt sich in den Einstellungen eintragen.
- **Je Fahrzeug** auf Wunsch ein eigenes Gerät mit Status, Funkstatus, HU, SP,
  Kilometerstand und offenen Aufträgen – und, wenn gewünscht, dem Standort aus
  Divera auf der Karte.
- Gemeldet wird alle 5 Minuten (einstellbar), die Anmeldung der Entitäten
  einmal täglich. *Jetzt senden* und *Entitäten entfernen* gibt es als Knopf.
- Gemeldet werden nur Zahlen, Zeitpunkte und Fahrzeugdaten – keine Namen.

## 1.24.0

### Kontakte: anrufen und mailen mit einem Tipp

- Rufnummern und E-Mail-Adressen sind in der Kontaktliste, im Verteiler und im
  Bearbeiten-Formular anklickbar (`tel:` und `mailto:`). Am Handy wählt ein
  Tipp direkt, am PC öffnet sich ein Softphone, falls eingerichtet.
- In der Handy-Ansicht der Kontaktliste standen bisher weder Nummer noch
  E-Mail – jetzt stehen Mobil, Telefon und E-Mail dort antippbar.
- **Rufnummern international:** Beim Speichern und beim Import wird aus
  „0151 123456" die Form „+49 151 123456". Erkannt werden Schreibweisen mit
  Klammern, Schrägstrich, Bindestrichen und „(0)"; steht eine zweite Nummer im
  Feld, zählt die erste. Text statt Nummer bleibt unverändert.
- Die Landesvorwahl ist einstellbar (Einstellungen → Allgemein, Vorgabe +49).
- Beim nächsten Start werden bestehende Kontakte einmalig umgeschrieben.

## 1.23.0

### Instandsetzungsaufträge: Fotos je Auftrag

- Jeder Auftrag hat jetzt eine eigene **Fotogalerie** mit Vorschaubildern,
  getrennt von den Dokumenten (Kostenvoranschlag, Werkstattbericht, Rechnung).
- **Beim Melden fotografieren:** Das Formular „Auftrag / Meldung" nimmt Fotos
  direkt entgegen; am Handy bietet es die Kamera an. Weitere Fotos lassen sich
  jederzeit am Auftrag ergänzen.
- Die Auftragsliste des Fahrzeugs zeigt, wie viele Fotos und Dokumente hängen.
- Wie bisher werden große Fotos verkleinert und verlieren dabei ihre Metadaten
  (etwa den Aufnahmeort); jedes Hinzufügen und Entfernen steht im Journal.

## 1.22.0

### Themenspeicher: Archiv und Status „Abgelehnt“

- Neuer Reiter **Archiv** im Themenspeicher: alle Themen, die auf einer
  Besprechung standen, und abgeschlossene Themen ohne Besprechung. Mit
  Besprechung und Datum, Status, Ergebnis, Verantwortlichen und den daraus
  entstandenen Aufgaben (erledigte durchgestrichen). Vertagte Themen zeigen,
  woher sie kamen und wo sie weitergingen.
- Filter nach Suchbegriff (auch im Ergebnis), Status, Fachgruppe und Zeitraum.
  Gezeigt werden die neuesten 300 Themen.
- Neuer Status **Abgelehnt** für Talking Points. Er gilt als abschließend:
  Das Thema verlässt die offenen Themen und steht im Archiv. Mit
  Status-Rückmeldung wird der Divera-Eintrag „Abgeschlossen“.

## 1.21.0

### Divera-Formulare: Bearbeitungsstand zurückmelden

- Neu je Formular: *Status der Einträge in Divera setzen* (standardmäßig aus).
  Übernommene Einträge werden **Weitergeleitet**, freigegebene Wünsche und
  Themen auf einer Tagesordnung **In Bearbeitung**, bestellte, beschaffte,
  abgelehnte Wünsche sowie besprochene Themen **Abgeschlossen**.
- Welche Wunsch-Status „In Bearbeitung“ bzw. „Abgeschlossen“ auslösen, ist in
  den Einstellungen unter Divera 24/7 einstellbar.
- Es geht nur vorwärts: Einen in Divera von Hand weitergeschalteten Status
  erkennt der Abruf und dreht ihn nicht zurück.
- Der Abgleich läuft mit dem automatischen Abruf, nach jedem manuellen Import
  und per Knopf *Status jetzt abgleichen*. Höchstens 25 Meldungen je Durchlauf.
  Beim ersten Fehler (etwa fehlenden Rechten) wird abgebrochen und protokolliert.

## 1.20.1

### Cron-Token nicht mehr im Klartext

- Unter *Verwaltung → Divera 24/7* steht der Cron-Aufruf jetzt mit verdecktem
  Token. **Token anzeigen** blendet ihn bei Bedarf ein, **Befehl kopieren**
  legt den vollständigen Aufruf in die Zwischenablage, ohne ihn zu zeigen.
- Der Aufruf nutzt das allgemeine Cron-Token, falls gesetzt, sonst das
  Divera-Token – genau wie `cron.php` selbst.
- In den Einstellungen sind beide Cron-Tokens jetzt Passwortfelder: Ein leeres
  Feld behält den gespeicherten Wert. Mit dem neuen Haken *gespeicherten Wert
  löschen* lässt sich ein Token (und jedes andere Passwortfeld) entfernen.

## 1.20.0

### Themenspeicher: Themen über ein Divera-Formular einreichen

- Ein eingebundenes Divera-Formular kann jetzt entweder **Wünsche** (wie
  bisher) oder **Themen für Besprechungen** liefern. Beim Einbinden wählt man
  „Als Wunschformular“ oder „Als Themenformular“, später lässt es sich in der
  Feldzuordnung umstellen.
- Jeder Eintrag eines Themenformulars wird ein Talking Point im
  Themenspeicher. Zuordenbar sind Thema, Beschreibung, Fachgruppe, Priorität,
  Zeitbedarf und „Eingereicht von“; die Felder werden anhand ihrer Namen
  vorgeschlagen. Nicht zugeordnete Felder und Anhänge werden in der
  Beschreibung genannt.
- Passt der Einreichername zu einem Benutzer, gilt das Thema als von dieser
  Person eingebracht. Sonst steht der Name am Thema („eingebracht von …
  · über Divera“).
- Der automatische Abruf berücksichtigt Themenformulare wie Wunschformulare.
  Im Themenspeicher holt **Aus Divera abrufen** neue Einreichungen sofort.
  Dort steht auch ein Hinweis, über welches Formular man Themen einreichen kann.
- Jede Einreichung wird nur einmal übernommen. Das Divera-Protokoll verlinkt auf
  das entstandene Thema.

## 1.19.0

### Besprechungen: Aufgaben direkt aus der Sitzung

- **Aufgaben wissen, woher sie kommen:** Jede Aufgabe kann auf ihre
  Besprechung und ihren Talking Point verweisen. In der Aufgabe steht
  „Aus Besprechung …" mit Link zurück.
- **+ Aufgabe je Punkt:** Öffnet das normale Aufgabenformular, vorausgefüllt
  mit Titel, festgehaltenem Ergebnis, Hintergrund, Fachgruppe und Priorität.
  Zuständigkeit (auch einzelne Personen) und Frist wählt man selbst. Aus einem
  Punkt dürfen mehrere Aufgaben entstehen; nach dem Speichern geht es zurück in
  die Besprechung.
- **Freie Aufgaben:** „+ Aufgabe" im neuen Abschnitt „Aufgaben aus dieser
  Besprechung" legt eine Aufgabe ohne Talking Point an, die trotzdem zur
  Besprechung gehört.
- **Talking Points als Aufgaben übernehmen:** Mehrere Punkte auf einmal, mit
  gemeinsamer Zuständigkeit (wie der Punkt, ganzer OV, Fachgruppe, Funktion
  oder Person) und optionaler Frist. Vorausgewählt sind Punkte mit Ergebnis,
  aus denen noch keine Aufgabe entstanden ist.
- **Überblick:** Die Besprechung listet alle ihre Aufgaben mit Zuständigkeit,
  Frist und Status, jeder Punkt zeigt seine eigenen. Das gedruckte Protokoll
  enthält eine Aufgabenliste.
- Bereits früher aus Talking Points angelegte Aufgaben werden beim Start ihrer
  Besprechung zugeordnet.

## 1.18.0

### Wünsch dir was: Divera-Import an die echte Schnittstelle angepasst

Geprüft gegen die Dokumentation der Divera-Formularschnittstelle
(<https://api.divera247.com/docs/api_v2_reporttype.yaml>). Der bisherige
Import passte nicht dazu:

- **Falsche Pfade:** Formulare heißen bei Divera `reporttypes`, ihre Einträge
  `reports`. Die bisherigen Vorgaben (`/v2/forms`, `/v2/forms/{form_id}/entries`)
  führten ins Leere. Wer sie nicht selbst geändert hatte, bekommt beim Start
  die richtigen.
- **Werte gingen verloren:** Divera liefert jedes Feld als Paar aus
  Felddefinition und Wert. Der Import las nur die Feldnamen – jeder Wunsch hieß
  „Divera-Import …" und stand auf 0 €. Jetzt kommen Bezeichnung, Anzahl,
  Betrag, Frist und alle anderen Felder an.
- **Auswahlfelder:** Bei Auswahl, Liste und Mehrfachauswahl liefert Divera die
  Kennung der Option statt ihres Textes. Der Import übersetzt sie jetzt
  („hoch", „Bergungsgruppe", „Einsatz, Ausbildung"). Überschriften-Felder
  fallen weg.
- **Mehr als 50 Einträge:** Divera liefert 50 je Seite; der Import holt jetzt
  alle Seiten.
- **Zuordnung vorgeschlagen:** „Formularfelder abrufen" liest die Felder auch
  aus der Formulardefinition – es muss also noch kein Eintrag vorhanden sein –
  und füllt leere Zuordnungen mit einem Vorschlag (Bezeichnung, Anzahl,
  Nettobetrag, Dringlichkeit …). Vorhandene Zuordnungen bleiben.
- **Anhänge:** Divera liefert Dateianhänge verschlüsselt aus, sie lassen sich
  nicht übernehmen. Der Wunsch vermerkt stattdessen, wie viele Dateien in
  Divera hängen.
- Die Formularschnittstelle verlangt den **persönlichen Accesskey**; ist er
  hinterlegt, wird er dafür genommen.
- Der automatische Formular-Import läuft nicht mehr im Minutentakt, sondern
  alle 15 Minuten (einstellbar) – ein Abruf kann jetzt mehrere Seiten umfassen.
- Das Änderungsprotokoll bekam bisher jede Minute einen Eintrag „divera.cron",
  auch ohne Import. Jetzt nur noch, wenn etwas angelegt wurde oder schiefging.

## 1.17.1

- **Build repariert:** Version 1.17.0 ließ sich nicht installieren. Das
  Dockerfile kopiert seit dieser Version den Änderungsverlauf in den Container,
  die `.dockerignore` schloss aber alle `*.md`-Dateien vom Build aus – der
  Build brach ab, weil `CHANGELOG.md` fehlte. Die Datei ist jetzt ausdrücklich
  zugelassen.

## 1.17.0

- **Versionsanzeige:** Unten auf jeder Seite steht jetzt, welche Version läuft.
  Ein Klick darauf öffnet **„Was ist neu"** mit den letzten Änderungen aus
  diesem Verlauf.
- **Beschreibung im Add-on-Store:** Das Info-Fenster von Home Assistant zeigt
  jetzt eine Übersicht aller Funktionen (neue Datei `README.md` im Add-on),
  die Kurzbeschreibung nennt alle Module.
- Die Add-on-Option `zeitzone` hat eine Beschriftung und Erklärung und steht
  in der Dokumentation.

## 1.16.1

### Einstellungen: Schalter gingen beim Speichern anderer Gruppen aus

- **Fehler behoben:** Das Speichern einer Gruppe unter *Verwaltung →
  Einstellungen* setzte **alle** Ja/Nein-Schalter der ganzen Anwendung, nicht
  nur die der angezeigten Gruppe. Da ein nicht angehakter Schalter im Formular
  gar nicht mitgeschickt wird, standen danach alle Schalter der anderen
  Gruppen auf „aus" – etwa der Stein.APP-Abgleich, nachdem man unter
  „Divera 24/7" gespeichert hatte. Jetzt wird nur die angezeigte Gruppe
  gespeichert.
- Interne Merkwerte (letzter Abruf, Pause nach Rate Limit, offene
  Zuordnungen) standen bisher als Textfelder unter „Allgemein" und wurden beim
  Speichern mit veralteten Werten überschrieben. Sie liegen jetzt in einer
  eigenen, unsichtbaren Gruppe.
### Stein.APP und Divera gleichzeitig

- Beide Abgleiche laufen unabhängig: Scheitert einer (etwa mit einem
  Datenbankfehler), laufen die anderen im Minutentakt trotzdem weiter.
  Bisher brach ein Fehler beim Stein-Abgleich auch den Divera-Abruf ab.
- Jeder Abgleich läuft nur einmal zur Zeit. Treffen Minutentakt und ein
  Seitenaufruf zusammen, wartet der zweite – und prüft danach frisch, ob
  überhaupt noch abgerufen werden muss. So gibt es keinen doppelten Aufruf
  gegen das Rate Limit der Stein.APP.
- Einträge ins Journal werden je Fahrzeug nacheinander geschrieben. Zwei
  gleichzeitige Einträge konnten sonst auf denselben Vorgänger zeigen, und
  die Prüfung hätte fälschlich eine Veränderung gemeldet.
- Beide schreiben in getrennte Felder: Status, HU und SP kommen aus der
  Stein.APP, Funkstatus, Position und Besatzung aus Divera. Kennzeichen, ISSI
  und Funkrufname füllt Divera nur, wenn sie leer sind – die Werte können
  also nicht hin- und herspringen.

### Hinweis

- Einmal wieder einschalten ist nötig: Schalter, die der Fehler schon
  ausgeschaltet hat, bleiben aus – welche vorher an waren, lässt sich nicht
  mehr erkennen. Bitte die Gruppen Stein.APP, Divera 24/7, Fahrzeuge, Budget,
  Kontakte und Besprechungen einmal durchsehen.

## 1.16.0

### Fahrzeugdaten aus Divera 24/7

- **Funkstatus als Fahrtenbuch:** Die Anwendung ruft den FMS-Status der
  Fahrzeuge ab (`/api/v2/pull/vehicle-status`, Vorgabe alle 2 Minuten) und
  schreibt jeden Wechsel mit Uhrzeit und Freitext ins Journal der
  Fahrzeugakte – Rubrik *Funkstatus*. Der aktuelle Status steht farbig in der
  Fahrzeugliste und in der Akte.
- **Letzte Position** mit Link auf OpenStreetMap und die **Besatzung**, die in
  Divera zugeordnet ist. Beides lässt sich abschalten.
- **Stammdaten:** OPTA und RIC als neue Felder am Fahrzeug, dazu Kennzeichen
  und ISSI, wenn bei uns noch nichts steht (`/api/v3/vehicles`, Vorgabe
  stündlich). Die v3-Schnittstelle ist bei Divera noch Beta und braucht einen
  persönlichen Accesskey mit Verwaltungsrechten.
- **Zuordnung:** Divera-Fahrzeuge werden über ISSI (auch eine von mehreren),
  Kennzeichen (egal ob mit Leerzeichen oder Bindestrich) oder Funkrufname
  erkannt – nur wenn genau eines passt. Der Rest steht unter
  *Verwaltung → Divera-Fahrzeuge* zum Zuordnen, mit Protokoll der Abrufe.
- Das Journal kennt jetzt die Quelle *Divera*; Suche in der Fahrzeugliste auch
  nach OPTA.
- Bestehende Installationen bekommen die neuen Spalten beim nächsten Start.

## 1.15.0

### Bilder und Dokumente an Fahrzeugen und Aufträgen

- **Bilder** je Fahrzeug in einer Galerie. Das erste Bild wird Titelbild und
  erscheint in der Fahrzeugliste und oben in der Akte; ein anderes lässt sich
  mit einem Klick zum Titelbild machen.
- **Dokumente** je Fahrzeug – Fahrzeugschein, Prüfberichte, Anleitungen. Welche
  Dateitypen erlaubt sind, steht in *Einstellungen → Fahrzeuge*. HTML, SVG,
  PHP und Ähnliches kommen nie durch, auch wenn man sie dort einträgt; eine
  Datei, deren Inhalt nicht zur Endung passt, wird abgelehnt.
- **An Aufträgen**: Fotos vom Schaden, Kostenvoranschlag, Rechnung. Bilder
  werden dabei selbst erkannt. In der Fahrzeugakte stehen sie mit Verweis auf
  den Auftrag.
- **Fotos vom Handy** werden neu gespeichert. Dabei fallen die Metadaten weg,
  auch der Aufnahmeort. Galeriebilder werden zusätzlich auf 1600 Pixel
  verkleinert, gedreht, falls das Handy nur eine Drehangabe gespeichert hat,
  und bekommen ein Vorschaubild. Fotos, die als Dokument hochgeladen werden
  (etwa der Fahrzeugschein), behalten ihre Auflösung. PDFs und andere
  Dokumente bleiben unverändert.
- Hochladen dürfen alle, die Schäden melden dürfen; Entfernen und Titelbild
  setzen die Leitung. Jedes Hinzufügen und Entfernen steht im Journal, das eine
  eigene Rubrik *Dateien* bekommt.
- Das Add-on bringt dafür die PHP-Erweiterungen GD und EXIF mit. Ohne sie
  werden Bilder unverändert gespeichert.

### Kleinigkeiten

- Veraltete Aufrufe für PHP 8.5 ersetzt (`finfo_close`, `imagedestroy`), auch
  bei den Anlagen der Wünsche.

## 1.14.0

### ISSI am Fahrzeug

- Fahrzeuge haben jetzt ein eigenes Feld für die **ISSI** (Funkrufkennung).
  Sie steht in der Fahrzeugliste unter Bezeichnung und Funkrufname, in den
  Stammdaten der Akte und lässt sich im Formular pflegen. Mehrere Kennungen
  in einem Feld („7120028, 7120029, 7140977") sind möglich.
- Die **Suche** in der Fahrzeugliste findet Fahrzeuge auch über die ISSI.
- Der Abgleich mit der Stein.APP übernimmt die ISSI, solange im Fahrzeug nichts
  Eigenes steht; ändert sie sich dort, zieht sie nach. Jede Änderung steht im
  Journal.
- Bestehende Installationen bekommen die Spalte beim nächsten Start.

## 1.13.1

- An echten Daten eines Ortsverbands geprüft: Das THW-Kennzeichen steht in der
  Schnittstelle im Feld `name`. Auch die Schreibweise mit zwei Leerzeichen
  („THW  99020") wird sauber übernommen.
- Beim automatischen Anlegen ist die **Bezeichnung** jetzt das Feld
  „Fahrzeug / Bez." der Stein.APP (`label`) – vorher wurden Bezeichnung,
  Funkrufname und Kennzeichen aneinandergehängt.

## 1.13.0

### Antworten der Stein.APP mitschneiden

Zur Fehlersuche gibt es einen Schalter *Antworten der Stein.APP mitschneiden*
(*Einstellungen → Stein.APP*, standardmäßig aus).

- Ist er an, wird jede Antwort der Schnittstelle als Datei abgelegt – auch
  Fehlerantworten wie „404" oder „429".
- Unter *Verwaltung → Stein.APP* stehen die Mitschnitte zum **Herunterladen**,
  mit Zeitpunkt und Größe, und lassen sich dort auch alle löschen.
- **Der API-Schlüssel steht nicht darin.** Zusätzlich werden Felder, deren Name
  nach einem Geheimnis klingt (`secret`, `token`, `password`, `webhookSecret`
  und ähnliche), durch `***` ersetzt – auch tief verschachtelt.
- Jede Datei enthält Zeitpunkt, angefragte Adresse, HTTP-Status und die
  Antwort als gültiges JSON. Antworten, die kein JSON sind, stehen als Text
  darin.
- Aufbewahrt werden die letzten 20 Dateien, ältere räumt die Anwendung selbst
  weg. Herunterladen darf nur die Administration.

## 1.12.1

### Kennzeichen aus dem Feld der Stein.APP

- Das Feld *THW-Kennzeichen* der Stein.APP wird jetzt direkt genommen. In der
  Schnittstelle heißt es nicht so – es steckt im Feld `name`. Steht dort nur
  ein Kennzeichen, wird es **unverändert** übernommen, also auch „THW 99020"
  mit Leerzeichen statt Bindestrich.
- Ändert sich das Kennzeichen in der Stein.APP, zieht es nach – aber nur, wenn
  es von dort stammt. Ein von Hand eingetragenes bleibt stehen.
- In der Fahrzeugakte gibt es für die Administration den aufklappbaren Punkt
  **Rohdaten aus der Stein.APP**. Dort steht, was die Schnittstelle je Fahrzeug
  wirklich liefert.

## 1.12.0

### Stein.APP: Abgleich an die Spezifikation angepasst

Grundlage sind jetzt die offizielle Spezifikation
(<https://stein.app/api/api/doc/api-doc.yaml>) und die Hinweise unter
<https://stein.app/api/api/doc/intro>.

- **Kennzeichen** werden übernommen. Ein eigenes Feld dafür kennt die
  Schnittstelle nicht, deshalb liest die Anwendung es aus Bezeichnung, Name,
  Funkrufname und Bemerkung – und trägt es ein, solange im Fahrzeug keines
  steht. Das gilt nicht mehr nur beim Anlegen, sondern bei jedem Abgleich; der
  Fund steht in der Fahrzeugakte.
- Neu mitgeführt wird, ob ein Fahrzeug **in der Stein.APP gelöscht** wurde.
  Gelöschte Fahrzeuge werden nicht mehr automatisch angelegt.
- Fehlende Ja/Nein-Angaben gelten als „nein". Der erste Abgleich meldet dadurch
  keinen Wechsel des Einsatzvorbehalts mehr, den es nie gab.
- Die Fahrzeugakte zeigt unter **Stand in der Stein.APP** Status, Kategorie,
  ISSI, Einsatzvorbehalt, Bemerkung, HU und SP sowie wer dort zuletzt etwas
  geändert hat. Die Verknüpfung lässt sich dort auch lösen.
- **Verbindung testen** in *Verwaltung → Stein.APP* prüft den Schlüssel über
  einen eigenen, sehr kleinen Endpunkt, ohne die Fahrzeuge abzurufen.

### Rate Limit und Webhook

- Die Grenzen sind jetzt bekannt: **20 Anfragen je Minute und IP**, sonst
  **eine Stunde Sperre**. Nach HTTP 429 pausiert der Abruf deshalb eine Stunde
  statt wie bisher 30 Minuten.
- Antwortet die Stein.APP mit 404, weist die Meldung auf die Sperre für
  IP-Adressen außerhalb Deutschlands hin.
- **Webhook**: Neue Adresse `/webhook.php`. Trägt man sie in der Stein.APP ein
  und hinterlegt das Webhook-Secret in den Einstellungen, meldet die Stein.APP
  Änderungen von selbst – das ist schonender als regelmäßiges Abfragen. Der
  Aufruf wird über den Header `X-Secret` geprüft; abgeglichen wird höchstens
  alle 30 Sekunden. Die Adresse muss von außen erreichbar sein.
- *Verwaltung → Stein.APP* nennt die Grenzen und den Zustand des Webhooks.

## 1.11.2

- Beschriftungen und Hinweistexte der Einstellungen werden beim Start
  aufgefrischt. Bisher wurden sie nur beim allerersten Start angelegt – wer
  schon eine Installation hatte, sah neben einer Option weiterhin den alten
  Erklärtext. Betrifft nur die Texte; die eingestellten Werte bleiben
  unverändert.

## 1.11.1

### Zeitzone

- Der Container stellt die Zeitzone jetzt immer ein – für die Anwendung **und**
  die mitgelieferte MariaDB. Vorher lief die Datenbank in UTC, weshalb im
  Protokoll der Stein.APP-Abrufe Zeiten zwei Stunden daneben standen.
- Neue Add-on-Option **`zeitzone`** (Vorgabe `Europe/Berlin`). Ohne Angabe gilt
  die von Home Assistant übergebene `TZ`, sonst Europe/Berlin.
- Bei einer externen Datenbank setzt die Anwendung die Zeitzone der Verbindung
  passend zu PHP – auch über den Wechsel auf Sommerzeit hinweg.
- Die Startseite der Verwaltung warnt, wenn die Zeit der Anwendung und die der
  Datenbank um mehr als zwei Minuten auseinandergehen.

### Automatisch angelegte Fahrzeuge

- Die Einstellung *Unbekannte Fahrzeuge selbst anlegen* legt eine Akte nur noch
  dann an, wenn ein **Kennzeichen** erkennbar ist: in einem Kennzeichenfeld der
  Stein.APP oder im Text (THW-84321, HB-XY 456). Anhänger, Aggregate und Geräte
  ohne Kennzeichen erscheinen weiterhin in der Verwaltung zum Zuordnen, dort mit
  dem Hinweis „kein Kennzeichen erkannt".
- Das erkannte Kennzeichen wird in die neue Akte übernommen – auch beim
  Zuordnen von Hand.

## 1.11.0

### Neues Modul: Fahrzeuge mit Fahrzeugakte

- **Fahrzeugstamm** mit Bezeichnung, Funkrufname, Kennzeichen, Art, Fachgruppe,
  Technik, Standort und den Fristen **HU, SP und UVV**. Abgelaufene und bald
  fällige Fristen stehen in der Liste, in der Akte und auf der Startseite.
- **Fahrzeugakte mit Journal:** Jede Änderung an den Stammdaten, jeder
  Auftragsschritt, jede Notiz und jede Meldung aus der Stein.APP landet als
  Eintrag im Journal. Einträge lassen sich **nicht ändern und nicht löschen** –
  es gibt dafür keine Funktion. Zusätzlich trägt jeder Eintrag die Prüfsumme
  des vorherigen; „Journal auf Veränderungen prüfen" rechnet die Kette nach und
  meldet, wenn jemand an der Datenbank vorbei etwas verändert oder gelöscht hat.
- **Instandsetzungsaufträge** mit fortlaufender Nummer (z. B. 2026-0007), Art,
  Priorität, Werkstatt, Auftragsnummer der Werkstatt, geschätzten und
  tatsächlichen Kosten sowie einem Bearbeitungsstand von *Gemeldet* bis
  *Erledigt*. Ein Auftrag kann als „Fahrzeug steht still" markiert werden.
- Mitglieder dürfen **Schäden melden** und ins Journal schreiben (abschaltbar);
  Stammdaten und Auftragsstand pflegt die Leitung.

### Schnittstelle zur Stein.APP

- Regelmäßiger Abgleich über `GET /assets/?buIds=<BU-ID>` mit Bearer-Token –
  derselbe Schlüssel wie für die Home-Assistant-Integration *THW Stein*.
- **Rate Limit ernst genommen:** je Durchgang genau ein Aufruf, dazwischen
  mindestens das eingestellte Intervall (Vorgabe 10 Minuten). Bei HTTP 429
  legt der Abruf von selbst eine Pause ein. Im Add-on ruft der Container
  `cron.php` jede Minute auf; abgerufen wird nur, wenn das Intervall um ist.
- Aus dem kompletten Stand wird der Unterschied zum letzten Abruf berechnet.
  Änderungen an Status, Bemerkung, HU, SP, Einsatzvorbehalt, Funkrufname und
  Kategorie stehen danach in der Fahrzeugakte.
- *Verwaltung → Stein.APP*: Fahrzeuge zuordnen oder als neue Akte übernehmen,
  Abgleich von Hand starten, Protokoll der Abrufe ansehen.

## 1.10.0

### Anwesenheit bei Besprechungen

- Jede Besprechung hat einen Abschnitt **Anwesenheit**. Je Person lässt sich
  *Eingeladen*, *Zugesagt*, *Teilgenommen*, *Entschuldigt* oder *Nicht
  erschienen* setzen, dazu eine kurze Bemerkung. Die Bezeichnungen und Farben
  sind wie überall über die Auswahlliste *Status (Teilnahme)* änderbar.
- **Eingeladene** kommen aus drei Quellen: Benutzer des Ortsverbands, Kontakte
  (einzeln oder als ganzer Verteiler) und frei eingetippte Namen.
- **Liste übernehmen:** Bei einem Termin ohne Teilnehmerliste bietet die
  Anwendung die Liste des letzten Termins derselben Serie oder Besprechungsart
  zum Übernehmen an; der Status beginnt dann wieder bei *eingeladen*.
- Sammelknöpfe setzen alle noch offenen Einträge auf einmal, etwa „Alle offenen:
  waren da". Über der Liste stehen die Zahlen: wie viele da waren, entschuldigt
  oder nicht erschienen.
- Im **Protokoll-Ausdruck** stehen Anwesende, Entschuldigte und Nicht
  Erschienene mit Namen, in der **Tagesordnung** die Eingeladenen. Das bisherige
  Freitextfeld bleibt als Ergänzung erhalten.
- Mitglieder ohne Verwaltungsrecht sehen die Liste, ändern können sie nur
  Leitung und Administration.

## 1.9.0

### Bestellberechtigungen nach Rolle und Funktion

- Neue Seite *Verwaltung → Bestellberechtigungen*: Für jede Rolle
  (Administration, Leitung, Mitglied) und jede Funktion im OV lässt sich
  festlegen, ob sie Wünsche **freigeben** darf, **bis zu welchem Nettobetrag**
  (leer = unbegrenzt) und ob sie Wünsche **als bestellt markieren** darf.
- Hat jemand mehrere Rollen oder Funktionen, reicht eine Berechtigung, und die
  höchste Grenze gilt. Unten auf der Seite steht, was das für jeden aktiven
  Benutzer bedeutet.
- **Vier-Augen-Prinzip** zuschaltbar: Eigene Wünsche muss dann eine andere
  Person freigeben.
- Beim Wunsch steht, wer ihn freigeben kann. Wer grundsätzlich berechtigt ist,
  aber nicht für diesen Betrag, sieht den Grund statt des Knopfes.
- Die Freigabe lässt sich nicht mehr über die Statusauswahl oder das
  Bearbeitungsformular umgehen. Wird ein freigegebener Wunsch nachträglich
  teurer, braucht das eine Freigabeberechtigung für den neuen Betrag.
- Vorgaben: Administration und Leitung behalten ihre bisherigen Rechte
  (freigeben unbegrenzt, bestellen). Neu dürfen Ortsbeauftragte:r und
  stellv. Ortsbeauftragte:r freigeben, Verwaltungsbeauftragte:r als bestellt
  markieren. Vorhandene Einstellungen werden bei Updates nicht überschrieben.

## 1.8.0

### „Freigegeben, bitte bestellen"

- Neuer Knopf **„Freigegeben, bitte bestellen"** in der Budgetübersicht und
  beim Wunsch. Er setzt den neuen Status *Freigegeben – bitte bestellen* und
  vermerkt, wer wann freigegeben hat. Vor dem Freigeben fragt die Anwendung mit
  Bezeichnung und Nettobetrag nach.
- Die Budgetübersicht zeigt oben **„Freigegeben – bitte bestellen"** mit Anzahl
  und Summe und darunter die Wünsche, die noch auf Freigabe warten (die 15
  wichtigsten). **„Ist bestellt"** setzt den Status *Bestellt*; der
  Freigabevermerk bleibt am Wunsch erhalten.
- Die Startseite weist auf freigegebene, noch nicht bestellte Wünsche hin.
- Freigeben und Bestellt-Markieren dürfen Leitung und Administration.
- Bestehende Installationen erhalten den Status und zwei Spalten
  (`freigegeben_von`, `freigegeben_am`) beim nächsten Start automatisch.

## 1.7.0

### Wiederkehrende Besprechungen

Unter *Besprechungen → + Serie* lassen sich regelmäßige Termine anlegen.

- **Rhythmen:** wöchentlich oder alle n Wochen („alle 2 Wochen montags"),
  monatlich an einem Wochentag („jeden 2. Montag im Monat", „jeden letzten
  Freitag"), monatlich an einem Kalendertag („am 15."), jeweils auch nur jeden
  n-ten Monat.
- Der Rhythmus richtet sich am Startdatum aus – es legt fest, in welcher Woche
  „alle 2 Wochen" beginnt. Gibt es einen Monatstag nicht, gilt der Monatsletzte.
- Vor dem Speichern zeigt eine **Vorschau** die nächsten Termine.
- Aus der Serie entstehen die Termine der nächsten Wochen als echte
  Besprechungen (Vorlauf einstellbar, Vorgabe 60 Tage). Dadurch lassen sich
  Themen schon auf die nächste Dienstbesprechung setzen und je Termin ein
  Protokoll führen. Neue Termine rücken automatisch nach.
- **Einzelne Termine** lassen sich verschieben oder absagen, ohne die Serie zu
  ändern; die Serie legt sie nicht erneut an. Offene Themen eines abgesagten
  Termins wandern zurück in den Themenspeicher.
- Wird eine Serie geändert, pausiert oder gelöscht, werden nur künftige Termine
  neu erzeugt oder entfernt, die noch niemand angefasst hat. Termine mit
  Themen, Notizen oder eigenen Änderungen bleiben unverändert.

### Behoben

- Per Skript ausgeblendete Formularfelder blieben sichtbar, weil eine
  CSS-Regel das hidden-Attribut überstimmte. Betroffen war auch das
  Aufgabenformular: dort standen die Auswahlfelder für Fachgruppe, Funktion und
  Person immer alle gleichzeitig da.

## 1.6.0

### Kontakt-Import

Unter *Kontakte → Importieren* lassen sich Kontakte in drei Schritten übernehmen:
Datei hochladen, Zuordnung prüfen, importieren.

- **CSV** aus Excel, LibreOffice, Outlook (deutsch und englisch) und Google
  Kontakte. Trennzeichen und Zeichensatz werden erkannt – auch der
  Windows-Zeichensatz, den Excel auf deutschen Rechnern schreibt, und die
  UTF-16-Dateien aus Outlooks „Unicode"-Export.
- **vCard** (.vcf) in den Versionen 2.1, 3.0 und 4.0, etwa vom Handy, aus iCloud
  oder Thunderbird; mehrere Kontakte je Datei. Bei mehreren Adressen oder
  Nummern gewinnt die dienstliche.
- Die **Spaltenzuordnung** wird anhand der Spaltennamen geschätzt und lässt sich
  je Spalte ändern – auch auf die frei definierten Zusatzfelder.
- **Vorschau** vor dem Speichern: je Zeile, ob neu angelegt, ergänzt,
  überschrieben oder übersprungen wird, und warum.
- **Dubletten** werden über die E-Mail-Adresse erkannt, ohne E-Mail über Name
  und Organisation. Wahlweise überspringen, nur leere Felder ergänzen,
  überschreiben oder trotzdem neu anlegen. Doppelte Einträge innerhalb der
  Datei werden immer nur einmal übernommen.
- Kategorien aus der Datei werden streng zugeordnet und auf Wunsch neu angelegt.
- Importierte Kontakte lassen sich gleich auf einen Verteiler setzen.
- Der Import läuft in einer Transaktion: bricht er ab, bleibt der Bestand
  unverändert.
- Vorlage zum Ausfüllen als CSV.

### Besprechungen und Talking Points

Neues Modul, um Themen für Besprechungen zu sammeln und Ergebnisse festzuhalten.

- **Themenspeicher**: Jedes Mitglied kann Themen einbringen (abschaltbar), mit
  Hintergrund, Dringlichkeit, betroffener Fachgruppe und Zeitbedarf.
- **Besprechungen** mit Art, Datum, Uhrzeit, Ort, Leitung und Protokollführung;
  Ort, Leitung und Uhrzeit werden von der letzten Besprechung derselben Art
  übernommen.
- Themen auf die **Tagesordnung** setzen, einzeln oder mehrere auf einmal, und
  die Reihenfolge verschieben. Aus dem Zeitbedarf ergeben sich geplante
  Uhrzeiten und das voraussichtliche Ende.
- Während der Sitzung je Thema **Status, Verantwortliche und Ergebnis**
  festhalten und mit einem Klick **eine Aufgabe daraus machen**.
- **Vertagte Themen** landen wieder im Themenspeicher. Übernommen wird eine
  Kopie, damit das Protokoll der ersten Besprechung unverändert bleibt. Beim
  Abschließen einer Besprechung gelten noch offene Themen als vertagt.
- **Druckansicht** als Tagesordnung (mit Platz für Notizen) oder als Protokoll.
- Besprechungsarten und Themenstatus sind im Admin pflegbar, die Bezeichnung
  „Talking Points" ist umbenennbar.

### Sonstiges

- Die Navigation am Handy lässt sich seitlich wischen, statt bei vielen
  Modulen die Beschriftungen abzuschneiden; der aktive Eintrag rückt beim
  Laden ins Bild.

## 1.5.0

### Budgetübersicht kann unscharf

Unter *Verwaltung → Einstellungen → Budget* lässt sich einstellen, ob die
Übersicht centgenau rechnet oder auf **10, 100 oder 1.000** gerundet anzeigt.
Gerundet entfallen die Nachkommastellen, und ein Hinweis nennt die Stufe.
Betroffen ist nur die Übersichtsseite – die Listen und die CSV-Ausgaben
bleiben immer centgenau, ebenso die Rechnung im Hintergrund.

### Kontaktverwaltung

Neuer Bereich für die Ansprechpartner ausserhalb des Ortsverbands.

- **Kontakte** mit Anrede, Titel, Name, Organisation, Funktion, Kategorie,
  E-Mail, Telefon, Anschrift, Briefanrede und Notiz
- **frei definierbare Zusatzfelder**, wie schon bei den Wünschen: eine Zeile
  je Feld unter *Einstellungen → Kontakte*, Typen text, textarea, number,
  bool und date. Sie erscheinen im Formular und in der CSV-Ausgabe.
- **Verteiler** für Einladungen: Kontakte einzeln oder gleich kategorieweise
  übernehmen, je Kontakt Status (offen, eingeladen, zugesagt, abgesagt),
  Personenzahl und Bemerkung festhalten
- **CSV für den Serienbrief** inklusive fertiger Briefanrede, die aus Anrede
  und Nachname gebildet wird, wenn keine eigene hinterlegt ist
- **E-Mail-Adressen zum Kopieren** für das Blindkopie-Feld
- Kategorien und Einladungsstatus sind wie alles andere im Admin pflegbar
- Kontakte enthalten personenbezogene Daten: standardmässig sehen sie nur
  Leitung und Administration, freigebbar per Einstellung

## 1.4.1

- CSS und JavaScript bekommen einen Versionsstempel in der Adresse. Der
  Webserver lässt beide eine Woche zwischenspeichern; ohne Stempel benutzte
  der Browser nach einer Aktualisierung weiter die alte Datei – neue Regeln
  blieben wirkungslos, etwa beim Monatsdiagramm im Budget, das dadurch als
  blosse Liste von Monatsnamen erschien.

## 1.4.0

### Einnahmen im Budget

Neben den Ausgaben lassen sich jetzt auch **Einnahmen** erfassen – vor allem
Kostenerstattungen für Einsätze und technische Hilfeleistung.

- eigene Kategorienliste: Einsatzkostenerstattung, technische Hilfeleistung,
  Amtshilfe, Absicherung, Ausbildung, Spenden, Förderung, Verkauf und mehr,
  frei pflegbar unter Verwaltung → Auswahllisten
- Feld für die **Einsatz- oder Auftragsnummer**, dazu Auftraggeber und
  Rechnungsnummer; die Beschriftungen wechseln je nach Richtung
- die Übersicht rechnet **Jahresbudget + Einnahmen − Ausgaben**: verfügbare
  Mittel, Verbrauch, freier Rest
- Aufschlüsselung nach Kategorie für beide Richtungen nebeneinander
- Monatsverlauf zeigt Einnahmen und Ausgaben als zwei Balken je Monat
- getrennte Listen und CSV-Ausgaben unter Budget → Ausgaben / Einnahmen

Bestehende Buchungen gelten unverändert als Ausgabe.

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
