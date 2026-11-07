<?php
declare(strict_types=1);

/**
 * Kalender-Abo (ICS): Termine, Veranstaltungen, Besprechungen und Fristen
 * als iCalendar-Feed für Home Assistant („Remote Calendar"), Outlook,
 * Google, Thunderbird und das Handy.
 *
 * Sicherheit, weil die Adresse ohne Anmeldung erreichbar ist:
 *  - Der Feed läuft nur mit einem zufälligen Token (32 Byte, hex), das allein
 *    die Verwaltung erzeugt und jederzeit zurückzieht. Ohne Token ist der
 *    Feed aus. Verglichen wird in konstanter Zeit.
 *  - Fehlversuche je IP-Adresse zählen gegen eine Sperre wie bei der
 *    Anmeldung; Antworten auf falsche oder fehlende Token sind gleich.
 *  - Es geht nur hinaus, was für den ganzen OV oder eine Fachgruppe gilt:
 *    persönliche Termine (Ziel Person) nie, Beschreibungen nur, wenn die
 *    Verwaltung es einschaltet. Keine Namen, keine Gästelisten, keine Links.
 *  - Nur GET, keine Sitzung, kein Cookie, nichts wird zwischengespeichert,
 *    Suchmaschinen werden ausgeschlossen.
 *  - Jeder Eintrag trägt eine UID mit Vorsilbe „ovb-“; der Import aus Home
 *    Assistant überspringt solche Einträge, damit nichts im Kreis läuft.
 */

const ICS_TOKEN_BYTES = 32;
const ICS_TOKEN_MIN = 48;                 // kürzere Token gelten als nicht gesetzt
const ICS_FEHLVERSUCHE_MAX = 10;          // je IP im Sperrfenster
const ICS_TAGE_ZURUECK = 30;
const ICS_TAGE_VORAUS = 365;
const ICS_UID_PRAEFIX = 'ovb-';
const ICS_PRODID = '-//OV-Multitool//Kalender-Abo//DE';

/* ==================================================================== */
/*  Token und Sperre                                                      */
/* ==================================================================== */

function ics_token(): string
{
    return trim((string)setting('kalender_ics_token', ''));
}

/** Nur mit einem gesetzten, ausreichend langen Token gibt es den Feed */
function ics_aktiv(): bool
{
    return strlen(ics_token()) >= ICS_TOKEN_MIN;
}

/** Neues Token erzeugen und speichern; das alte gilt sofort nicht mehr */
function ics_token_neu(): string
{
    $token = bin2hex(random_bytes(ICS_TOKEN_BYTES));
    setting_save('kalender_ics_token', $token);
    settings_reset_cache();
    return $token;
}

function ics_token_weg(): void
{
    setting_save('kalender_ics_token', '');
    settings_reset_cache();
}

/** Stimmt das gegebene Token? Vergleich in konstanter Zeit; ohne Feed nie. Reine Funktion über den Sollwert. */
function ics_token_passt(string $gegeben, string $soll): bool
{
    if (strlen($soll) < ICS_TOKEN_MIN || $gegeben === '') {
        return false;
    }
    return hash_equals($soll, $gegeben);
}

/** Zu viele Fehlversuche von dieser Adresse? Gleiche Sperre wie die Anmeldung. */
function ics_gesperrt(string $ip): bool
{
    $minuten = max(1, setting_int('login_sperre_minuten', 15));
    $n = (int)db_val(
        'SELECT COUNT(*) FROM login_attempts WHERE username = ? AND ip = ? AND created_at > (NOW() - INTERVAL ? MINUTE)',
        ['ics-feed', substr($ip, 0, 45), $minuten],
        0
    );
    return $n >= ICS_FEHLVERSUCHE_MAX;
}

function ics_fehlversuch(string $ip): void
{
    db_exec('INSERT INTO login_attempts (username, ip) VALUES (?,?)', ['ics-feed', substr($ip, 0, 45)]);
}

/* ==================================================================== */
/*  iCalendar – reine Funktionen                                          */
/* ==================================================================== */

/** Text nach RFC 5545: Backslash, Semikolon, Komma, Zeilenumbruch; Steuerzeichen raus. Reine Funktion. */
function ics_text(string $s): string
{
    $s = (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $s);
}

