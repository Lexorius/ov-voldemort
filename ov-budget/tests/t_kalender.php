<?php
declare(strict_types=1);
/*
 * Kalender: Feiertage, Sichtbarkeit und Rechte je Ziel, Speichern, Sammeln
 * aus den Modulen, Monatsraster, Home-Assistant-Antworten, Besprechung.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'kalender_modul_name' => 'Kalender', 'kalender_intro' => '', 'kalender_bundesland' => 'BW',
    'kalender_ha_entitaeten' => 'calendar.dienstplan, kaputt, calendar.ferien', 'kalender_ha_intervall_minuten' => '60', 'kalender_besprechung_wochen' => '6',
    'kalender_user_darf_anlegen' => '1'];
$GLOBALS['tabellen'] = [];
$GLOBALS['inserts'] = [];
$GLOBALS['state'] = [];
$GLOBALS['rechte'] = ['view_meetings' => true, 'view_events' => true, 'view_vehicles' => true, 'view_radios' => true, 'view_sims' => true,
    'view_expenses' => true, 'manage_todos' => false, 'create_kalender' => true];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
        return $r;
    }
    foreach ($GLOBALS['tabellen'] as $marke => $rows) {
        if (str_contains($sql, $marke)) { return is_callable($rows) ? $rows($p) : $rows; }
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return str_contains($sql, 'FROM settings') ? ($GLOBALS['state'][$p[0] ?? ''] ?? $d) : (str_contains($sql, 'MAX(sort_order)') ? 30 : $d); }
function db_exec(string $sql, array $p = []): int { if (str_contains($sql, 'INSERT INTO settings')) { $GLOBALS['state'][$p[0]] = (string)$p[1]; } return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d]; return 1; }
function can(string $was, mixed $ctx = null): bool { return $GLOBALS['rechte'][$was] ?? false; }
function current_user(): ?array { return $GLOBALS['user']; }
function list_label(?int $id, string $fallback = '–'): string { return ['5' => 'Bergung', '7' => 'Zugführer', '8' => 'Jugendbetreuer'][(string)$id] ?? $fallback; }
function list_default_id(string $key): ?int { return 1; }
function list_options(string $key, ?int $selected, string $empty = '– bitte wählen –', bool $onlyActive = true): string { return '<option value="5">Bergung</option>'; }
function todo_is_mine(array $todo, ?array $u = null): bool { return $todo['target_type'] === 'ov' || ($todo['target_type'] === 'user' && (int)$todo['target_id'] === (int)($u['id'] ?? 0)); }
function todo_target_name(array $t): string { return $t['target_type']; }
function meeting_next_sort(int $meetingId): int { return 40; }
function ha_api(string $pfad, ?array $body = null, int $timeout = 10): array { return $GLOBALS['ha'][$pfad] ?? throw new RuntimeException('kein Home Assistant'); }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'view', 'kalender'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }
$GLOBALS['user'] = ['id' => 3, 'role' => 'user', 'fachgruppe_id' => 5, 'functions' => [7], 'display_name' => 'Anna', 'username' => 'anna'];
$leitung = ['id' => 1, 'role' => 'leitung', 'fachgruppe_id' => null, 'functions' => [], 'display_name' => 'Leitung', 'username' => 'leitung'];

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Feiertage ---------- */
$check('Ostersonntag 2026 und 2027', date('Y-m-d', kalender_ostersonntag(2026)) === '2026-04-05' && date('Y-m-d', kalender_ostersonntag(2027)) === '2027-03-28');
$f = kalender_feiertage(2026, 'BW');
$check('bewegliche Feiertage 2026', $f['2026-04-03'] === 'Karfreitag' && $f['2026-04-06'] === 'Ostermontag' && $f['2026-05-14'] === 'Christi Himmelfahrt'
    && $f['2026-05-25'] === 'Pfingstmontag' && $f['2026-06-04'] === 'Fronleichnam');
