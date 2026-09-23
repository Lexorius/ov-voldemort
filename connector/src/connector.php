<?php
declare(strict_types=1);

/**
 * OV-Budget-Connector: Briefkasten für alles, was von außen hereinkommt.
 *
 * Der Connector steht auf einem öffentlich erreichbaren Webserver und hat
 * zwei Aufgaben:
 *
 *   Fahrzeuge       – Standortmeldungen von Handys (QR-Code im Fahrzeug)
 *   Veranstaltungen – Rückmeldungen auf Einladungen (kurze Adresse mit Code)
 *
 * Beides nimmt er entgegen und gibt es an OV-Budget weiter – mehr nicht.
 *
 * Zwei Dinge sind wichtig:
 *
 * 1. Er kann die Meldungen nicht lesen. Das Handy verschlüsselt sie im Browser
 *    für den öffentlichen Schlüssel von OV-Budget; hier liegt nur Geheimtext,
 *    und der wird nach dem Abholen gelöscht.
 * 1a. Er kennt die Fahrzeuge nicht. Gespeichert werden nur Prüfsummen der
 *    Zugänge; Bezeichnung und Kennzeichen stehen im Anker der Adresse
 *    (hinter dem #) und erreichen den Server nie.
 * 1b. Von den Veranstaltungen kennt er nur, was die Einladungsseite zeigen
 *    muss: Titel, Zeitpunkt, Ort und den Hinweistext. Wer eingeladen ist,
 *    weiß er nicht – von den Einladungscodes liegen nur Prüfsummen hier,
 *    und die Rückmeldungen sind verschlüsselt.
 * 2. Er redet nur mit einem gekoppelten OV-Budget. Die Kopplung passiert
 *    einmalig mit einem Code, danach ist jede Anfrage signiert.
 *
 * Gespeichert wird in Dateien unterhalb von daten/ – keine Datenbank nötig.
 */

const CON_VERSION = '1.1.3';

/** Höchstalter einer signierten Anfrage in Sekunden (gegen Wiedereinspielen) */
const CON_ZEITFENSTER = 300;
/** Meldungen je Fahrzeug und Stunde */
const CON_LIMIT_STUNDE = 120;
/** Mehr als so viele unabgeholte Meldungen hebt der Connector nicht auf */
const CON_MAX_OFFEN = 500;
/** Größte erlaubte Meldung in Byte */
const CON_MAX_MELDUNG = 4096;
/** Rückmeldungen je Einladung und Stunde */
const CON_LIMIT_EINLADUNG = 20;
/** Fehlgriffe je Anschluss und Stunde (Zugänge oder Codes durchprobieren) */
const CON_LIMIT_FEHLGRIFF = 60;
/** So lange hebt der Connector Unabgeholtes auf, dann wirft er es weg (Tage) */
const CON_AUFHEBEN_TAGE = 30;
/** Größte erlaubte Rückmeldung in Byte */
const CON_MAX_RUECKMELDUNG = 8192;

class ConException extends RuntimeException
{
}

