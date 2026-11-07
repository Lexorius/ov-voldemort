<?php
declare(strict_types=1);
/* Kalender-Abo (ICS): Token und Sperre, iCalendar-Aufbau, Auswahl der Einträge, Antwort des Abrufs, Schleifenschutz beim Import */
session_start();
date_default_timezone_set('Europe/Berlin');   // wie bootstrap.php; der Feed rechnet Ortszeit nach UTC
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'ov_name' => 'THW OV Musterstadt', 'login_sperre_minuten' => '15',
    'kalender_ics_token' => '', 'kalender_ics_termine' => '1', 'kalender_ics_veranstaltungen' => '1', 'kalender_ics_besprechungen' => '1',
    'kalender_ics_fristen' => '0', 'kalender_ics_beschreibung' => '0', 'ha_benachrichtigung_basis_url' => ''];
$GLOBALS['tabellen'] = [];
$GLOBALS['state'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['fehlversuche'] = 0;
$GLOBALS['abfragen'] = [];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0]; }
        return $r;
    }
    $GLOBALS['abfragen'][] = [$sql, $p];
    foreach ($GLOBALS['tabellen'] as $marke => $rows) {
        if (str_contains($sql, $marke)) { return is_callable($rows) ? $rows($sql, $p) : $rows; }
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) {
    if (str_contains($sql, 'svalue FROM settings')) { return $GLOBALS['state'][$p[0]] ?? $d; }
    if (str_contains($sql, 'FROM login_attempts')) { $GLOBALS['sperr_abfrage'] = $p; return $GLOBALS['fehlversuche']; }
    return $d;
}
function db_exec(string $sql, array $p = []): int {
    $GLOBALS['execs'][] = [$sql, $p];
    if (str_contains($sql, 'INSERT INTO settings')) { $GLOBALS['state'][$p[0]] = (string)$p[1]; $GLOBALS['settings'][$p[0]] = (string)$p[1]; }
    if (str_contains($sql, 'INSERT INTO login_attempts')) { $GLOBALS['fehlversuche']++; }
    return 1;
}
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'admin']; }
function list_label(?int $id, string $fallback = '–'): string { return $id === 5 ? 'Bergung' : $fallback; }
function todo_is_mine(array $todo, ?array $u = null): bool { return true; }
function todo_target_name(array $t): string { return ''; }