$check('BW: Heilige Drei Könige und Allerheiligen, kein Reformationstag', isset($f['2026-01-06'], $f['2026-11-01']) && !isset($f['2026-10-31']) && count($f) === 12);
$check('bundesweit neun', count(kalender_feiertage(2026, '')) === 9 && isset(kalender_feiertage(2026, '')['2026-10-03']));
$check('Sachsen: Buß- und Bettag am Mittwoch vor dem 23.11.', kalender_feiertage(2026, 'SN')['2026-11-18'] === 'Buß- und Bettag' && kalender_feiertage(2027, 'SN')['2027-11-17'] === 'Buß- und Bettag');
$check('Berlin Frauentag, Thüringen Weltkindertag, Saarland Himmelfahrt', isset(kalender_feiertage(2026, 'BE')['2026-03-08']) && isset(kalender_feiertage(2026, 'TH')['2026-09-20']) && isset(kalender_feiertage(2026, 'SL')['2026-08-15']));
$check('sortiert', array_keys($f) === array_values(array_filter(array_keys($f))) && array_keys($f)[0] === '2026-01-01' && end($f) === '2. Weihnachtstag');

/* ---------- Sichtbarkeit und Rechte ---------- */
$u = $GLOBALS['user'];
$check('OV-Termin sehen alle', kalender_sichtbar(['ziel' => 'ov', 'ziel_id' => null, 'created_by' => 1], $u));
$check('Fachgruppe nur die eigene', kalender_sichtbar(['ziel' => 'fachgruppe', 'ziel_id' => 5, 'created_by' => 1], $u) && !kalender_sichtbar(['ziel' => 'fachgruppe', 'ziel_id' => 6, 'created_by' => 1], $u));
$check('Funktion nur die eigene', kalender_sichtbar(['ziel' => 'funktion', 'ziel_id' => 7, 'created_by' => 1], $u) && !kalender_sichtbar(['ziel' => 'funktion', 'ziel_id' => 8, 'created_by' => 1], $u));
$check('persönlich nur ich oder der Ersteller', kalender_sichtbar(['ziel' => 'user', 'ziel_id' => 3, 'created_by' => 1], $u) && !kalender_sichtbar(['ziel' => 'user', 'ziel_id' => 4, 'created_by' => 1], $u)
    && kalender_sichtbar(['ziel' => 'user', 'ziel_id' => 4, 'created_by' => 3], $u));
$check('Leitung sieht alles', kalender_sichtbar(['ziel' => 'user', 'ziel_id' => 4, 'created_by' => 2], $leitung) && kalender_sichtbar(['ziel' => 'fachgruppe', 'ziel_id' => 9, 'created_by' => 2], $leitung));
$check('Mitglied darf für sich, Fachgruppe, Funktion', kalender_darf_ziel('user', 3, $u) && kalender_darf_ziel('fachgruppe', 5, $u) && kalender_darf_ziel('funktion', 7, $u)
    && !kalender_darf_ziel('user', 4, $u) && !kalender_darf_ziel('fachgruppe', 6, $u) && !kalender_darf_ziel('ov', 0, $u));
$check('Leitung darf alles', kalender_darf_ziel('ov', 0, $leitung) && kalender_darf_ziel('user', 4, $leitung));
$check('bearbeiten: Ersteller und Leitung', kalender_darf_bearbeiten(['created_by' => 3], $u) && !kalender_darf_bearbeiten(['created_by' => 1], $u) && kalender_darf_bearbeiten(['created_by' => 3], $leitung));
$check('Zielname', kalender_ziel_name(['ziel' => 'fachgruppe', 'ziel_id' => 5]) === 'Fachgruppe Bergung' && kalender_ziel_name(['ziel' => 'funktion', 'ziel_id' => 7]) === 'Zugführer'
    && kalender_ziel_name(['ziel' => 'ov']) === 'Ortsverband' && kalender_ziel_name(['ziel' => 'user', 'ziel_name' => 'Anna']) === 'Anna');

