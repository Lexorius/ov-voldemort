<?php
declare(strict_types=1);

/**
 * Einstieg des Connectors. Alles läuft über diese Datei.
 *
 *   ?p=melden&fz=<Zugang>   Seite für das Handy (aus dem QR-Code)
 *   ?p=position             Meldung des Handys (POST, verschlüsselt)
 *   /<Code> oder ?e=<Code>  Einladungsseite einer Veranstaltung
 *   ?p=rueckmeldung         Rückmeldung auf eine Einladung (POST, verschlüsselt)
 *   ?p=koppeln              einmalige Kopplung mit OV-Budget (POST)
 *   ?p=fahrzeuge            Liste der Zugänge setzen (POST, signiert)
 *   ?p=veranstaltungen      Veranstaltungen und Einladungen setzen (POST, signiert)
 *   ?p=abholen              Standortmeldungen abholen (POST, signiert)
 *   ?p=rueckmeldungen       Rückmeldungen abholen (POST, signiert)
 *   ?p=zustand              Zahlen für OV-Budget (POST, signiert)
 *   sonst                   Startseite: Feld für den Einladungscode
 *
 * Die kurze Adresse (z. B. https://i.example.de/AB23CD) kommt über eine
 * Umschreibregel des Webservers hier an; siehe public/.htaccess.
 */
require dirname(__DIR__) . '/src/connector.php';

header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');

/** Nur echte Zeichenketten annehmen – ?fz[]=… käme sonst als "Array" an */
function nur_text(mixed $wert): string
{
    return is_string($wert) ? trim($wert) : '';
}

$p = nur_text($_GET['p'] ?? '');
$token = nur_text($_GET['fz'] ?? '');

// Einladungscode: aus ?e= oder aus dem Pfad hinter index.php (kurze Adresse)
$code = nur_text($_GET['e'] ?? '');
if ($code === '' && ($_SERVER['PATH_INFO'] ?? '') !== '') {
    $code = trim(nur_text($_SERVER['PATH_INFO']), '/');
}
if ($code !== '' && $p === '') {
    $p = 'einladung';
}

// Andere Verfahren als diese drei hat der Connector nicht zu beantworten
if (!in_array(strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')), ['GET', 'HEAD', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD, POST');
    header('Content-Type: text/plain; charset=UTF-8');
    exit("Nur GET, HEAD und POST.
");
}

// Ohne verschlüsselte Verbindung nehmen wir nichts entgegen und geben nichts heraus
if (!con_https()) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    exit("Dieser Dienst arbeitet nur über https.\n");
}

/** JSON beantworten und dabei signieren, wenn die Kopplung steht */
function antwort(array $daten, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    $koerper = (string)json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (con_gekoppelt()) {
        $sig = con_signiere($koerper);
        if ($sig !== '') {
            header('X-Signatur: ' . $sig);
        }
    }
    echo $koerper;
    exit;
}

function fehler(string $text, int $code = 400): never
{
    antwort(['ok' => false, 'fehler' => $text], $code);
}

/**
 * Kopfzeilen der beiden offenen Seiten.
 *
 * Die Seiten laden nur ihr eigenes Skript, dürfen nirgends eingebettet werden
 * und sollen in keinem Zwischenspeicher landen – auf ihnen steht, wohin ein
 * Fahrzeug gehört oder wozu jemand eingeladen ist.
 */
function seiten_kopf(bool $html = false): void
{
    header('Content-Type: text/' . ($html ? 'html' : 'plain') . '; charset=UTF-8');
    header('Cache-Control: no-store, private');
    header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'unsafe-inline'; "
        . "connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
}

/** Rumpf lesen – mit Deckel, damit niemand den Server vollschreibt */
function koerper_lesen(int $hoechstens = 65536): string
{
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $hoechstens) {
        fehler('Die Anfrage ist zu groß.', 413);
    }
    $roh = (string)file_get_contents('php://input', false, null, 0, $hoechstens + 1);
    if (strlen($roh) > $hoechstens) {
        fehler('Die Anfrage ist zu groß.', 413);
    }
    return $roh;
}

