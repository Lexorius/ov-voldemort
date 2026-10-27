<?php
declare(strict_types=1);

/**
 * E-Mail-Versand über einen SMTP-Server – ohne fremde Bibliothek.
 *
 * Das Add-on hat keinen Mailserver und PHPs mail() läuft dort ins Leere.
 * Darum spricht diese Datei SMTP selbst: EHLO, STARTTLS oder SSL, AUTH
 * LOGIN, MAIL FROM, RCPT TO, DATA. Es gibt nur Textnachrichten; mehr
 * braucht kein Bericht.
 *
 * Eingerichtet wird unter Verwaltung → Einstellungen → E-Mail; die
 * Verwaltungsseite „E-Mail" verschickt eine Testnachricht.
 *
 * Prüfungen hängen über $GLOBALS['mail_attrappe'] (callable) dazwischen und
 * fangen die Nachricht ab, statt sie zu verschicken.
 */

class MailException extends RuntimeException
{
}

/** Wie die Verbindung zum Server gesichert ist */
const MAIL_SICHERHEIT = [
    'starttls' => 'STARTTLS – Verbindung wird nach dem Verbinden verschlüsselt (meist Port 587)',
    'ssl'      => 'SSL/TLS – von Anfang an verschlüsselt (meist Port 465)',
    'keine'    => 'ohne Verschlüsselung – nur für einen Server im eigenen Netz',
];

/** Höchstdauer in Sekunden für Verbindung und jede Antwort des Servers */
const MAIL_TIMEOUT = 20;

/* ==================================================================== */
/* Einstellungen                                                         */
/* ==================================================================== */

/** Ist der Versand eingeschaltet und soweit ausgefüllt, dass er gehen kann? */
function mail_eingerichtet(): bool
{
    return setting_bool('mail_aktiv', false)
        && trim((string)setting('mail_host', '')) !== ''
        && mail_adresse_gueltig((string)setting('mail_absender', ''));
}

/** Sieht die Adresse wie eine E-Mail-Adresse aus? Reine Funktion. */
function mail_adresse_gueltig(string $adresse): bool
{
    $adresse = trim($adresse);
    return $adresse !== '' && strlen($adresse) <= 254 && filter_var($adresse, FILTER_VALIDATE_EMAIL) !== false
        && !preg_match('/[\r\n<>]/', $adresse);
}

/** Was auf der Verwaltungsseite steht: Zustand ohne Geheimnisse */
function mail_status(): array
{
    $sicherheit = (string)setting('mail_sicherheit', 'starttls');
    return [
        'aktiv'         => setting_bool('mail_aktiv', false),
        'eingerichtet'  => mail_eingerichtet(),
        'host'          => trim((string)setting('mail_host', '')),
        'port'          => setting_int('mail_port', 587),
        'sicherheit'    => isset(MAIL_SICHERHEIT[$sicherheit]) ? $sicherheit : 'starttls',
        'benutzer'      => trim((string)setting('mail_benutzer', '')),
        'absender'      => trim((string)setting('mail_absender', '')),
        'absender_name' => trim((string)setting('mail_absender_name', 'OV-Multitool')),
        'zuletzt'       => (int)state_get('mail_zuletzt', '0'),
        'letzter_fehler' => state_get('mail_letzter_fehler', ''),
    ];
}

/* ==================================================================== */
/* Nachricht bauen                                                       */
/* ==================================================================== */