$app = dirname(__DIR__);
foreach (['util', 'settings', 'view', 'kalender', 'ics'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Token ---------- */
$check('ohne Token aus', !ics_aktiv() && !ics_token_passt('', '') && !ics_token_passt('abc', 'abc'));
$kurz = str_repeat('a', 40);
$check('zu kurzes Token gilt nicht', !ics_token_passt($kurz, $kurz));
$token = ics_token_neu();
settings_reset_cache();
$check('Token: 64 Hexzeichen, gespeichert, Feed an', strlen($token) === 64 && ctype_xdigit($token) && ics_token() === $token && ics_aktiv());
$check('Vergleich', ics_token_passt($token, $token) && !ics_token_passt(strtoupper($token), $token) && !ics_token_passt(substr($token, 0, 63), $token) && !ics_token_passt($token . 'x', $token));
$zweites = ics_token_neu();
settings_reset_cache();
$check('neues Token macht das alte ungültig', $zweites !== $token && !ics_token_passt($token, ics_token()) && ics_token_passt($zweites, ics_token()));
$token = $zweites;

/* ---------- iCalendar-Bausteine ---------- */
$check('Text nach RFC 5545', ics_text("a;b,c\\d\ne\r\nf\x07") === 'a\\;b\\,c\\\\d\\ne\\nf');
$lang = str_repeat('ä', 60);   // 120 Byte
$gefaltet = ics_zeile('SUMMARY:' . $lang);
$zeilen = explode("\r\n", rtrim($gefaltet));
$check('Zeilen auf 75 Oktette gefaltet, Umlaute heil', count($zeilen) === 2 && strlen($zeilen[0]) <= 75 && str_starts_with($zeilen[1], ' ') && str_replace(["\r\n ", "\r\n"], '', $gefaltet) === 'SUMMARY:' . $lang);
$check('kurze Zeile ungefaltet', ics_zeile('BEGIN:VEVENT') === "BEGIN:VEVENT\r\n");
$check('Ortszeit nach UTC (Sommer- und Winterzeit)', ics_zeit_utc('2026-07-01 10:00:00') === '20260701T080000Z' && ics_zeit_utc('2026-12-01 10:00:00') === '20261201T090000Z');
$check('Datum, Tag danach, Monatsgrenze', ics_datum('2026-11-07') === '20261107' && ics_datum('2026-11-30', 1) === '20261201' && ics_datum('2026-11-07 15:00:00') === '20261107');

$ics = ics_bauen([
    ['uid' => 'termin-5', 'titel' => 'Übung; Halle', 'beginn' => '2026-11-14 09:00:00', 'ende' => '2026-11-14 12:30:00', 'ganztag' => false, 'ort' => 'Halle 1', 'beschreibung' => "Zeile 1\nZeile 2", 'kategorie' => 'Termin'],
    ['uid' => 'frist-7-hu', 'titel' => 'HU fällig: GKW 1', 'beginn' => '2026-12-03', 'ende' => null, 'ganztag' => true, 'ort' => '', 'beschreibung' => '', 'kategorie' => 'Fahrzeugfrist'],
    ['uid' => 'termin-6', 'titel' => 'Zwei Tage', 'beginn' => '2026-11-20 00:00:00', 'ende' => '2026-11-21 23:59:59', 'ganztag' => true, 'ort' => '', 'beschreibung' => '', 'kategorie' => 'Termin'],
], 'THW OV Musterstadt', strtotime('2026-11-07 12:00:00'));
$check('Kalenderrahmen', str_starts_with($ics, "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n") && str_ends_with($ics, "END:VCALENDAR\r\n") && str_contains($ics, 'X-WR-CALNAME:THW OV Musterstadt') && str_contains($ics, 'REFRESH-INTERVAL;VALUE=DURATION:PT15M'));
$check('Termin mit Zeiten in UTC, Ort, Beschreibung, Kategorie', str_contains($ics, "UID:ovb-termin-5\r\n") && str_contains($ics, 'DTSTART:20261114T080000Z') && str_contains($ics, 'DTEND:20261114T113000Z')
    && str_contains($ics, 'SUMMARY:Übung\\; Halle') && str_contains($ics, 'LOCATION:Halle 1') && str_contains($ics, 'DESCRIPTION:Zeile 1\\nZeile 2') && str_contains($ics, 'CATEGORIES:Termin'));
$check('Ganztägig: Datum, Ende exklusiv', str_contains($ics, 'DTSTART;VALUE=DATE:20261203') && str_contains($ics, 'DTEND;VALUE=DATE:20261204')
    && str_contains($ics, 'DTSTART;VALUE=DATE:20261120') && str_contains($ics, 'DTEND;VALUE=DATE:20261122'));
$check('DTSTAMP aus der Uhr', substr_count($ics, 'DTSTAMP:20261107T110000Z') === 3 && !str_contains($ics, 'LOCATION:' . "\r\n"));
$check('Nur CRLF', !preg_match('/(?<!\r)\n/', $ics));

/* ---------- Einträge aus der Datenbank ---------- */
$jetzt = strtotime('2026-11-07 12:00:00');
$GLOBALS['tabellen'] = [
    'FROM calendar_entries k' => [['id' => 5, 'titel' => 'Übung', 'beschreibung' => 'geheim', 'ort' => 'Tor 3', 'beginn' => '2026-11-14 09:00:00', 'ende' => null, 'ganztag' => 0, 'standort_name' => 'Halle 1']],
    'FROM events e' => [['id' => 9, 'titel' => 'Tag der offenen Tür', 'beschreibung' => 'Programm', 'ort' => 'Unterkunft', 'beginn' => '2027-05-01 10:00:00', 'ende' => '2027-05-01 18:00:00']],
    'FROM meetings m' => [['id' => 3, 'titel' => 'OV-Besprechung', 'datum' => '2026-11-20', 'beginn' => '19:30:00', 'ende' => '21:00:00', 'ende_datum' => null, 'ort' => 'Schulungsraum'],
                          ['id' => 4, 'titel' => 'Klausur', 'datum' => '2026-12-05', 'beginn' => null, 'ende' => null, 'ende_datum' => '2026-12-06', 'ort' => '']],
    'FROM vehicles v' => [['id' => 7, 'bezeichnung' => 'GKW 1', 'kennzeichen' => 'THW 99020', 'hu_bis' => '2026-12-03', 'sp_bis' => '2028-01-01', 'uvv_bis' => null]],
];
$GLOBALS['abfragen'] = [];
$e = ics_eintraege(null, $jetzt);
$check('Termine, Veranstaltungen, Besprechungen – keine Fristen, keine Beschreibung', array_column($e, 'uid') === ['termin-5', 'veranstaltung-9', 'besprechung-3', 'besprechung-4']
    && $e[0]['ort'] === 'Halle 1 · Tor 3' && $e[0]['beschreibung'] === '' && $e[1]['beschreibung'] === '');
$check('Besprechung mit Uhrzeit und ganztägige Klausur über zwei Tage', $e[2]['beginn'] === '2026-11-20 19:30:00' && $e[2]['ende'] === '2026-11-20 21:00:00' && !$e[2]['ganztag']
    && $e[3]['ganztag'] && $e[3]['beginn'] === '2026-12-05' && $e[3]['ende'] === '2026-12-06');
$termineSql = $GLOBALS['abfragen'][0][0];
$check('Persönliche Termine bleiben draußen', str_contains($termineSql, "k.ziel IN ('ov', 'fachgruppe', 'funktion')") && !str_contains($termineSql, "'user'"));
$check('Fenster 30 Tage zurück, ein Jahr voraus', $GLOBALS['abfragen'][0][1][0] === '2027-11-07 23:59:59' && $GLOBALS['abfragen'][0][1][1] === '2026-10-08 00:00:00');
$GLOBALS['abfragen'] = [];
$e = ics_eintraege(5, $jetzt, ['termine' => true, 'veranstaltungen' => false, 'besprechungen' => false, 'fristen' => true, 'beschreibung' => true]);
$check('Fachgruppe: nur OV, Funktionen und diese Gruppe; Fristen und Beschreibung an', str_contains($GLOBALS['abfragen'][0][0], "(k.ziel = 'fachgruppe' AND k.ziel_id = ?)") && end($GLOBALS['abfragen'][0][1]) === 5
    && array_column($e, 'uid') === ['termin-5', 'frist-7-hu'] && $e[0]['beschreibung'] === 'geheim' && $e[1]['titel'] === 'HU fällig: GKW 1 (THW 99020)' && $e[1]['ganztag']);

/* ---------- Abruf ---------- */
$ip = '192.168.1.50';
[$code, $kopf, $rumpf] = ics_antwort('POST', ['token' => $token], $ip, $jetzt);
$check('nur GET', $code === 405 && $kopf['Allow'] === 'GET, HEAD');
[$code] = ics_antwort('GET', [], $ip, $jetzt);
$check('ohne Token: 403 und Fehlversuch gezählt', $code === 403 && $GLOBALS['fehlversuche'] === 1);
[$code] = ics_antwort('GET', ['token' => 'falsch'], $ip, $jetzt);
$check('falsches Token: 403 und Fehlversuch', $code === 403 && $GLOBALS['fehlversuche'] === 2 && $GLOBALS['sperr_abfrage'][0] === 'ics-feed' && $GLOBALS['sperr_abfrage'][1] === $ip);
[$code] = ics_antwort('GET', ['token' => ['a']], $ip, $jetzt);
$check('Token als Feld: 403', $code === 403);
$GLOBALS['fehlversuche'] = 10;
[$code, $kopf] = ics_antwort('GET', ['token' => $token], $ip, $jetzt);
$check('zehn Fehlversuche: gesperrt, auch mit richtigem Token', $code === 429 && $kopf['Retry-After'] === '900');
$GLOBALS['fehlversuche'] = 0;
$GLOBALS['execs'] = [];
[$code, $kopf, $rumpf] = ics_antwort('GET', ['token' => $token], $ip, $jetzt);
$check('richtiges Token: Kalender', $code === 200 && $kopf['Content-Type'] === 'text/calendar; charset=UTF-8' && $kopf['Cache-Control'] === 'private, no-store'
    && $kopf['X-Robots-Tag'] === 'noindex, nofollow' && str_starts_with($rumpf, 'BEGIN:VCALENDAR') && str_contains($rumpf, 'UID:ovb-termin-5') && str_contains($rumpf, 'X-WR-CALNAME:THW OV Musterstadt'));
$check('Abruf gemerkt, kein Fehlversuch', $GLOBALS['state']['kalender_ics_letzte_ip'] === $ip && (int)$GLOBALS['state']['kalender_ics_letzter_abruf'] === $jetzt && $GLOBALS['fehlversuche'] === 0);
[$code, $kopf, $rumpf] = ics_antwort('HEAD', ['token' => $token], $ip, $jetzt);
$check('HEAD ohne Rumpf', $code === 200 && $rumpf === '');
[$code, , $rumpf] = ics_antwort('GET', ['token' => $token, 'fachgruppe' => '5'], $ip, $jetzt);
$check('Fachgruppe im Namen', $code === 200 && str_contains($rumpf, 'X-WR-CALNAME:THW OV Musterstadt – Bergung'));
[$code, , $rumpf] = ics_antwort('GET', ['token' => $token, 'fachgruppe' => '5; DROP'], $ip, $jetzt);
$check('kaputte Fachgruppe wird übergangen', $code === 200 && !str_contains($rumpf, 'Bergung'));
ics_token_weg();
settings_reset_cache();
[$code] = ics_antwort('GET', ['token' => $token], $ip, $jetzt);
$check('Token zurückgezogen: 404, kein Fehlversuch', $code === 404 && $GLOBALS['fehlversuche'] === 0);

/* ---------- Adresse ---------- */
$GLOBALS['settings']['kalender_ics_token'] = str_repeat('f', 64);
settings_reset_cache();
$check('Adresse relativ ohne Basis', ics_adresse() === '/ics.php?token=' . str_repeat('f', 64) && ics_adresse(5) === '/ics.php?token=' . str_repeat('f', 64) . '&fachgruppe=5');
$GLOBALS['settings']['ha_benachrichtigung_basis_url'] = 'https://ov.example.de/multitool/';
settings_reset_cache();
$check('Adresse mit Basis', ics_adresse() === 'https://ov.example.de/multitool/ics.php?token=' . str_repeat('f', 64));
$GLOBALS['settings']['ha_benachrichtigung_basis_url'] = 'https://ha.example.de/hassio/ingress/abc';
settings_reset_cache();
$check('Ingress-Adresse taugt nicht als Basis', ics_adresse() === '/ics.php?token=' . str_repeat('f', 64));

/* ---------- Schleifenschutz beim Import aus Home Assistant ---------- */
$zeilen = kalender_ha_zeilen('calendar.ov', 'OV', [
    ['uid' => 'ovb-termin-5', 'summary' => 'Übung', 'start' => ['dateTime' => '2026-11-14T09:00:00+01:00'], 'end' => ['dateTime' => '2026-11-14T12:00:00+01:00']],
    ['uid' => 'fremd-1', 'summary' => 'Dienst', 'start' => ['dateTime' => '2026-11-15T09:00:00+01:00'], 'end' => ['dateTime' => '2026-11-15T12:00:00+01:00']],
]);
$check('Import überspringt Einträge aus dem eigenen Feed', count($zeilen) === 1 && $zeilen[0]['uid'] === 'fremd-1');

/* ---------- Einbau ---------- */
$check('Endpunkt, Einstellungen, Verwaltung', is_file($app . '/public/ics.php') && str_contains((string)file_get_contents($app . '/sql/seed.sql'), "'kalender_ics_token'")
    && str_contains((string)file_get_contents($app . '/src/pages/admin/kalender.php'), 'ics_token_neu') && str_contains((string)file_get_contents($app . '/views/admin/kalender.php'), 'Kalender-Abo'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
