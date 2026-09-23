# OV-Budget-Connector

Briefkasten für alles, was von außen zu OV-Budget hereinkommt. Er läuft auf
einem öffentlich erreichbaren Webserver mit PHP 8.1 oder neuer und hat zwei
Aufgaben:

* **Fahrzeuge** – Standortmeldungen aus dem QR-Code im Fahrzeug. Wer ihn
  scannt, landet auf einer kleinen Seite und kann einmalig oder im Takt den
  Standort melden.
* **Veranstaltungen** – Einladungen. Hinter einer kurzen Adresse
  (`https://i.example.de/AB23CD`) steht eine Seite mit Titel, Zeitpunkt und
  Ort; dort sagt man zu, ab, kündigt Begleiter an oder nennt eine Vertretung.

Beides ohne Anmeldung, ohne Zugang zu OV-Budget und ohne Home Assistant.
Welche der beiden Aufgaben ein Connector übernimmt, steht in OV-Budget unter
*Verwaltung → Connectoren*; es können auch beide sein, und es können mehrere
Connectoren nebeneinander stehen.

## Was der Connector nicht kann

Er kann die Meldungen **nicht lesen**. Handy und Browser verschlüsseln sie an
Ort und Stelle für den öffentlichen Schlüssel von OV-Budget (ECDH auf P-256,
dann AES-256-GCM). Hier liegt nur Geheimtext, und der wird beim Abholen
gelöscht.

Er weiß auch **nicht, um wen es geht**:

* Von den Zugängen der Fahrzeuge und den Einladungscodes sind nur
  **Prüfsummen** (SHA-256) gespeichert, nie die Codes selbst. Aus einer
  erbeuteten `daten/einladungen.json` lässt sich keine gültige Einladung bauen.
* **Fahrzeuge:** Bezeichnung und Kennzeichen stehen im **Anker der Adresse**,
  also hinter dem `#`. Browser schicken den Anker nicht an den Server; die
  Melde-Seite setzt ihn selbst ein.
* **Veranstaltungen:** Titel, Zeitpunkt, Ort und der Hinweistext liegen im
  Klartext hier – die Einladungsseite muss sie zeigen. **Wer eingeladen ist,
  steht nicht hier**: keine Namen, keine Adressen, keine Rückmeldungen im
  Klartext.

Ein Unterschied bleibt: Den Einladungscode bekommt der Server beim Aufruf zu
sehen, sonst könnte er die Seite nicht ausliefern. Gespeichert wird er nicht,
und für sich genommen sagt er nichts darüber aus, wer ihn bekommen hat.

## Einrichten

1. Den Ordner `connector/` auf den Webserver legen.
2. Das Dokumentenverzeichnis auf **`connector/public`** zeigen lassen.
   Liegt `daten/` im Web, wären Kopplungscode und Protokoll erreichbar; für
   Apache liegt vorsichtshalber eine `.htaccess` darin.
3. Die Startseite einmal im Browser aufrufen. Dabei legt der Connector
   `daten/` an und schreibt einen Kopplungscode nach
   `daten/kopplungscode.txt`.
4. Den Code per FTP oder SSH holen — er steht bewusst nicht auf der Webseite.
5. In OV-Budget unter *Verwaltung → Connectoren* einen Connector anlegen,
   Adresse und Code eintragen, die Verwendung ankreuzen und koppeln.

Danach tauschen beide Seiten ihre öffentlichen Schlüssel aus, der Code ist
verbraucht, und jede weitere Anfrage ist signiert.

Neu koppeln: auf dem Server `daten/kopplung.json` löschen, dann beginnt es
wieder bei Schritt 3.

## Kurze Einladungsadressen

Einladungen sollen auf Papier kurz sein. Ohne weiteres Zutun lautet die
Adresse `https://ov.example.de/connector/index.php?e=AB23CD` – das geht, ist
aber nichts zum Abtippen.

Schöner ist ein eigener Name für dieselbe Installation, etwa
`https://i.example.de`. In OV-Budget wird er beim Connector als *kurze
Adresse* eingetragen, dann stehen Einladungen so auf dem Papier:

```
https://i.example.de/AB23CD
```

Der Webserver muss den Code auf `index.php` umschreiben. Für **Apache** liegt
die Regel bereits in `public/.htaccess` (dafür `AllowOverride All` oder die
Regel direkt in den vHost). Für **nginx**:

```nginx
server {
    server_name i.example.de;
    root /var/www/connector/public;
    index index.php;

    # /AB23CD -> index.php?e=AB23CD
    location ~ "^/([0-9A-Za-z]{4,16})/?$" {
        try_files $uri /index.php?e=$1;
    }

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

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
* **Meldungen von außen:** bewusst ohne Anmeldung — wer den QR-Code oder die
  Einladung hat, darf melden. Begrenzt auf 120 Standortmeldungen je Fahrzeug
  und Stunde, 20 Rückmeldungen je Einladung und Stunde sowie 300 je Anschluss;
  höchstens 500 unabgeholte je Zugang, größere Pakete als 4 KB (Standort) bzw.
  8 KB (Rückmeldung) werden abgewiesen.
* **Rückmeldefrist:** Steht bei der Veranstaltung eine, nimmt der Connector
  danach nichts mehr an.
* **Zurückziehen:** In OV-Budget den QR-Code oder den Einladungscode neu
  erzeugen oder die Person von der Gästeliste nehmen — beim nächsten Abgleich
  verwirft der Connector auch die wartenden Meldungen dazu.
* **Ablage:** nur Prüfsummen der Zugänge und Codes, verschlüsselte Meldungen,
  Zeitstempel und die Angaben, die auf der Einladungsseite stehen müssen.

Was bleibt: Wer einen QR-Code abfotografiert oder eine Einladung weitergibt,
kann bis zum Zurückziehen in fremdem Namen melden. Deshalb steht in der
Fahrzeugakte bei jeder Position, woher sie kommt, und in der Gästeliste, ob
eine Rückmeldung über die Einladung oder von Hand kam.

## Dateien

```
connector/
├── public/            <- Dokumentenverzeichnis
│   ├── index.php      Einstieg, alle Aufrufe
│   ├── .htaccess      kurze Einladungsadressen (Apache)
│   └── assets/melden.js, assets/einladung.js
├── src/
│   ├── connector.php  Ablage, Kopplung, Signaturen, Begrenzungen
│   ├── seite_melden.php
│   └── seite_einladung.php
└── daten/             wird beim ersten Aufruf angelegt (nicht ins Web!)
```