/** Läuft die Anfrage über https? Hinter einem Proxy zählt X-Forwarded-Proto. */
function con_https(): bool
{
    if (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    // Für lokale Versuche
    return in_array((string)($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1'], true);
}

/* ==================================================================== */
/* Ablage                                                                */
/* ==================================================================== */

function con_dir(string $unter = ''): string
{
    $basis = dirname(__DIR__) . '/daten';
    if (!is_dir($basis)) {
        @mkdir($basis, 0770, true);
    }
    // Falls das Wurzelverzeichnis des Webservers doch auf den Ordner darüber zeigt.
    // Auch nachträglich: Ein von Hand angelegter Ordner wäre sonst ungeschützt.
    if (!is_file($basis . '/.htaccess')) {
        @file_put_contents($basis . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    if (!is_file($basis . '/index.html')) {
        @file_put_contents($basis . '/index.html', '');
    }
    $pfad = $unter === '' ? $basis : $basis . '/' . trim($unter, '/');
    if ($unter !== '' && !is_dir($pfad)) {
        @mkdir($pfad, 0770, true);
    }
    return $pfad;
}

function con_lesen(string $datei, array $vorgabe = []): array
{
    $roh = @file_get_contents(con_dir() . '/' . $datei);
    $daten = is_string($roh) ? json_decode($roh, true) : null;
    return is_array($daten) ? $daten : $vorgabe;
}

/**
 * Lesen, ändern, schreiben – unter einer Sperre.
 * Ohne das könnten zwei gleichzeitige Meldungen denselben Zähler
 * überschreiben und so die Begrenzung aushebeln.
 */
function con_aendern(string $datei, callable $aendern): mixed
{
    $sperre = fopen(con_dir() . '/' . $datei . '.lock', 'c');
    if ($sperre === false) {
        throw new ConException('Die Ablage ist nicht beschreibbar.');
    }
    try {
        flock($sperre, LOCK_EX);
        $daten = con_lesen($datei);
        $ergebnis = $aendern($daten);
        con_schreiben($datei, $daten);
        return $ergebnis;
    } finally {
        flock($sperre, LOCK_UN);
        fclose($sperre);
    }
}

/** Schreiben mit Sperre, damit sich gleichzeitige Meldungen nicht überholen */
function con_schreiben(string $datei, array $daten): void
{
    $pfad = con_dir() . '/' . $datei;
    $tmp = $pfad . '.tmp' . bin2hex(random_bytes(4));
    file_put_contents($tmp, json_encode($daten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    @chmod($tmp, 0660);
    rename($tmp, $pfad);
}

/* ==================================================================== */
/* Base64url und Schlüssel                                               */
/* ==================================================================== */

function con_b64u(string $daten): string
{
    return rtrim(strtr(base64_encode($daten), '+/', '-_'), '=');
}

function con_unb64u(string $text): string
{
    $text = rtrim(strtr(trim($text), '-_', '+/'), '=');
    $rest = strlen($text) % 4;
    if ($rest > 0) {
        $text .= str_repeat('=', 4 - $rest);
    }
    $roh = base64_decode($text, true);
    if ($roh === false) {
        throw new ConException('Ungültige Base64url-Zeichenkette.');
    }
    return $roh;
}

/** Öffentlichen P-256-Punkt (65 Byte) als PEM verpacken. Reine Funktion. */
function con_pubkey_pem(string $punkt): string
{
    if (strlen($punkt) !== 65 || $punkt[0] !== "\x04") {
        throw new ConException('Der öffentliche Schlüssel hat nicht die erwarteten 65 Byte.');
    }
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $punkt;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/** Neues Schlüsselpaar: [PEM privat, öffentlicher Punkt (65 Byte)] */
function con_keypair(): array
{
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$key) {
        throw new ConException('Schlüsselpaar konnte nicht erzeugt werden: ' . openssl_error_string());
    }
    openssl_pkey_export($key, $pem);
    $d = openssl_pkey_get_details($key);
    $punkt = "\x04" . str_pad((string)$d['ec']['x'], 32, "\x00", STR_PAD_LEFT)
                    . str_pad((string)$d['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    return [(string)$pem, $punkt];
}

/* ==================================================================== */
/* Kopplung                                                              */
/* ==================================================================== */

function con_kopplung(): array
{
    return con_lesen('kopplung.json');
}

function con_gekoppelt(): bool
{
    $k = con_kopplung();
    return trim((string)($k['ov_pubkey'] ?? '')) !== '';
}

/**
 * Beim ersten Aufruf einen Kopplungscode erzeugen und in eine Datei legen.
 * Er steht bewusst nicht auf einer Webseite – wer koppeln will, braucht
 * Zugriff auf den Server.
 */
function con_kopplungscode(): string
{
    $pfad = con_dir() . '/kopplungscode.txt';
    $vorhanden = is_file($pfad) ? trim((string)file_get_contents($pfad)) : '';
    if ($vorhanden !== '') {
        return $vorhanden;
    }
    $code = strtoupper(bin2hex(random_bytes(9)));
    $code = implode('-', str_split($code, 6));   // z. B. A1B2C3-D4E5F6-071829
    file_put_contents($pfad, $code . "\n");
    @chmod($pfad, 0640);
    return $code;
}

/**
 * Kopplung durchführen: OV-Budget schickt Code und seinen öffentlichen
 * Schlüssel, bekommt unseren zurück. Danach ist der Code verbraucht.
 */
function con_koppeln(string $code, string $ovPubkey): array
{
    if (con_gekoppelt()) {
        throw new ConException('Dieser Connector ist bereits gekoppelt. Zum Neukoppeln die Datei '
            . 'daten/kopplung.json auf dem Server löschen.');
    }
    if (!con_limit_frei('kopplung', 10)) {
        con_notiz('kopplung.gesperrt', '');
        throw new ConException('Zu viele Fehlversuche. Bitte eine Stunde warten.');
    }
    $erwartet = con_kopplungscode();
    if (!hash_equals($erwartet, strtoupper(trim($code)))) {
        con_notiz('kopplung.fehlversuch', '');
        usleep(300000);   // bremst Rateversuche zusätzlich
        throw new ConException('Der Kopplungscode stimmt nicht.');
    }
    $punkt = con_unb64u($ovPubkey);
    con_pubkey_pem($punkt);   // wirft, wenn er nicht taugt

    [$pem, $eigenerPunkt] = con_keypair();
    con_schreiben('kopplung.json', [
        'ov_pubkey'     => con_b64u($punkt),
        'eigener_pem'   => $pem,
        'eigener_punkt' => con_b64u($eigenerPunkt),
        'gekoppelt_am'  => date('c'),
    ]);
    @unlink(con_dir() . '/kopplungscode.txt');
    con_notiz('kopplung.ok', '');

    return ['pubkey' => con_b64u($eigenerPunkt), 'version' => CON_VERSION];
}

/* ==================================================================== */
/* Signaturen                                                            */
/* ==================================================================== */

/**
 * Anfrage von OV-Budget prüfen: Signatur, Alter, Einmaligkeit.
 * Gibt den entschlüsselten Rumpf als Array zurück.
 */
function con_pruefe_anfrage(string $koerper, string $signatur, string $pfad = ''): array
{
    $k = con_kopplung();
    if (trim((string)($k['ov_pubkey'] ?? '')) === '') {
        throw new ConException('Dieser Connector ist noch nicht gekoppelt.');
    }
    $pem = con_pubkey_pem(con_unb64u((string)$k['ov_pubkey']));
    $ok = openssl_verify($koerper, con_unb64u($signatur), $pem, OPENSSL_ALGO_SHA256);
    if ($ok !== 1) {
        throw new ConException('Die Signatur stimmt nicht.');
    }

    $daten = json_decode($koerper, true);
    if (!is_array($daten)) {
        throw new ConException('Die Anfrage war kein gültiges JSON.');
    }
    $ts = (int)($daten['ts'] ?? 0);
    if (abs(time() - $ts) > CON_ZEITFENSTER) {
        throw new ConException('Die Anfrage ist zu alt oder die Uhren laufen auseinander.');
    }
    $nonce = (string)($daten['nonce'] ?? '');
    if ($nonce === '' || !con_nonce_neu($nonce, $ts)) {
        throw new ConException('Diese Anfrage gab es schon.');
    }
    // Sonst ließe sich eine mitgeschnittene Anfrage an einem anderen Zweck verwenden
    if ($pfad !== '' && (string)($daten['zweck'] ?? '') !== $pfad) {
        throw new ConException('Die Anfrage gehört zu einem anderen Zweck.');
    }
    return $daten;
}

/** Antwort signieren, damit OV-Budget sicher ist, mit wem es spricht */
function con_signiere(string $koerper): string
{
    $k = con_kopplung();
    $pem = (string)($k['eigener_pem'] ?? '');
    if ($pem === '' || !openssl_sign($koerper, $sig, openssl_pkey_get_private($pem), OPENSSL_ALGO_SHA256)) {
        return '';
    }
    return con_b64u($sig);
}

/** Einmalkennungen merken, alte vergessen */
function con_nonce_neu(string $nonce, int $ts): bool
{
    if (!preg_match('/^[a-f0-9]{16,64}$/i', $nonce)) {
        return false;
    }
    return (bool)con_aendern('nonces.json', static function (array &$alle) use ($nonce, $ts): bool {
        $grenze = time() - CON_ZEITFENSTER * 2;
        $alle = array_filter($alle, static fn($t) => (int)$t >= $grenze);
        if (isset($alle[$nonce])) {
            return false;
        }
        $alle[$nonce] = $ts;
        return true;
    });
}

/**
 * Ein Fehlgriff: jemand hat einen Zugang oder einen Einladungscode versucht,
 * den es nicht gibt. Ein paar davon sind normal (alter QR-Code, vertippt) –
 * viele heißen: Hier probiert jemand durch.
 *
 * Gezählt wird je Anschluss, und nur die Fehlgriffe: Wer die richtige Adresse
 * hat, darf die Seite so oft öffnen, wie er mag.
 */
function con_fehlgriff(): bool
{
    return con_limit_frei('f:' . substr(sha1((string)($_SERVER['REMOTE_ADDR'] ?? '')), 0, 16),
        CON_LIMIT_FEHLGRIFF);
}

/* ==================================================================== */
/* Fahrzeuge                                                             */
/* ==================================================================== */

/**
 * Wie ein Zugang in der Ablage steht: als Prüfsumme, nie im Klartext.
 * Wer die Datei erbeutet, kann damit keine Meldungen abgeben – aus der
 * Prüfsumme lässt sich der Zugang nicht zurückrechnen. Reine Funktion.
 */
function con_kennung(string $token): string
{
    return hash('sha256', 'ovb-zugang:' . $token);
}

/**
 * Die Liste kommt komplett von OV-Budget und ersetzt die bisherige.
 * Übertragen werden nur Prüfsummen – Bezeichnung und Kennzeichen der
 * Fahrzeuge bleiben in OV-Budget und stehen für die Melde-Seite im
 * Adressanker, der den Server nie erreicht.
 */
function con_fahrzeuge_setzen(array $liste): int
{
    $neu = [];
    foreach ($liste as $fz) {
        $kennung = strtolower(trim((string)(is_array($fz) ? ($fz['kennung'] ?? '') : $fz)));
        if (preg_match('/^[a-f0-9]{64}$/', $kennung)) {
            $neu[$kennung] = true;
        }
    }
    con_schreiben('fahrzeuge.json', $neu);

    // Meldungen zurückgezogener Fahrzeuge wegwerfen
    foreach (glob(con_dir('meldungen') . '/*') ?: [] as $ordner) {
        if (!isset($neu[basename($ordner)])) {
            con_ordner_leeren($ordner);
            @rmdir($ordner);
        }
    }
    return count($neu);
}

/** Sieht der Zugang überhaupt wie einer aus? Reine Funktion. */
function con_token_gueltig(string $token): bool
{
    return (bool)preg_match('/^[A-Za-z0-9_-]{20,80}$/', $token);
}

/** Ist dieser Zugang angemeldet? Rückgabe: seine Kennung oder null. */
function con_fahrzeug(string $token): ?string
{
    if (!con_token_gueltig($token)) {
        return null;
    }
    $kennung = con_kennung($token);
    return isset(con_lesen('fahrzeuge.json')[$kennung]) ? $kennung : null;
}

/* ==================================================================== */
/* Meldungen                                                             */
/* ==================================================================== */

/**
 * Meldung eines Handys ablegen. $inhalt ist bereits verschlüsselt und wird
 * hier nur weggeschrieben – der Connector sieht keine Koordinaten.
 */
function con_meldung_ablegen(string $token, string $inhalt): void
{
    $kennung = con_fahrzeug($token);
    if ($kennung === null) {
        throw new ConException('Unbekannter Zugang. Vermutlich wurde der QR-Code zurückgezogen.');
    }
    if ($inhalt === '' || strlen($inhalt) > CON_MAX_MELDUNG) {
        throw new ConException('Die Meldung hat eine unerwartete Größe.');
    }
    if (!con_limit_frei($kennung)) {
        throw new ConException('Für dieses Fahrzeug kamen gerade sehr viele Meldungen. Bitte später erneut.');
    }

    $ordner = con_dir('meldungen/' . $kennung);
    $offen = glob($ordner . '/*.json') ?: [];
    if (count($offen) >= CON_MAX_OFFEN) {
        sort($offen);
        @unlink($offen[0]);   // ältesten wegwerfen, sonst läuft die Platte voll
    }
    $name = sprintf('%d-%s.json', time(), bin2hex(random_bytes(5)));
    file_put_contents($ordner . '/' . $name, json_encode([
        'ts'    => time(),
        'daten' => $inhalt,
    ], JSON_UNESCAPED_SLASHES));
}

/** Meldungen abholen und dabei löschen */
function con_meldungen_abholen(int $max = 200): array
{
    $out = [];
    foreach (glob(con_dir('meldungen') . '/*') ?: [] as $ordner) {
        $kennung = basename($ordner);
        foreach (glob($ordner . '/*.json') ?: [] as $datei) {
            if (count($out) >= $max) {
                break 2;
            }
            $roh = json_decode((string)@file_get_contents($datei), true);
            @unlink($datei);
            if (is_array($roh) && isset($roh['daten'])) {
                $out[] = ['fz' => $kennung, 'ts' => (int)($roh['ts'] ?? 0), 'daten' => (string)$roh['daten']];
            }
        }
    }
    return $out;
}

/**
 * Uraltes Unabgeholtes wegwerfen. Holt OV-Budget längere Zeit nichts ab
 * (abgeschaltet, umgezogen), soll das hier nicht ewig liegen bleiben.
 */
function con_alte_wegwerfen(string $unter): void
{
    $grenze = time() - CON_AUFHEBEN_TAGE * 86400;
    foreach (glob(con_dir($unter) . '/*/*.json') ?: [] as $datei) {
        if ((int)@filemtime($datei) < $grenze) {
            @unlink($datei);
        }
    }
}

/** Wie viele Meldungen kamen in der letzten Stunde? Begrenzt Missbrauch. */
function con_limit_frei(string $schluessel, int $hoechstens = CON_LIMIT_STUNDE): bool
{
    return (bool)con_aendern('limit.json', static function (array &$alle) use ($schluessel, $hoechstens): bool {
        $jetzt = time();
        // Abgelaufene Zeitfenster wegwerfen, damit die Datei nicht wächst
        foreach ($alle as $k => $e) {
            if ($jetzt - (int)($e['start'] ?? 0) >= 7200) {
                unset($alle[$k]);
            }
        }
        $eintrag = $alle[$schluessel] ?? ['start' => $jetzt, 'anzahl' => 0];
        if ($jetzt - (int)$eintrag['start'] >= 3600) {
            $eintrag = ['start' => $jetzt, 'anzahl' => 0];
        }
        if ((int)$eintrag['anzahl'] >= $hoechstens) {
            $alle[$schluessel] = $eintrag;
            return false;
        }
        $eintrag['anzahl'] = (int)$eintrag['anzahl'] + 1;
        $alle[$schluessel] = $eintrag;
        return true;
    });
}

/* ==================================================================== */
/* Veranstaltungen und Einladungen                                       */
/* ==================================================================== */

/**
 * Was der Connector über eine Veranstaltung wissen darf. Titel, Zeitpunkt
 * und Ort muss die Einladungsseite zeigen – alles andere bleibt in
 * OV-Budget. Reine Funktion.
 */
function con_veranstaltung_saeubern(array $v): ?array
{
    $kennung = strtolower(trim((string)($v['kennung'] ?? '')));
    if (!preg_match('/^[a-f0-9]{64}$/', $kennung)) {
        return null;
    }
    $text = static fn(string $wert, int $laenge): string => mb_substr(
        trim(preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f]/u', '', $wert) ?? ''), 0, $laenge);

    return [
        'kennung'       => $kennung,
        'titel'         => $text((string)($v['titel'] ?? ''), 200),
        'beginn'        => $text((string)($v['beginn'] ?? ''), 30),
        'ende'          => $text((string)($v['ende'] ?? ''), 30),
        'ort'           => $text((string)($v['ort'] ?? ''), 200),
        'hinweis'       => $text((string)($v['hinweis'] ?? ''), 2000),
        'bis'           => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($v['bis'] ?? ''))
            ? (string)$v['bis'] : '',
        'status'        => in_array((string)($v['status'] ?? ''), ['geplant', 'laeuft', 'abgesagt', 'abgeschlossen'], true)
            ? (string)$v['status'] : 'geplant',
        'begleiter_max' => max(0, min(50, (int)($v['begleiter_max'] ?? 0))),
        'kommentare'    => !empty($v['kommentare']) ? 1 : 0,
        'vertretung'    => !empty($v['vertretung']) ? 1 : 0,
    ];
}

/**
 * Veranstaltungen und Einladungen setzen – beide Listen kommen komplett von
 * OV-Budget und ersetzen die bisherigen. Von den Codes kommen nur Prüfsummen.
 */
function con_veranstaltungen_setzen(array $veranstaltungen, array $einladungen): array
{
    $neu = [];
    foreach ($veranstaltungen as $v) {
        $sauber = is_array($v) ? con_veranstaltung_saeubern($v) : null;
        if ($sauber !== null) {
            $neu[$sauber['kennung']] = $sauber;
        }
    }
    $codes = [];
    foreach ($einladungen as $code => $veranstaltung) {
        $code = strtolower(trim((string)$code));
        $veranstaltung = strtolower(trim((string)$veranstaltung));
        if (preg_match('/^[a-f0-9]{64}$/', $code) && isset($neu[$veranstaltung])) {
            $codes[$code] = $veranstaltung;
        }
    }
    con_schreiben('veranstaltungen.json', $neu);
    con_schreiben('einladungen.json', $codes);

    // Rückmeldungen zurückgezogener Einladungen wegwerfen
    foreach (glob(con_dir('rueckmeldungen') . '/*') ?: [] as $ordner) {
        if (!isset($codes[basename($ordner)])) {
            con_ordner_leeren($ordner);
            @rmdir($ordner);
        }
    }
    return ['veranstaltungen' => count($neu), 'einladungen' => count($codes)];
}

/** So steht ein Einladungscode in der Ablage: als Prüfsumme. Reine Funktion. */
function con_code_kennung(string $code): string
{
    return hash('sha256', 'ovb-einladung:' . strtoupper(trim($code)));
}

/** Sieht die Zeichenfolge wie ein Einladungscode aus? Reine Funktion. */
function con_code_gueltig(string $code): bool
{
    return (bool)preg_match('/^[0-9A-Za-z]{4,16}$/', trim($code));
}

/**
 * Zu einem Code die Veranstaltung suchen.
 * Rückgabe: ['kennung' => Prüfsumme des Codes, 'veranstaltung' => Angaben]
 * oder null, wenn der Code nicht (mehr) gilt.
 */
function con_einladung(string $code): ?array
{
    if (!con_code_gueltig($code)) {
        return null;
    }
    $kennung = con_code_kennung($code);
    $ziel = con_lesen('einladungen.json')[$kennung] ?? null;
    if (!is_string($ziel)) {
        return null;
    }
    $v = con_lesen('veranstaltungen.json')[$ziel] ?? null;
    return is_array($v) ? ['kennung' => $kennung, 'veranstaltung' => $v] : null;
}

/** Ist die Rückmeldefrist abgelaufen? Reine Funktion. */
function con_frist_vorbei(array $veranstaltung, ?string $heute = null): bool
{
    $bis = (string)($veranstaltung['bis'] ?? '');
    if ($bis === '') {
        return false;
    }
    return ($heute ?? date('Y-m-d')) > $bis;
}

/**
 * Rückmeldung ablegen. $inhalt ist bereits verschlüsselt – der Connector
 * erfährt weder Namen noch Antwort.
 */
function con_rueckmeldung_ablegen(string $code, string $inhalt): void
{
    $einladung = con_einladung($code);
    if ($einladung === null) {
        throw new ConException('Diese Einladung gilt nicht (mehr).');
    }
    if (con_frist_vorbei($einladung['veranstaltung'])) {
        throw new ConException('Die Rückmeldefrist ist abgelaufen.');
    }
    if ($inhalt === '' || strlen($inhalt) > CON_MAX_RUECKMELDUNG) {
        throw new ConException('Die Rückmeldung hat eine unerwartete Größe.');
    }
    if (!con_limit_frei('e:' . $einladung['kennung'], CON_LIMIT_EINLADUNG)) {
        throw new ConException('Für diese Einladung kamen gerade sehr viele Rückmeldungen. Bitte später erneut.');
    }

    $ordner = con_dir('rueckmeldungen/' . $einladung['kennung']);
    $offen = glob($ordner . '/*.json') ?: [];
    if (count($offen) >= CON_MAX_OFFEN) {
        sort($offen);
        @unlink($offen[0]);
    }
    $name = sprintf('%d-%s.json', time(), bin2hex(random_bytes(5)));
    file_put_contents($ordner . '/' . $name, json_encode([
        'ts'    => time(),
        'daten' => $inhalt,
    ], JSON_UNESCAPED_SLASHES));
}

/** Rückmeldungen abholen und dabei löschen */
function con_rueckmeldungen_abholen(int $max = 200): array
{
    $out = [];
    foreach (glob(con_dir('rueckmeldungen') . '/*') ?: [] as $ordner) {
        $kennung = basename($ordner);
        foreach (glob($ordner . '/*.json') ?: [] as $datei) {
            if (count($out) >= $max) {
                break 2;
            }
            $roh = json_decode((string)@file_get_contents($datei), true);
            @unlink($datei);
            if (is_array($roh) && isset($roh['daten'])) {
                $out[] = ['code' => $kennung, 'ts' => (int)($roh['ts'] ?? 0), 'daten' => (string)$roh['daten']];
            }
        }
    }
    return $out;
}

/* ==================================================================== */
/* Kleinkram                                                             */
/* ==================================================================== */

function con_ordner_leeren(string $ordner): void
{
    foreach (glob($ordner . '/*') ?: [] as $f) {
        @unlink($f);
    }
}

/** Knappes Protokoll – ohne Inhalte, nur was passiert ist */
function con_notiz(string $was, string $dazu = ''): void
{
    // Steuerzeichen raus: Sonst ließen sich über eine Fehlermeldung eigene
    // Zeilen ins Protokoll schreiben
    $sauber = static fn(string $t): string => mb_substr(
        (string)preg_replace('/[\x00-\x1f\x7f]/u', ' ', $t), 0, 120);
    $zeile = sprintf("%s\t%s\t%s\n", date('c'), $sauber($was), $sauber($dazu));
    @file_put_contents(con_dir() . '/protokoll.log', $zeile, FILE_APPEND);
}

/**
 * Zustand des Connectors – Zahlen, keine Geheimnisse.
 *
 * Das steht bewusst auf keiner Webseite: Wie viele Fahrzeuge oder Einladungen
 * hier hängen, geht nur das gekoppelte OV-Budget etwas an. Abgefragt wird es
 * signiert über ?p=zustand.
 */
function con_status(): array
{
    $zaehlen = static function (string $unter): int {
        $n = 0;
        foreach (glob(con_dir($unter) . '/*') ?: [] as $ordner) {
            $n += count(glob($ordner . '/*.json') ?: []);
        }
        return $n;
    };
    $k = con_kopplung();
    return [
        'version'         => CON_VERSION,
        'php'             => PHP_VERSION,
        'gekoppelt'       => con_gekoppelt(),
        'seit'            => (string)($k['gekoppelt_am'] ?? ''),
        'fahrzeuge'       => count(con_lesen('fahrzeuge.json')),
        'veranstaltungen' => count(con_lesen('veranstaltungen.json')),
        'einladungen'     => count(con_lesen('einladungen.json')),
        'meldungen'       => $zaehlen('meldungen'),
        'rueckmeldungen'  => $zaehlen('rueckmeldungen'),
        'schreibbar'      => is_writable(con_dir()),
        'frei'            => (int)max(0, (float)@disk_free_space(con_dir())),
    ];
}