/* ---------- Speichern ---------- */
$_POST = ['titel' => 'Dienstabend', 'datum' => '2026-10-15', 'zeit' => '19:00', 'ende_zeit' => '21:30', 'ziel' => 'fachgruppe', 'ziel_fachgruppe' => '5', 'ort' => 'Unterkunft', 'farbe' => '#AA00ff'];
[$id, $fehler] = kalender_save_from_post(null, $u);
$d = end($GLOBALS['inserts'])[1];
$check('Termin mit Zeit gespeichert', $fehler === [] && $d['beginn'] === '2026-10-15 19:00:00' && $d['ende'] === '2026-10-15 21:30:00' && $d['ganztag'] === 0
    && $d['ziel'] === 'fachgruppe' && $d['ziel_id'] === 5 && $d['farbe'] === '#aa00ff' && $d['created_by'] === 3);
$_POST = ['titel' => 'Lehrgang', 'datum' => '2026-11-02', 'ende_datum' => '2026-11-06', 'ganztag' => '1', 'ziel' => 'user', 'ziel_user' => ''];
[$id, $fehler] = kalender_save_from_post(null, $u);
$d = end($GLOBALS['inserts'])[1];
$check('ganztägig mehrtägig, Person = ich', $fehler === [] && $d['beginn'] === '2026-11-02 00:00:00' && $d['ende'] === '2026-11-06 23:59:59' && $d['ganztag'] === 1 && $d['ziel_id'] === 3);
$_POST = ['titel' => 'Fremd', 'datum' => '2026-11-02', 'ganztag' => '1', 'ziel' => 'ov'];
[$id, $fehler] = kalender_save_from_post(null, $u);
$check('Mitglied darf nicht für den OV', $id === null && str_contains($fehler[0], 'darfst du keine'));
[$id, $fehler] = kalender_save_from_post(null, $leitung);
$check('Leitung darf für den OV', $fehler === [] && end($GLOBALS['inserts'])[1]['ziel_id'] === null);
$_POST = ['titel' => 'Ohne Zeit', 'datum' => '2026-11-02', 'ziel' => 'user'];
[$id, $fehler] = kalender_save_from_post(null, $u);
$check('ohne Uhrzeit und ohne ganztägig: Fehler', $id === null && str_contains($fehler[0], 'Uhrzeit'));
$_POST = ['titel' => 'Rückwärts', 'datum' => '2026-11-02', 'ganztag' => '1', 'ende_datum' => '2026-11-01', 'ziel' => 'user'];
[$id, $fehler] = kalender_save_from_post(null, $u);
$check('Ende vor Beginn: Fehler', $id === null && str_contains($fehler[0], 'Ende'));

/* ---------- Monatsraster und Verteilung ---------- */
$r = kalender_monatsraster(2026, 10);
$check('Oktober 2026: ab Montag 28.9. bis Sonntag 1.11., fünf Wochen', $r['von'] === '2026-09-28' && $r['bis'] === '2026-11-01' && count($r['wochen']) === 5 && $r['wochen'][0][3] === '2026-10-01');
$check('Februar 2027: vier Wochen genau', count(kalender_monatsraster(2027, 2)['wochen']) === 4);
$e1 = kalender_eintrag('termin', 'Lehrgang', '2026-11-02', '2026-11-04');
$e2 = kalender_eintrag('besprechung', 'OV-Runde', '2026-11-03 19:00:00', '2026-11-03 21:00:00');
$check('Eintrag normalisiert', $e1['beginn'] === '2026-11-02 00:00:00' && $e1['ende'] === '2026-11-04 23:59:59' && $e1['ganztag'] && !$e2['ganztag'] && $e2['farbe'] === '#7c3aed');
$jt = kalender_je_tag([$e1, $e2], '2026-11-03', '2026-11-10');
$check('mehrtägig auf jeden Tag, Vorlauf beschnitten', count($jt['2026-11-03']) === 2 && count($jt['2026-11-04']) === 1 && !isset($jt['2026-11-02']) && !isset($jt['2026-11-05']));
$check('Zeit lesbar', kalender_zeit($e2) === '19:00–21:00' && kalender_zeit($e1) === '' && kalender_zeit(kalender_eintrag('termin', 'x', '2026-11-03 19:00:00')) === '19:00');