try {
    switch ($p) {
        /* ---------------- Kopplung ---------------- */
        case 'koppeln':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                fehler('Nur POST.', 405);
            }
            $daten = json_decode(koerper_lesen(), true);
            if (!is_array($daten)) {
                fehler('Kein gültiges JSON.');
            }
            antwort(['ok' => true] + con_koppeln(
                (string)($daten['code'] ?? ''),
                (string)($daten['pubkey'] ?? '')
            ));

        /* ---------------- Meldung vom Handy ---------------- */
        case 'position':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                fehler('Nur POST.', 405);
            }
            $daten = json_decode(koerper_lesen(), true);
            if (!is_array($daten)) {
                fehler('Kein gültiges JSON.');
            }
            // Zusätzlich je Absender bremsen, damit niemand fremde Zugänge durchprobiert
            if (!con_limit_frei('ip:' . substr(sha1((string)($_SERVER['REMOTE_ADDR'] ?? '')), 0, 16), 300)) {
                fehler('Zu viele Meldungen von diesem Anschluss. Bitte später erneut.', 429);
            }
            con_meldung_ablegen(nur_text($daten['fz'] ?? ''), nur_text($daten['daten'] ?? ''));
            antwort(['ok' => true]);

        /* ---------------- Von OV-Budget, signiert ---------------- */
        case 'fahrzeuge':
            $daten = con_pruefe_anfrage(koerper_lesen(), (string)($_SERVER['HTTP_X_SIGNATUR'] ?? ''), 'fahrzeuge');
            $anzahl = con_fahrzeuge_setzen((array)($daten['fahrzeuge'] ?? []));
            con_notiz('fahrzeuge.gesetzt', (string)$anzahl);
            antwort(['ok' => true, 'fahrzeuge' => $anzahl]);

        case 'abholen':
            $daten = con_pruefe_anfrage(koerper_lesen(), (string)($_SERVER['HTTP_X_SIGNATUR'] ?? ''), 'abholen');
            con_alte_wegwerfen('meldungen');
            $meldungen = con_meldungen_abholen((int)($daten['max'] ?? 200));
            antwort(['ok' => true, 'meldungen' => $meldungen]);

        /* ---------------- Rückmeldung auf eine Einladung ---------------- */
        case 'rueckmeldung':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                fehler('Nur POST.', 405);
            }
            $daten = json_decode(koerper_lesen(), true);
            if (!is_array($daten)) {
                fehler('Kein gültiges JSON.');
            }
            // Auch hier je Absender bremsen, damit niemand Codes durchprobiert
            if (!con_limit_frei('ip:' . substr(sha1((string)($_SERVER['REMOTE_ADDR'] ?? '')), 0, 16), 300)) {
                fehler('Zu viele Anfragen von diesem Anschluss. Bitte später erneut.', 429);
            }
            con_rueckmeldung_ablegen(nur_text($daten['code'] ?? ''), nur_text($daten['daten'] ?? ''));
            antwort(['ok' => true]);

        /* ---------------- Von OV-Budget, signiert ---------------- */
        case 'veranstaltungen':
            $daten = con_pruefe_anfrage(koerper_lesen(131072),
                (string)($_SERVER['HTTP_X_SIGNATUR'] ?? ''), 'veranstaltungen');
            $res = con_veranstaltungen_setzen(
                (array)($daten['veranstaltungen'] ?? []),
                (array)($daten['einladungen'] ?? [])
            );
            con_notiz('veranstaltungen.gesetzt', $res['veranstaltungen'] . '/' . $res['einladungen']);
            antwort(['ok' => true] + $res);

        case 'rueckmeldungen':
            $daten = con_pruefe_anfrage(koerper_lesen(), (string)($_SERVER['HTTP_X_SIGNATUR'] ?? ''), 'rueckmeldungen');
            con_alte_wegwerfen('rueckmeldungen');
            antwort(['ok' => true, 'rueckmeldungen' => con_rueckmeldungen_abholen((int)($daten['max'] ?? 200))]);

        /* ---------------- Zahlen für OV-Budget ---------------- */
        case 'zustand':
            con_pruefe_anfrage(koerper_lesen(), (string)($_SERVER['HTTP_X_SIGNATUR'] ?? ''), 'zustand');
            antwort(['ok' => true, 'zustand' => con_status()]);

        /* ---------------- Einladungsseite ---------------- */
        case 'einladung':
            $einladung = con_einladung($code);
            // Wer die richtige Adresse hat, darf sie beliebig oft öffnen.
            // Wer daneben greift, wird gebremst – sonst ließen sich Codes durchprobieren.
            if ($einladung === null && !con_fehlgriff()) {
                seiten_kopf();
                http_response_code(429);
                exit("Zu viele Versuche von diesem Anschluss. Bitte später erneut.\n");
            }
            seiten_kopf(true);
            require dirname(__DIR__) . '/src/seite_einladung.php';
            exit;

        /* ---------------- Seite für das Handy ---------------- */
        case 'melden':
            $bekannt = con_fahrzeug($token) !== null;
            if (!$bekannt && !con_fehlgriff()) {
                seiten_kopf();
                http_response_code(429);
                exit("Zu viele Versuche von diesem Anschluss. Bitte später erneut.\n");
            }
            seiten_kopf(true);
            require dirname(__DIR__) . '/src/seite_melden.php';
            exit;

        /* ---------------- Startseite ---------------- */
        default:
            // Hier stehen bewusst keine Zahlen: Wie viele Fahrzeuge oder
            // Einladungen es gibt, geht nur OV-Budget etwas an (?p=zustand).
            $gekoppelt = con_gekoppelt();
            $schreibbar = is_writable(con_dir());
            seiten_kopf(true);
            require dirname(__DIR__) . '/src/seite_start.php';

            // Beim ersten Aufruf den Kopplungscode anlegen
            if (!$gekoppelt) {
                con_kopplungscode();
            }
            exit;
    }
} catch (ConException $ex) {
    fehler($ex->getMessage());
} catch (Throwable $ex) {
    con_notiz('fehler', $ex->getMessage());
    fehler('Unerwarteter Fehler.', 500);
}
