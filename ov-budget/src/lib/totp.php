<?php
declare(strict_types=1);

/*
 * Zweiter Faktor: zeitbasierte Einmalcodes (TOTP, RFC 6238) aus einer
 * Authenticator-App, dazu Backup-Codes für den Fall, dass das Handy fehlt.
 * Ohne fremde Bibliothek – HMAC-SHA1 über den 30-Sekunden-Schritt.
 */

/** Für wen der zweite Faktor Pflicht ist – Einstellung totp_pflicht */
const TOTP_PFLICHT = [
    'keine'   => 'freiwillig für alle',
    'admin'   => 'Pflicht für die Administration',
    'leitung' => 'Pflicht für Administration und Leitung',
    'alle'    => 'Pflicht für alle Benutzer',
];
const TOTP_SCHRITT = 30;
const TOTP_STELLEN = 6;
/** Je ein Schritt vor und zurück, damit eine Uhr, die 30 s abweicht, noch geht */
const TOTP_FENSTER = 1;
const TOTP_BACKUP_ANZAHL = 8;
/** Wie lange die halbe Anmeldung (Passwort ja, Code noch nicht) offen bleibt */
const TOTP_ANMELDUNG_SEKUNDEN = 300;
const TOTP_BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
/** Zeichen der Backup-Codes – ohne 0/O, 1/l/I */
const TOTP_BACKUP_ZEICHEN = 'abcdefghjkmnpqrstuvwxyz23456789';

/* ==================================================================== */
/* Reine Funktionen                                                      */
/* ==================================================================== */

function totp_base32_encode(string $bin): string
{
    $bits = '';
    foreach (str_split($bin) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $stueck) {
        $out .= TOTP_BASE32[bindec(str_pad($stueck, 5, '0', STR_PAD_RIGHT))];
    }
    return $out;
}