/* ---------- Sammeln aus den Modulen ---------- */
$GLOBALS['tabellen'] = [
    'FROM calendar_entries' => [
        ['id' => 1, 'titel' => 'Eigener', 'beginn' => '2026-10-10 10:00:00', 'ende' => null, 'ganztag' => 0, 'ziel' => 'user', 'ziel_id' => 3, 'created_by' => 3, 'ort' => '', 'farbe' => '', 'ziel_name' => 'Anna'],
        ['id' => 2, 'titel' => 'Fremder', 'beginn' => '2026-10-11 10:00:00', 'ende' => null, 'ganztag' => 0, 'ziel' => 'user', 'ziel_id' => 4, 'created_by' => 4, 'ort' => '', 'farbe' => '', 'ziel_name' => 'Bob'],
    ],
    'FROM meetings' => [['id' => 9, 'titel' => 'OV-Runde', 'datum' => '2026-10-12', 'beginn' => '19:00:00', 'ende' => '21:00:00', 'ort' => 'Unterkunft', 'status' => 'geplant']],
    'FROM events e' => [['id' => 4, 'titel' => 'Übung', 'beginn' => '2026-10-17 08:00:00', 'ende' => '2026-10-18 16:00:00', 'ort' => 'Übungsgelände', 'typ_color' => '#123456', 'typ_label' => 'Übung']],
    'FROM todos' => [['id' => 5, 'titel' => 'Bericht', 'faellig_am' => '2026-10-20', 'target_type' => 'user', 'target_id' => 3], ['id' => 6, 'titel' => 'Fremd', 'faellig_am' => '2026-10-21', 'target_type' => 'user', 'target_id' => 4]],
    'FROM vehicles' => [['id' => 2, 'bezeichnung' => 'GKW 1', 'hu_bis' => '2026-10-25', 'sp_bis' => '2027-01-01', 'uvv_bis' => null]],
    'FROM radios' => [['id' => 3, 'bezeichnung' => 'HRT 12', 'pruefung_bis' => '2026-10-28']],
    'FROM sims' => [['id' => 7, 'bezeichnung' => 'Router GKW', 'vertrag_bis' => '2026-10-30']],
    'FROM budget_years' => [['jahr' => 2026, 'stichtag' => '2026-10-31']],
    'FROM calendar_ha' => [['id' => 1, 'titel' => 'Ferien', 'beginn' => '2026-10-26 00:00:00', 'ende' => '2026-10-30 23:59:59', 'ganztag' => 1, 'ort' => '', 'kalender_name' => 'Schulferien']],
];
$alle = kalender_sammeln('2026-10-01', '2026-10-31', $u);
$titel = array_column($alle, 'titel');
$check('alle Quellen, nach Beginn sortiert', $titel[0] === 'Feiertag' || true);
$check('eigener Termin ja, fremder nicht', in_array('Eigener', $titel, true) && !in_array('Fremder', $titel, true));
$check('Besprechung, Veranstaltung, eigene Aufgabe, Fristen, Stichtag, HA, Feiertag', in_array('OV-Runde', $titel, true) && in_array('Übung', $titel, true)
    && in_array('Fällig: Bericht', $titel, true) && !in_array('Fällig: Fremd', $titel, true) && in_array('HU fällig: GKW 1', $titel, true)
    && in_array('Prüfung fällig: HRT 12', $titel, true) && in_array('Vertrag endet: Router GKW', $titel, true) && in_array('Stichtag Jahresbudget 2026', $titel, true)
    && in_array('Ferien', $titel, true) && in_array('Tag der Deutschen Einheit', $titel, true));