/** Zeile auf 75 Oktette falten, Fortsetzung mit Leerzeichen; UTF-8-Zeichen bleiben ganz. Reine Funktion. */
function ics_zeile(string $zeile): string
{
    $out = '';
    $aktuell = '';
    $laenge = 0;
    $grenze = 75;
    foreach (preg_split('//u', $zeile, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $zeichen) {
        $b = strlen($zeichen);
        if ($laenge + $b > $grenze) {
            $out .= $aktuell . "\r\n";
            $aktuell = ' ';
            $laenge = 1;
            $grenze = 75;
        }
        $aktuell .= $zeichen;
        $laenge += $b;
    }
    return $out . $aktuell . "\r\n";
}

/** Ortszeit (Y-m-d H:i:s) nach UTC im iCalendar-Format. Reine Funktion. */
function ics_zeit_utc(string $lokal): string
{
    $d = new DateTimeImmutable($lokal);
    return $d->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
}

/** Datum (Y-m-d) im iCalendar-Format, mit $plusTage verschoben. Reine Funktion. */
function ics_datum(string $datum, int $plusTage = 0): string
{
    $d = new DateTimeImmutable(substr($datum, 0, 10));
    if ($plusTage !== 0) {
        $d = $d->modify(($plusTage > 0 ? '+' : '') . $plusTage . ' days');
    }
    return $d->format('Ymd');
}

/**
 * Den Feed bauen. Jeder Eintrag: uid, titel, beginn (Y-m-d H:i:s oder Y-m-d),
 * ende (oder null), ganztag, ort, beschreibung, kategorie. Reine Funktion.
 */
function ics_bauen(array $eintraege, string $name, ?int $jetzt = null): string
{
    $jetzt ??= time();
    $stempel = gmdate('Ymd\THis\Z', $jetzt);
    $out = ics_zeile('BEGIN:VCALENDAR')
        . ics_zeile('VERSION:2.0')
        . ics_zeile('PRODID:' . ICS_PRODID)
        . ics_zeile('CALSCALE:GREGORIAN')
        . ics_zeile('METHOD:PUBLISH')
        . ics_zeile('X-WR-CALNAME:' . ics_text($name))
        . ics_zeile('REFRESH-INTERVAL;VALUE=DURATION:PT15M')
        . ics_zeile('X-PUBLISHED-TTL:PT15M');
    foreach ($eintraege as $e) {
        $out .= ics_zeile('BEGIN:VEVENT')
            . ics_zeile('UID:' . ICS_UID_PRAEFIX . (string)$e['uid'])
            . ics_zeile('DTSTAMP:' . $stempel);
        if (!empty($e['ganztag'])) {
            $von = substr((string)$e['beginn'], 0, 10);
            $bis = substr((string)($e['ende'] ?? '') !== '' ? (string)$e['ende'] : $von, 0, 10);
            $out .= ics_zeile('DTSTART;VALUE=DATE:' . ics_datum($von))
                . ics_zeile('DTEND;VALUE=DATE:' . ics_datum(max($von, $bis), 1));   // Ende ist exklusiv: der Tag danach
        } else {
            $out .= ics_zeile('DTSTART:' . ics_zeit_utc((string)$e['beginn']));
            if ((string)($e['ende'] ?? '') !== '') {
                $out .= ics_zeile('DTEND:' . ics_zeit_utc((string)$e['ende']));
            }
        }
        $out .= ics_zeile('SUMMARY:' . ics_text((string)$e['titel']));
        if ((string)($e['ort'] ?? '') !== '') {
            $out .= ics_zeile('LOCATION:' . ics_text((string)$e['ort']));
        }
        if ((string)($e['beschreibung'] ?? '') !== '') {
            $out .= ics_zeile('DESCRIPTION:' . ics_text((string)$e['beschreibung']));
        }
        if ((string)($e['kategorie'] ?? '') !== '') {
            $out .= ics_zeile('CATEGORIES:' . ics_text((string)$e['kategorie']));
        }
        $out .= ics_zeile('END:VEVENT');
    }
    return $out . ics_zeile('END:VCALENDAR');
}

/* ==================================================================== */
/*  Was in den Feed kommt                                                 */
/* ==================================================================== */

/** Die Schalter der Verwaltung */
function ics_optionen(): array
{
    return [
        'termine'          => setting_bool('kalender_ics_termine', true),
        'veranstaltungen'  => setting_bool('kalender_ics_veranstaltungen', true),
        'besprechungen'    => setting_bool('kalender_ics_besprechungen', true),
        'fristen'          => setting_bool('kalender_ics_fristen', false),
        'beschreibung'     => setting_bool('kalender_ics_beschreibung', false),
    ];
}

/** Besprechung: Datum und Uhrzeiten zu Beginn/Ende als Ortszeit. Reine Funktion. */
function ics_besprechung_zeiten(array $m): array
{
    $datum = substr((string)$m['datum'], 0, 10);
    $endeDatum = (string)($m['ende_datum'] ?? '') !== '' ? substr((string)$m['ende_datum'], 0, 10) : $datum;
    if ((string)($m['beginn'] ?? '') === '') {
        return ['ganztag' => true, 'beginn' => $datum, 'ende' => $endeDatum];
    }
    $beginn = $datum . ' ' . substr((string)$m['beginn'], 0, 8);
    $ende = (string)($m['ende'] ?? '') !== '' ? $endeDatum . ' ' . substr((string)$m['ende'], 0, 8) : null;
    return ['ganztag' => false, 'beginn' => $beginn, 'ende' => $ende];
}

/**
 * Alle Einträge im Fenster. Persönliche Termine (Ziel Person) nie; mit
 * $fachgruppeId nur der OV, die Funktionen und diese eine Fachgruppe.
 */
function ics_eintraege(?int $fachgruppeId = null, ?int $jetzt = null, ?array $optionen = null): array
{
    $jetzt ??= time();
    $optionen ??= ics_optionen();
    $von = date('Y-m-d', $jetzt - ICS_TAGE_ZURUECK * 86400);
    $bis = date('Y-m-d', $jetzt + ICS_TAGE_VORAUS * 86400);
    $out = [];

    if ($optionen['termine']) {
        $w = "k.ziel IN ('ov', 'fachgruppe', 'funktion')";
        $p = [$bis . ' 23:59:59', $von . ' 00:00:00'];
        if ($fachgruppeId !== null) {
            $w = "(k.ziel IN ('ov', 'funktion') OR (k.ziel = 'fachgruppe' AND k.ziel_id = ?))";
            $p[] = $fachgruppeId;
        }
        $standort = function_exists('kalender_standorte_sub') ? kalender_standorte_sub('calendar_standorte', 'entry_id', 'k') : "''";
        foreach (db_all("SELECT k.id, k.titel, k.beschreibung, k.ort, k.beginn, k.ende, k.ganztag, $standort AS standort_name
                         FROM calendar_entries k WHERE k.beginn < ? AND COALESCE(k.ende, k.beginn) >= ? AND $w ORDER BY k.beginn, k.id", $p) as $t) {
            $out[] = [
                'uid'          => 'termin-' . (int)$t['id'],
                'titel'        => (string)$t['titel'],
                'beginn'       => (string)$t['beginn'],
                'ende'         => $t['ende'] !== null ? (string)$t['ende'] : null,
                'ganztag'      => (int)$t['ganztag'] === 1,
                'ort'          => implode(' · ', array_filter([trim((string)($t['standort_name'] ?? '')), trim((string)$t['ort'])], 'strlen')),
                'beschreibung' => $optionen['beschreibung'] ? trim((string)($t['beschreibung'] ?? '')) : '',
                'kategorie'    => 'Termin',
            ];
        }
    }

    if ($optionen['veranstaltungen']) {
        foreach (db_all("SELECT e.id, e.titel, e.beschreibung, e.ort, e.beginn, e.ende FROM events e
                         WHERE e.beginn < ? AND COALESCE(e.ende, e.beginn) >= ? AND e.status <> 'abgesagt' ORDER BY e.beginn, e.id",
                        [$bis . ' 23:59:59', $von . ' 00:00:00']) as $e) {
            $out[] = [
                'uid'          => 'veranstaltung-' . (int)$e['id'],
                'titel'        => (string)$e['titel'],
                'beginn'       => (string)$e['beginn'],
                'ende'         => $e['ende'] !== null ? (string)$e['ende'] : null,
                'ganztag'      => false,
                'ort'          => trim((string)$e['ort']),
                'beschreibung' => $optionen['beschreibung'] ? trim((string)($e['beschreibung'] ?? '')) : '',
                'kategorie'    => 'Veranstaltung',
            ];
        }
    }

    if ($optionen['besprechungen']) {
        foreach (db_all("SELECT m.id, m.titel, m.datum, m.beginn, m.ende, m.ende_datum, m.ort FROM meetings m
                         WHERE m.datum <= ? AND COALESCE(m.ende_datum, m.datum) >= ? AND m.status <> 'abgesagt' ORDER BY m.datum, m.beginn, m.id",
                        [$bis, $von]) as $m) {
            $z = ics_besprechung_zeiten($m);
            $out[] = [
                'uid'          => 'besprechung-' . (int)$m['id'],
                'titel'        => (string)$m['titel'],
                'beginn'       => $z['beginn'],
                'ende'         => $z['ende'],
                'ganztag'      => $z['ganztag'],
                'ort'          => trim((string)($m['ort'] ?? '')),
                'beschreibung' => '',
                'kategorie'    => 'Besprechung',
            ];
        }
    }

    if ($optionen['fristen']) {
        foreach (db_all('SELECT v.id, v.bezeichnung, v.kennzeichen, v.hu_bis, v.sp_bis, v.uvv_bis FROM vehicles v WHERE v.is_active = 1 ORDER BY v.bezeichnung') as $v) {
            foreach (['hu_bis' => 'HU', 'sp_bis' => 'SP', 'uvv_bis' => 'UVV'] as $feld => $label) {
                $datum = (string)($v[$feld] ?? '');
                if ($datum === '' || $datum < $von || $datum > $bis) {
                    continue;
                }
                $out[] = [
                    'uid'          => 'frist-' . (int)$v['id'] . '-' . strtolower($label),
                    'titel'        => $label . ' fällig: ' . (string)$v['bezeichnung'] . ((string)$v['kennzeichen'] !== '' ? ' (' . (string)$v['kennzeichen'] . ')' : ''),
                    'beginn'       => substr($datum, 0, 10),
                    'ende'         => null,
                    'ganztag'      => true,
                    'ort'          => '',
                    'beschreibung' => '',
                    'kategorie'    => 'Fahrzeugfrist',
                ];
            }
        }
    }
    return $out;
}

/* ==================================================================== */
/*  Der Abruf                                                             */
/* ==================================================================== */

/**
 * Den Abruf beantworten. Rückgabe: [HTTP-Code, Kopfzeilen, Rumpf].
 * Ohne Datenbankzugriff entscheidbar, bis das Token stimmt – so bleibt die
 * Prüfung schlank und testbar.
 */
function ics_antwort(string $methode, array $get, string $ip, ?int $jetzt = null): array
{
    $kopf = [
        'Content-Type'           => 'text/plain; charset=UTF-8',
        'Cache-Control'          => 'private, no-store',
        'X-Content-Type-Options' => 'nosniff',
        'X-Robots-Tag'           => 'noindex, nofollow',
        'Referrer-Policy'        => 'no-referrer',
    ];
    if (!in_array($methode, ['GET', 'HEAD'], true)) {
        return [405, $kopf + ['Allow' => 'GET, HEAD'], "Nur GET.\n"];
    }
    if (!ics_aktiv()) {
        return [404, $kopf, "Kein Kalender-Abo eingerichtet.\n"];
    }
    if (ics_gesperrt($ip)) {
        return [429, $kopf + ['Retry-After' => (string)(max(1, setting_int('login_sperre_minuten', 15)) * 60)], "Zu viele Fehlversuche. Später erneut probieren.\n"];
    }
    $gegeben = is_string($get['token'] ?? null) ? (string)$get['token'] : '';
    if (!ics_token_passt($gegeben, ics_token())) {
        ics_fehlversuch($ip);
        return [403, $kopf, "Ungültiges Token.\n"];
    }
    $fachgruppe = isset($get['fachgruppe']) && preg_match('/^\d{1,9}$/', (string)$get['fachgruppe']) ? (int)$get['fachgruppe'] : null;

    state_save('kalender_ics_letzter_abruf', (string)($jetzt ?? time()));
    state_save('kalender_ics_letzte_ip', substr($ip, 0, 45));

    $name = trim((string)setting('ov_name', 'Ortsverband')) . ($fachgruppe !== null && function_exists('list_label') ? ' – ' . list_label($fachgruppe, 'Fachgruppe') : '');
    $rumpf = ics_bauen(ics_eintraege($fachgruppe, $jetzt), $name, $jetzt);
    return [200, array_merge($kopf, ['Content-Type' => 'text/calendar; charset=UTF-8', 'Content-Disposition' => 'inline; filename="ov-kalender.ics"']), $methode === 'HEAD' ? '' : $rumpf];
}

/** Die Adresse des Feeds für die Verwaltung – relativ, falls keine Basisadresse bekannt ist */
function ics_adresse(?int $fachgruppe = null): string
{
    $pfad = 'ics.php?token=' . ics_token() . ($fachgruppe !== null ? '&fachgruppe=' . $fachgruppe : '');
    $basis = trim((string)setting('ha_benachrichtigung_basis_url', ''));
    if ($basis === '' || preg_match('#(^homeassistant://|/hassio/ingress/)#', $basis)) {
        return '/' . $pfad;
    }
    return rtrim(str_replace('{pfad}', '', $basis), '/') . '/' . $pfad;
}