/** Leer, wenn ein Zeichen nicht ins Alphabet passt. Groß/klein und Leerzeichen sind egal. */
function totp_base32_decode(string $s): string
{
    $s = strtoupper(preg_replace('/[\s=-]+/', '', $s) ?? '');
    if ($s === '' || preg_match('/[^A-Z2-7]/', $s)) {
        return '';
    }
    $bits = '';
    foreach (str_split($s) as $c) {
        $bits .= str_pad(decbin(strpos(TOTP_BASE32, $c)), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

/** 160 Bit Zufall als 32 Zeichen Base32 – das, was die App einliest */
function totp_secret_neu(): string
{
    return totp_base32_encode(random_bytes(20));
}

/** Der Code zu einem Zeitpunkt. */
function totp_code(string $secret, int $zeit, int $schritt = TOTP_SCHRITT, int $stellen = TOTP_STELLEN): string
{
    $key = totp_base32_decode($secret);
    $zaehler = intdiv($zeit, $schritt);
    $h = hash_hmac('sha1', pack('J', $zaehler), $key, true);
    $off = ord($h[19]) & 0x0f;
    $bin = ((ord($h[$off]) & 0x7f) << 24) | (ord($h[$off + 1]) << 16) | (ord($h[$off + 2]) << 8) | ord($h[$off + 3]);
    return str_pad((string)($bin % (10 ** $stellen)), $stellen, '0', STR_PAD_LEFT);
}

/**
 * Passt der Code? Liefert den getroffenen Zeitschritt oder null. Schritte
 * bis einschließlich $letzter gelten als verbraucht – ein abgefangener
 * Code lässt sich so nicht ein zweites Mal verwenden.
 */
function totp_pruefen(string $secret, string $eingabe, int $jetzt, int $letzter = 0, int $fenster = TOTP_FENSTER): ?int
{
    $code = preg_replace('/\D/', '', $eingabe) ?? '';
    if (strlen($code) !== TOTP_STELLEN || totp_base32_decode($secret) === '') {
        return null;
    }
    $mitte = intdiv($jetzt, TOTP_SCHRITT);
    for ($d = -$fenster; $d <= $fenster; $d++) {
        $schritt = $mitte + $d;
        if ($schritt <= $letzter) {
            continue;
        }
        if (hash_equals(totp_code($secret, $schritt * TOTP_SCHRITT), $code)) {
            return $schritt;
        }
    }
    return null;
}

/** Die Adresse für den QR-Code, den die Authenticator-App einliest. */
function totp_uri(string $secret, string $konto, string $aussteller): string
{
    $aussteller = trim(str_replace(':', ' ', $aussteller)) ?: 'OV-Multitool';
    return 'otpauth://totp/' . rawurlencode($aussteller) . ':' . rawurlencode($konto)
        . '?secret=' . $secret . '&issuer=' . rawurlencode($aussteller)
        . '&algorithm=SHA1&digits=' . TOTP_STELLEN . '&period=' . TOTP_SCHRITT;
}

/** Das Geheimnis in Vierergruppen, zum Abtippen ohne QR-Code */
function totp_secret_lesbar(string $secret): string
{
    return trim(chunk_split($secret, 4, ' '));
}

/** Acht Backup-Codes der Form abcd-2345 im Klartext */
function totp_backup_neu(int $anzahl = TOTP_BACKUP_ANZAHL): array
{
    $codes = [];
    $n = strlen(TOTP_BACKUP_ZEICHEN);
    for ($i = 0; $i < $anzahl; $i++) {
        $c = '';
        for ($j = 0; $j < 8; $j++) {
            $c .= TOTP_BACKUP_ZEICHEN[random_int(0, $n - 1)];
        }
        $codes[] = substr($c, 0, 4) . '-' . substr($c, 4);
    }
    return $codes;
}

function totp_backup_normalisieren(string $code): string
{
    return preg_replace('/[^a-z0-9]/', '', strtolower($code)) ?? '';
}

/** Gespeichert wird nur der Hash, gesalzen mit dem Geheimnis des Kontos */
function totp_backup_hash(string $secret, string $code): string
{
    return hash('sha256', $secret . '|' . totp_backup_normalisieren($code));
}

function totp_backup_hashes(string $secret, array $codes): array
{
    return array_map(static fn($c) => totp_backup_hash($secret, $c), $codes);
}

/** Passt der Code zu einem der Hashes, kommen die übrigen zurück – sonst null. */
function totp_backup_einloesen(array $hashes, string $secret, string $code): ?array
{
    if (strlen(totp_backup_normalisieren($code)) !== 8) {
        return null;
    }
    $h = totp_backup_hash($secret, $code);
    $rest = [];
    $treffer = false;
    foreach ($hashes as $x) {
        if (!$treffer && hash_equals((string)$x, $h)) {
            $treffer = true;
            continue;
        }
        $rest[] = $x;
    }
    return $treffer ? $rest : null;
}

/** Ist der zweite Faktor für diese Rolle Pflicht? */
function totp_pflichtig(array $user, ?string $regel = null): bool
{
    $regel ??= (string)setting('totp_pflicht', 'leitung');
    $rolle = (string)($user['role'] ?? 'user');
    return match ($regel) {
        'alle'    => true,
        'leitung' => in_array($rolle, ['admin', 'leitung'], true),
        'admin'   => $rolle === 'admin',
        default   => false,
    };
}

function totp_aktiv(array $user): bool
{
    return (int)($user['totp_aktiv'] ?? 0) === 1 && (string)($user['totp_secret'] ?? '') !== '';
}

/** Was das Profil und die Benutzerliste zeigen */
function totp_status(array $user): array
{
    $hashes = json_decode((string)($user['totp_backup'] ?? ''), true);
    return [
        'aktiv'       => totp_aktiv($user),
        'seit'        => $user['totp_seit'] ?? null,
        'backup_rest' => is_array($hashes) ? count($hashes) : 0,
        'pflicht'     => totp_pflichtig($user),
    ];
}

/* ==================================================================== */
/* Mit Datenbank                                                         */
/* ==================================================================== */

/** Einschalten: Geheimnis speichern, Backup-Codes erzeugen und im Klartext zurückgeben */
function totp_aktivieren(int $userId, string $secret): array
{
    $codes = totp_backup_neu();
    db_update('users', [
        'totp_secret'  => $secret,
        'totp_aktiv'   => 1,
        'totp_seit'    => date('Y-m-d H:i:s'),
        'totp_letzter' => intdiv(time(), TOTP_SCHRITT),   // der Einrichtungscode gilt nicht noch einmal
        'totp_backup'  => json_encode(totp_backup_hashes($secret, $codes)),
    ], 'id = ?', [$userId]);
    return $codes;
}

function totp_backup_erneuern(int $userId, string $secret): array
{
    $codes = totp_backup_neu();
    db_update('users', ['totp_backup' => json_encode(totp_backup_hashes($secret, $codes))], 'id = ?', [$userId]);
    return $codes;
}

function totp_abschalten(int $userId): void
{
    db_update('users', ['totp_secret' => '', 'totp_aktiv' => 0, 'totp_seit' => null, 'totp_letzter' => 0, 'totp_backup' => null],
        'id = ?', [$userId]);
}

/**
 * Prüft die Eingabe bei der Anmeldung: erst als App-Code, dann als
 * Backup-Code. Liefert 'app', 'backup' oder null und verbucht den Verbrauch.
 */
function totp_zweiter_faktor_pruefen(array $user, string $eingabe, ?int $jetzt = null): ?string
{
    $jetzt ??= time();
    $secret = (string)($user['totp_secret'] ?? '');
    $schritt = totp_pruefen($secret, $eingabe, $jetzt, (int)($user['totp_letzter'] ?? 0));
    if ($schritt !== null) {
        db_update('users', ['totp_letzter' => $schritt], 'id = ?', [(int)$user['id']]);
        return 'app';
    }
    $hashes = json_decode((string)($user['totp_backup'] ?? ''), true);
    $rest = is_array($hashes) ? totp_backup_einloesen($hashes, $secret, $eingabe) : null;
    if ($rest !== null) {
        db_update('users', ['totp_backup' => json_encode(array_values($rest))], 'id = ?', [(int)$user['id']]);
        return 'backup';
    }
    return null;
}