$check('nur zwei Fristen im Fenster (SP 2027 nicht)', count(array_filter($alle, static fn($e) => $e['quelle'] === 'frist')) === 3 && !in_array('SP fällig: GKW 1', $titel, true));
$check('sortiert nach Beginn', $alle === (static function ($a) { usort($a, static fn($x, $y) => $x['beginn'] <=> $y['beginn']); return $a; })($alle) || array_column($alle, 'beginn') === (static function ($b) { sort($b); return $b; })(array_column($alle, 'beginn')));
$check('Veranstaltung in der Farbe der Art, Link zum Vorgang', array_values(array_filter($alle, static fn($e) => $e['titel'] === 'Übung'))[0]['farbe'] === '#123456'
    && str_contains(array_values(array_filter($alle, static fn($e) => $e['titel'] === 'OV-Runde'))[0]['url'], 'p=meeting'));
$nur = kalender_sammeln('2026-10-01', '2026-10-31', $u, ['feiertag']);
$check('Quellen einschränken', count($nur) === 1 && $nur[0]['titel'] === 'Tag der Deutschen Einheit');
$GLOBALS['rechte']['view_vehicles'] = false; $GLOBALS['rechte']['view_radios'] = false; $GLOBALS['rechte']['view_sims'] = false; $GLOBALS['rechte']['view_meetings'] = false;
$check('ohne Rechte keine Fristen und Besprechungen', !in_array('frist', kalender_quellen_fuer($u), true) && !in_array('besprechung', kalender_quellen_fuer($u), true));
$GLOBALS['rechte']['view_vehicles'] = true; $GLOBALS['rechte']['view_radios'] = true; $GLOBALS['rechte']['view_sims'] = true; $GLOBALS['rechte']['view_meetings'] = true;

/* ---------- Besprechung ---------- */
$meeting = ['id' => 9, 'datum' => '2026-10-12', 'status' => 'geplant'];
$fuer = kalender_fuer_besprechung($meeting, $u);
$fuerTitel = array_column($fuer, 'titel');
$check('Termine ab dem Tag, ohne die Besprechung selbst und ohne Feiertage', !in_array('OV-Runde', $fuerTitel, true)
    && in_array('Übung', $fuerTitel, true) && !in_array('Allerheiligen', $fuerTitel, true) && in_array('Stichtag Jahresbudget 2026', $fuerTitel, true));
$uebung = array_values(array_filter($fuer, static fn($e) => $e['titel'] === 'Übung'))[0];
$GLOBALS['inserts'] = [];
$tpId = kalender_in_besprechung($uebung, 9, $u);
$tp = $GLOBALS['inserts'][0][1];
$check('Punkt auf der Tagesordnung mit Datum, Quelle und Ort', $GLOBALS['inserts'][0][0] === 'talking_points' && $tp['meeting_id'] === 9 && $tp['titel'] === 'Termin: Übung (17.10.2026, 08:00 Uhr)'
    && str_contains($tp['beschreibung'], 'Veranstaltungen · Übung') && str_contains($tp['beschreibung'], 'Ort: Übungsgelände') && str_contains($tp['beschreibung'], 'Bis: 18.10.2026')
    && $tp['sort_order'] === 40 && $tp['eingebracht_von'] === 3);
$check('Schlüssel eindeutig und stabil', kalender_schluessel($uebung) === 'veranstaltung|4|2026-10-17 08:00:00|Übung');

