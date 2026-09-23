# OV-Budget-Connector

Briefkasten für Standortmeldungen aus Fahrzeugen. Er läuft auf einem öffentlich
erreichbaren Webserver mit PHP 8.1 oder neuer und hat genau eine Aufgabe:
Meldungen von Handys entgegennehmen und an OV-Budget weiterreichen.

In den Fahrzeugen hängt ein QR-Code. Wer ihn scannt, landet auf einer kleinen
Seite, kann einmalig oder im Takt den Standort melden – ohne Anmeldung, ohne
Zugang zu OV-Budget und ohne Home Assistant.

## Was der Connector nicht kann

Er kann die Meldungen **nicht lesen**. Das Handy verschlüsselt die Position im
Browser für den öffentlichen Schlüssel von OV-Budget (ECDH auf P-256, dann
AES-256-GCM). Hier liegt nur Geheimtext, und der wird beim Abholen gelöscht.

Er weiß auch **nicht, um welche Fahrzeuge es geht**:

* Gespeichert werden nur **Prüfsummen** der Zugänge (SHA-256), nie die Zugänge
  selbst. Aus einer erbeuteten `daten/fahrzeuge.json` lässt sich also kein
  gültiger QR-Code bauen.
* Bezeichnung und Kennzeichen stehen im **Anker der Adresse**, also hinter dem
  `#`. Browser schicken den Anker nicht an den Server; die Melde-Seite setzt
  ihn selbst ein.

Auf dem Server liegen damit nur Prüfsummen, Geheimtext und Zeitpunkte. Wer ihn
kontrolliert, sieht *dass* um 14:02 für irgendein Fahrzeug gemeldet wurde –
nicht für welches und nicht wo.

## Einrichten

1. Den Ordner `connector/` auf den Webserver legen.
2. Das Dokumentenverzeichnis auf **`connector/public`** zeigen lassen.
   Liegt `daten/` im Web, wären Kopplungscode und Protokoll erreichbar; für
   Apache liegt vorsichtshalber eine `.htaccess` darin.
3. Die Startseite einmal im Browser aufrufen. Dabei legt der Connector
   `daten/` an und schreibt einen Kopplungscode nach
   `daten/kopplungscode.txt`.
4. Den Code per FTP oder SSH holen — er steht bewusst nicht auf der Webseite.
5. In OV-Budget unter *Verwaltung → Standortmeldung per QR-Code* die Adresse
   des Connectors und den Code eintragen und koppeln.

Danach tauschen beide Seiten ihre öffentlichen Schlüssel aus, der Code ist
verbraucht, und jede weitere Anfrage ist signiert.

Neu koppeln: auf dem Server `daten/kopplung.json` löschen, dann beginnt es
wieder bei Schritt 3.

## Voraussetzungen

* PHP 8.1+ mit `openssl` und `json`
* HTTPS – ohne verschlüsselte Verbindung lehnt der Connector alles ab
* Schreibrecht auf `connector/daten/`

## Sicherheit in Stichworten

* **Kopplung:** 72 Bit Zufall, zehn Fehlversuche je Stunde, danach Sperre.
* **Anfragen von OV-Budget:** ECDSA-signiert, höchstens fünf Minuten alt,
  jede Einmalkennung nur einmal, der Zweck steht mit in der Signatur.
* **Antworten:** signiert der Connector, damit OV-Budget die Gegenseite prüfen
  kann.
* **Meldungen vom Handy:** bewusst ohne Anmeldung — wer den QR-Code hat, darf
  melden. Begrenzt auf 120 Meldungen je Fahrzeug und Stunde sowie 300 je
  Anschluss; höchstens 500 unabgeholte je Fahrzeug, größere Pakete als 4 KB
  werden abgewiesen.
* **Zurückziehen:** In OV-Budget den Code neu erzeugen oder zurückziehen —
  dann verwirft der Connector auch die wartenden Meldungen dazu.
* **Ablage:** nur Prüfsummen der Zugänge, verschlüsselte Meldungen und
  Zeitstempel. Keine Fahrzeugnamen, keine Kennzeichen, keine Klartext-Zugänge.

Was bleibt: Wer den QR-Code abfotografiert, kann bis zum Zurückziehen falsche
Standorte melden. Deshalb steht in der Fahrzeugakte bei jeder Position, woher
sie kommt, und im Journal landet nur, was als Parkposition gilt.

## Dateien

```
connector/
├── public/            <- Dokumentenverzeichnis
│   ├── index.php      Einstieg, alle Aufrufe
│   └── assets/melden.js
├── src/
│   ├── connector.php  Ablage, Kopplung, Signaturen, Begrenzungen
│   └── seite_melden.php
└── daten/             wird beim ersten Aufruf angelegt (nicht ins Web!)
```