/** Kopfzeile nach RFC 2047 kodieren, wenn sie mehr als ASCII enthält. Reine Funktion. */
function mail_kopf_kodieren(string $text): string
{
    $text = trim((string)preg_replace('/[\r\n]+/', ' ', $text));
    if ($text === '' || !preg_match('/[^\x20-\x7e]/', $text)) {
        return $text;
    }
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

/** "Name <adresse>" oder nur die Adresse. Reine Funktion. */
function mail_adresse_mit_name(string $adresse, string $name): string
{
    $name = trim((string)preg_replace('/\s+/', ' ', (string)preg_replace('/[\r\n"<>]+/', ' ', $name)));
    if ($name === '') {
        return $adresse;
    }
    return mail_kopf_kodieren($name) . ' <' . $adresse . '>';
}

/**
 * Die ganze Nachricht mit Kopfzeilen, Zeilenenden CRLF, Text als Base64 –
 * so kommt jede Sprache und jede Zeilenlänge unbeschadet an. Reine Funktion.
 */
function mail_nachricht_bauen(string $von, string $vonName, string $an, string $anName, string $betreff, string $text, ?int $jetzt = null, array $anhaenge = []): string
{
    $jetzt ??= time();
    $host = preg_replace('/[^A-Za-z0-9.-]/', '', substr(strrchr($von, '@') ?: '@ov-multitool', 1)) ?: 'ov-multitool';
    $kopf = [
        'Date: ' . date('r', $jetzt),
        'From: ' . mail_adresse_mit_name($von, $vonName),
        'To: ' . mail_adresse_mit_name($an, $anName),
        'Subject: ' . mail_kopf_kodieren($betreff),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '.' . $jetzt . '@' . $host . '>',
        'MIME-Version: 1.0',
        'X-Mailer: OV-Multitool',
    ];
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $textTeil = chunk_split(base64_encode($text), 76, "\r\n");
    if (!$anhaenge) {
        $kopf[] = 'Content-Type: text/plain; charset=UTF-8';
        $kopf[] = 'Content-Transfer-Encoding: base64';
        return implode("\r\n", $kopf) . "\r\n\r\n" . $textTeil;
    }
    // Mit Anhängen: multipart/mixed – erst der Text, dann jede Datei als Base64
    $grenze = '=_OVB_' . bin2hex(random_bytes(12));
    $kopf[] = 'Content-Type: multipart/mixed; boundary="' . $grenze . '"';
    $teile = "--$grenze\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $textTeil;
    foreach ($anhaenge as $a) {
        $name = trim((string)preg_replace('/[\r\n"\\\\\/]+/', '_', (string)($a['name'] ?? 'anhang')));
        $mime = preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', (string)($a['mime'] ?? '')) ? (string)$a['mime'] : 'application/octet-stream';
        $teile .= "\r\n--$grenze\r\nContent-Type: $mime; name=\"" . mail_kopf_kodieren($name) . "\"\r\n"
            . "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"" . mail_kopf_kodieren($name) . "\"\r\n\r\n"
            . chunk_split(base64_encode((string)($a['inhalt'] ?? '')), 76, "\r\n");
    }
    $teile .= "\r\n--$grenze--\r\n";
    return implode("\r\n", $kopf) . "\r\n\r\n" . $teile;
}

/* ==================================================================== */
/* SMTP                                                                  */
/* ==================================================================== */

/**
 * Eine Textnachricht verschicken, wahlweise mit Anhängen
 * ([['name' => 'x.pdf', 'mime' => 'application/pdf', 'inhalt' => Bytes], …]).
 * Wirft MailException mit einem Grund, den man dem Benutzer zeigen kann.
 */
function mail_senden(string $an, string $betreff, string $text, string $anName = '', array $anhaenge = []): void
{
    if (!mail_adresse_gueltig($an)) {
        throw new MailException('Die Empfängeradresse ist ungültig.');
    }
    if (isset($GLOBALS['mail_attrappe']) && is_callable($GLOBALS['mail_attrappe'])) {
        ($GLOBALS['mail_attrappe'])($an, $betreff, $text, $anName, $anhaenge);
        return;
    }
    if (!mail_eingerichtet()) {
        throw new MailException('Der E-Mail-Versand ist nicht eingerichtet (Verwaltung → Einstellungen → E-Mail).');
    }
    $s = mail_status();
    $nachricht = mail_nachricht_bauen($s['absender'], $s['absender_name'], $an, $anName, $betreff, $text, null, $anhaenge);
    try {
        mail_smtp_senden($s['host'], $s['port'], $s['sicherheit'], $s['benutzer'], (string)setting('mail_passwort', ''),
            $s['absender'], $an, $nachricht);
    } catch (MailException $ex) {
        state_save('mail_letzter_fehler', date('d.m.Y H:i') . ' – ' . mb_substr($ex->getMessage(), 0, 200));
        throw $ex;
    }
    state_save('mail_zuletzt', (string)time());
    state_save('mail_letzter_fehler', '');
    if (function_exists('audit')) {
        audit('mail.gesendet', 'mail', null, mb_substr($betreff, 0, 120));
    }
}

/**
 * Der SMTP-Dialog. Antworten werden auf ihren Code geprüft; was der Server
 * sonst sagt, landet im Fehlertext – ohne Passwort.
 */
function mail_smtp_senden(string $host, int $port, string $sicherheit, string $benutzer, string $passwort, string $von, string $an, string $nachricht): void
{
    $kontext = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
    $adresse = ($sicherheit === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fehlerNr = 0;
    $fehlerText = '';
    $sock = @stream_socket_client($adresse, $fehlerNr, $fehlerText, MAIL_TIMEOUT, STREAM_CLIENT_CONNECT, $kontext);
    if ($sock === false) {
        throw new MailException(sprintf('Keine Verbindung zu %s:%d: %s', $host, $port, $fehlerText !== '' ? $fehlerText : 'Zeitüberschreitung'));
    }
    stream_set_timeout($sock, MAIL_TIMEOUT);

    $lesen = static function () use ($sock): array {
        $code = 0;
        $zeilen = [];
        while (($zeile = fgets($sock, 2048)) !== false) {
            $zeilen[] = rtrim($zeile, "\r\n");
            if (preg_match('/^(\d{3})([ -])/', $zeile, $m)) {
                $code = (int)$m[1];
                if ($m[2] === ' ') {
                    break;
                }
            }
        }
        if ($code === 0) {
            $info = stream_get_meta_data($sock);
            throw new MailException(!empty($info['timed_out']) ? 'Der Server hat nicht geantwortet (Zeitüberschreitung).' : 'Der Server hat die Verbindung beendet.');
        }
        return [$code, implode(' ', $zeilen)];
    };
    $sagen = static function (string $befehl, array $erwartet, string $anzeige = '') use ($sock, $lesen): string {
        fwrite($sock, $befehl . "\r\n");
        [$code, $text] = $lesen();
        if (!in_array($code, $erwartet, true)) {
            throw new MailException(sprintf('%s: Server antwortete „%s"', $anzeige !== '' ? $anzeige : $befehl, mb_substr($text, 0, 200)));
        }
        return $text;
    };

    try {
        [$code, $text] = $lesen();
        if ($code !== 220) {
            throw new MailException('Der Server begrüßt nicht mit 220: ' . mb_substr($text, 0, 200));
        }
        $eigener = 'ov-multitool.local';
        $ehlo = $sagen('EHLO ' . $eigener, [250]);
        if ($sicherheit === 'starttls') {
            if (stripos($ehlo, 'STARTTLS') === false) {
                throw new MailException('Der Server bietet kein STARTTLS an – Sicherheit auf SSL/TLS oder Port prüfen.');
            }
            $sagen('STARTTLS', [220]);
            if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new MailException('Die TLS-Verschlüsselung kam nicht zustande (Zertifikat oder Protokoll).');
            }
            $ehlo = $sagen('EHLO ' . $eigener, [250]);
        }
        if ($benutzer !== '') {
            if (stripos($ehlo, 'AUTH') === false) {
                throw new MailException('Der Server bietet keine Anmeldung an.');
            }
            $sagen('AUTH LOGIN', [334], 'Anmeldung');
            $sagen(base64_encode($benutzer), [334], 'Anmeldung (Benutzer)');
            $sagen(base64_encode($passwort), [235], 'Anmeldung (Passwort)');
        }
        $sagen('MAIL FROM:<' . $von . '>', [250]);
        $sagen('RCPT TO:<' . $an . '>', [250, 251], 'Empfänger');
        $sagen('DATA', [354]);
        // Zeilen, die mit einem Punkt beginnen, bekommen einen zweiten (RFC 5321, 4.5.2)
        $koerper = preg_replace('/^\./m', '..', $nachricht);
        fwrite($sock, $koerper . "\r\n.\r\n");
        [$code, $text] = $lesen();
        if ($code !== 250) {
            throw new MailException('Der Server hat die Nachricht nicht angenommen: ' . mb_substr($text, 0, 200));
        }
        fwrite($sock, "QUIT\r\n");
    } finally {
        fclose($sock);
    }
}