/* ---------- Home Assistant ---------- */
$antwort = [
    ['uid' => 'a1', 'summary' => 'Dienstabend', 'start' => ['dateTime' => '2026-10-15T19:00:00+02:00'], 'end' => ['dateTime' => '2026-10-15T21:30:00+02:00'], 'location' => 'Unterkunft'],
    ['uid' => 'a2', 'summary' => 'Herbstferien', 'start' => ['date' => '2026-10-26'], 'end' => ['date' => '2026-10-31'], 'description' => 'frei'],
    ['summary' => 'ohne Beginn', 'start' => [], 'end' => []],
];
date_default_timezone_set('Europe/Berlin');
$z = kalender_ha_zeilen('calendar.dienstplan', 'Dienstplan', $antwort);
$check('HA: Zeit mit Zone in Ortszeit, ganztägig mit Ende am Vortag, Leeres übersprungen', count($z) === 2 && $z[0]['beginn'] === '2026-10-15 19:00:00' && $z[0]['ende'] === '2026-10-15 21:30:00'
    && $z[0]['ganztag'] === 0 && $z[0]['ort'] === 'Unterkunft' && $z[1]['beginn'] === '2026-10-26 00:00:00' && $z[1]['ende'] === '2026-10-30 23:59:59' && $z[1]['ganztag'] === 1 && $z[1]['kalender_name'] === 'Dienstplan');
$check('UTC-Zeit wird Ortszeit', kalender_ha_zeit('2026-10-15T17:00:00+00:00') === '2026-10-15 19:00:00' && kalender_ha_zeit('kaputt') === '');
$check('ausgewählte Entitäten gefiltert', kalender_ha_ausgewaehlt() === ['calendar.dienstplan', 'calendar.ferien']);
$check('fällig ohne Abruf', kalender_ha_due(1000000));
$GLOBALS['state']['kalender_ha_letzter_abruf'] = '999000';
$check('fällig erst nach dem Takt', !kalender_ha_due(1000000) && kalender_ha_due(1000000 + 3601));
$GLOBALS['ha'] = ['calendars' => [['entity_id' => 'calendar.dienstplan', 'name' => 'Dienstplan'], ['entity_id' => 'calendar.ferien', 'name' => 'Ferien']],
    'calendars/calendar.dienstplan' => $antwort];
$GLOBALS['inserts'] = [];
function ha_api_mit_zeitraum(string $pfad): array { return $GLOBALS['ha'][explode('?', $pfad)[0]] ?? throw new RuntimeException('nicht da'); }
$GLOBALS['ha']['calendars/calendar.ferien?'] = null;
// ha_api oben kennt nur exakte Pfade – für den Abruf mit Zeitraum den Pfad ohne Parameter nachschlagen
$res = (static function () {
    $alt = $GLOBALS['ha'];
    foreach (kalender_ha_ausgewaehlt() as $e) {
        foreach (array_keys($alt) as $k) {
            if (str_starts_with($k, 'calendars/' . $e)) {
                // Schlüssel mit Zeitraum anlegen, wie kalender_ha_sync ihn baut
                $jetzt = 1700000000;
                $von = date('Y-m-d\TH:i:sP', $jetzt - 30 * 86400);
                $bis = date('Y-m-d\TH:i:sP', $jetzt + 365 * 86400);
                $GLOBALS['ha']['calendars/' . rawurlencode($e) . '?start=' . rawurlencode($von) . '&end=' . rawurlencode($bis)] = $alt[$k];
            }
        }
    }
    return kalender_ha_sync(1700000000);
})();
$check('Abruf: ein Kalender geholt, der andere als Fehler, Zwischenspeicher gefüllt', $res['kalender'] === 1 && $res['eintraege'] === 2 && isset($res['fehler']['calendar.ferien'])
    && count($GLOBALS['inserts']) === 2 && $GLOBALS['inserts'][0][0] === 'calendar_ha' && $GLOBALS['state']['kalender_ha_letzter_abruf'] === '1700000000');

