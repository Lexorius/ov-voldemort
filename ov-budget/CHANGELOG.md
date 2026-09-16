# Änderungsverlauf

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