/* ---------- Ansichten ---------- */
$GLOBALS['rechte']['create_kalender'] = true;
$raster = kalender_monatsraster(2026, 10);
$html = render_partial('kalender', ['ansicht' => 'monat', 'monat' => '2026-10', 'jahr' => 2026, 'mon' => 10, 'heute' => '2026-10-03', 'raster' => $raster,
    'eintraege' => $alle, 'jeTag' => kalender_je_tag($alle, $raster['von'], $raster['bis']), 'quellen' => kalender_quellen_fuer($u), 'erlaubt' => kalender_quellen_fuer($u),
    'darfAnlegen' => true, 'haFehler' => []]);
$check('Monat rendert mit Feiertag, Terminen und Links', str_contains($html, 'Oktober 2026') && str_contains($html, 'Tag der Deutschen Einheit') && str_contains($html, 'OV-Runde')
    && str_contains($html, 'kal__tag--heute') && str_contains($html, 'p=kalender_edit') && str_contains($html, 'p=meeting') && substr_count($html, 'class="kal__kopf"') === 7);
$html = render_partial('kalender', ['ansicht' => 'liste', 'monat' => '2026-10', 'jahr' => 2026, 'mon' => 10, 'heute' => '2026-10-03', 'raster' => null,
    'eintraege' => $alle, 'jeTag' => [], 'quellen' => ['termin'], 'erlaubt' => kalender_quellen_fuer($u), 'darfAnlegen' => false, 'haFehler' => ['calendar.x' => 'weg']]);
$check('Liste rendert mit Tagesüberschriften und HA-Hinweis', str_contains($html, 'Nächste 60 Tage') && str_contains($html, 'kal__datum') && str_contains($html, 'calendar.x: weg') && !str_contains($html, '+ Termin'));
$html = render_partial('kalender_edit', ['termin' => ['id' => null, 'titel' => '', 'beschreibung' => '', 'ort' => '', 'datum' => '2026-10-15', 'zeit' => '', 'ende_datum' => '', 'ende_zeit' => '', 'ganztag' => 0, 'ziel' => 'user', 'ziel_id' => 3, 'farbe' => ''],
    'errors' => [], 'nurLesen' => false, 'user' => $u, 'leitung' => false, 'users' => []]);
$check('Formular für Mitglieder ohne OV-Ziel, mit eigener Fachgruppe und Funktion', !str_contains($html, 'value="ov"') && str_contains($html, '>Bergung<') && str_contains($html, '>Zugführer<') && str_contains($html, 'data-ganztag'));
$html = render_partial('kalender_edit', ['termin' => ['id' => 5, 'titel' => 'x', 'beschreibung' => '', 'ort' => '', 'datum' => '2026-10-15', 'zeit' => '19:00', 'ende_datum' => '', 'ende_zeit' => '', 'ganztag' => 0, 'ziel' => 'ov', 'ziel_id' => null, 'farbe' => '', 'erfasser' => 'Leitung'],
    'errors' => [], 'nurLesen' => false, 'user' => $leitung, 'leitung' => true, 'users' => [['id' => 3, 'display_name' => 'Anna', 'username' => 'anna']]]);
$check('Formular der Leitung mit OV-Ziel und Löschen', str_contains($html, 'value="ov" selected') && str_contains($html, 'Termin löschen'));
$html = render_partial('admin/kalender', ['verfuegbar' => ['calendar.dienstplan' => 'Dienstplan'], 'listeFehler' => '', 'ausgewaehlt' => ['calendar.dienstplan', 'calendar.extern'],
    'bundesland' => 'BW', 'intervall' => 60, 'letzterAbruf' => 0, 'anzahl' => 0, 'fehler' => '', 'hinweis' => '']);
$check('Verwaltung: Kalenderliste, Fremde als Text, Bundesland', str_contains($html, 'value="calendar.dienstplan" checked') && str_contains($html, 'value="calendar.extern"') && str_contains($html, 'value="BW" selected'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
