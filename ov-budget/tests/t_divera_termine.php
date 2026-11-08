<?php
declare(strict_types=1);
/*
 * Termine nach Divera 24/7: Nutzlast nach api_v2_event.yaml, Gruppen aus
 * /pull/all, Anlegen, Ändern, Archivieren, Abruf mit Stand, Formularteil.
 */
session_start();
date_default_timezone_set('Europe/Berlin');
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'divera_aktiv' => '1', 'divera_accesskey' => 'system-key', 'divera_personal_key' => 'persoenlich',
    'divera_termine_aktiv' => '1', 'divera_termine_push' => '1', 'divera_termine_intervall_minuten' => '30', 'divera_base_url' => 'https://app.divera247.com/api'];
$GLOBALS['rows'] = [];        // divera_termine, Schlüssel typ-ref
$GLOBALS['quellen'] = [];     // Termine, Veranstaltungen, Besprechungen
$GLOBALS['state'] = [];
$GLOBALS['anfragen'] = [];
$GLOBALS['antwort'] = null;
$GLOBALS['execs'] = [];
$GLOBALS['naechste_id'] = 10;
$GLOBALS['can'] = [];

function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0]; }
        return $r;
    }
    if (str_contains($sql, "FROM divera_termine WHERE status IN ('offen', 'fehler')")) {
        return array_values(array_filter($GLOBALS['rows'], static fn($r) => in_array($r['status'], ['offen', 'fehler'], true) && $r['versuche'] < $p[0]));
    }
    if (str_contains($sql, "FROM divera_termine WHERE status = 'gesendet'")) {
        return array_values(array_filter($GLOBALS['rows'], static fn($r) => $r['status'] === 'gesendet' && $r['divera_id'] !== null && ($r['beginn'] === null || $r['beginn'] >= $p[0]) && ($r['geprueft_am'] === null || $r['geprueft_am'] < $p[1])));
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array {
    if (str_contains($sql, 'FROM divera_termine WHERE typ = ? AND ref_id = ?')) { return $GLOBALS['rows'][$p[0] . '-' . $p[1]] ?? null; }
    return null;
}
function db_val(string $sql, array $p = [], mixed $d = null) { return str_contains($sql, 'svalue FROM settings') ? ($GLOBALS['state'][$p[0]] ?? $d) : $d; }
function db_exec(string $sql, array $p = []): int {
    $GLOBALS['execs'][] = [$sql, $p];
    if (str_contains($sql, 'INSERT INTO settings')) { $GLOBALS['state'][$p[0]] = (string)$p[1]; }
    if (str_contains($sql, 'DELETE FROM divera_termine')) { foreach ($GLOBALS['rows'] as $k => $r) { if ($r['id'] === $p[0]) { unset($GLOBALS['rows'][$k]); } } }
    return 1;
}
function db_insert(string $t, array $d): int {
    $id = $GLOBALS['naechste_id']++;
    $GLOBALS['rows'][$d['typ'] . '-' . $d['ref_id']] = $d + ['id' => $id, 'divera_id' => null, 'gesendet_am' => null, 'geprueft_am' => null, 'stand' => null];
    return $id;
}
function db_update(string $t, array $d, string $w, array $p): int {
    foreach ($GLOBALS['rows'] as $k => $r) { if ($r['id'] === $p[0]) { $GLOBALS['rows'][$k] = array_merge($r, $d); } }
    return 1;
}
function can(string $was, mixed $ctx = null): bool { return $GLOBALS['can'][$was] ?? true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'leitung']; }
final class StubStmt { public function execute(array $p): bool { return true; } }
final class StubPdo { public function prepare(string $s): StubStmt { return new StubStmt(); } }
function db(): StubPdo { return new StubPdo(); }
function kalender_find(int $id): ?array { return $GLOBALS['quellen']['termin'][$id] ?? null; }
function kalender_ort_text(array $t): string { return trim((string)($t['ort'] ?? '')); }
function event_find(?int $id): ?array { return $GLOBALS['quellen']['veranstaltung'][$id] ?? null; }
function event_ort_text(array $e): string { return trim((string)($e['ort'] ?? '')); }
function meeting_find(int $id): ?array { return $GLOBALS['quellen']['besprechung'][$id] ?? null; }
function meeting_treffen_text(array $m): string { return trim((string)($m['ort'] ?? '')); }
$GLOBALS['divera_http_attrappe'] = static function (string $methode, string $pfad, ?array $json): array {
    $GLOBALS['anfragen'][] = [$methode, $pfad, $json];
    if ($GLOBALS['antwort'] instanceof Throwable) { throw $GLOBALS['antwort']; }
    if (is_callable($GLOBALS['antwort'])) { return ($GLOBALS['antwort'])($methode, $pfad, $json); }
    return $GLOBALS['antwort'] ?? ['success' => true, 'data' => ['id' => 777]];
};

$app = dirname(__DIR__);
foreach (['util', 'settings', 'view', 'divera', 'divera_termine'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Schalter ---------- */
$check('aktiv mit Hauptschalter, Terminschalter und persönlichem Key', divera_termine_aktiv());
$GLOBALS['settings']['divera_termine_aktiv'] = '0'; settings_reset_cache();
$check('Terminschalter aus', !divera_termine_aktiv());
$GLOBALS['settings']['divera_termine_aktiv'] = '1'; $GLOBALS['settings']['divera_personal_key'] = ''; settings_reset_cache();
$check('ohne persönlichen Key aus', !divera_termine_aktiv());
$GLOBALS['settings']['divera_personal_key'] = 'persoenlich'; settings_reset_cache();

/* ---------- Optionen und Nutzlast (rein) ---------- */
$o = divera_termin_optionen_normieren(['empfaenger' => 'gruppen', 'gruppen' => ['3', 'x', 3, '0', 5], 'push' => '1']);
$check('Optionen normiert', $o === ['empfaenger' => 'gruppen', 'gruppen' => [3, 5], 'push' => true, 'rueckmeldung' => false, 'text' => false]);
$check('Gruppen ohne Auswahl heißt alle', divera_termin_optionen_normieren(['empfaenger' => 'gruppen'])['empfaenger'] === 'alle');

$quelle = ['titel' => 'Herbstübung', 'text' => 'Treffpunkt Unterkunft', 'ort' => 'Halle 1', 'beginn' => '2026-11-14 09:00:00', 'ende' => '2026-11-14 12:30:00', 'ganztag' => false, 'abgesagt' => false];
$p = divera_termin_payload($quelle, ['empfaenger' => 'gruppen', 'gruppen' => [3, 5], 'push' => true, 'rueckmeldung' => true, 'text' => true], 'ovb-termin-5');
$ev = $p['Event'];
$check('Nutzlast nach Spezifikation', $ev['foreign_id'] === 'ovb-termin-5' && $ev['title'] === 'Herbstübung' && $ev['text'] === 'Treffpunkt Unterkunft' && $ev['address'] === 'Halle 1'
    && $ev['ts_start'] === strtotime('2026-11-14 09:00:00') && $ev['ts_end'] === strtotime('2026-11-14 12:30:00') && $ev['fullday'] === false && !isset($ev['days'])
    && $ev['notification_type'] === 3 && $ev['group'] === [3, 5] && $ev['send_push'] === true && $ev['response_type'] === 3 && $ev['show_result_count'] === 2 && $ev['private_mode'] === false);
$p = divera_termin_payload($quelle, ['empfaenger' => 'alle', 'push' => false, 'rueckmeldung' => false, 'text' => false], 'ovb-termin-5');
$check('alle, ohne Push, ohne Rückmeldung, ohne Text', $p['Event']['notification_type'] === 2 && !isset($p['Event']['group']) && $p['Event']['send_push'] === false && !isset($p['Event']['response_type']) && !isset($p['Event']['text']));
$p = divera_termin_payload(['titel' => 'Klausur', 'text' => '', 'ort' => '', 'beginn' => '2026-12-05 00:00:00', 'ende' => '2026-12-06 23:59:59', 'ganztag' => true, 'abgesagt' => false], [], 'ovb-besprechung-4');
$check('ganztägig über zwei Tage', $p['Event']['fullday'] === true && $p['Event']['days'] === 2 && $p['Event']['address'] === '');
$p = divera_termin_payload(['titel' => 'x', 'text' => '', 'ort' => '', 'beginn' => '2026-12-05 10:00:00', 'ende' => '2026-12-05 09:00:00', 'ganztag' => false, 'abgesagt' => false], [], 'f');
$check('Ende vor Beginn wird auf Beginn gesetzt', $p['Event']['ts_end'] === $p['Event']['ts_start']);

/* ---------- Gruppen ---------- */
$antwortPull = ['success' => true, 'data' => ['cluster' => ['group' => ['7' => ['name' => 'Zugtrupp'], '3' => ['name' => 'Bergung'], '9' => ['name' => 'Ohne Sortierung']], 'groupsorting' => [3, 7]]]];
$check('Gruppen aus /pull/all in Reihenfolge', divera_gruppen_aus_antwort($antwortPull) === [3 => 'Bergung', 7 => 'Zugtrupp', 9 => 'Ohne Sortierung'] && divera_gruppen_aus_antwort([]) === []);
$GLOBALS['antwort'] = $antwortPull;
$GLOBALS['anfragen'] = [];
$g = divera_gruppen();
$check('Gruppen geholt und gemerkt', $g === [3 => 'Bergung', 7 => 'Zugtrupp', 9 => 'Ohne Sortierung'] && $GLOBALS['anfragen'][0] === ['GET', '/api/v2/pull/all', null] && str_contains($GLOBALS['state']['divera_gruppen'], 'Bergung'));
$GLOBALS['anfragen'] = [];
$check('aus dem Zwischenspeicher ohne Anfrage', divera_gruppen() === $g && $GLOBALS['anfragen'] === []);
$GLOBALS['antwort'] = new DiveraException('Divera antwortete mit HTTP 403');
$check('Fehler: alter Stand bleibt, Grund gemerkt', divera_gruppen(true) === $g && str_contains($GLOBALS['state']['divera_gruppen_fehler'], '403'));
$GLOBALS['antwort'] = null;

/* ---------- Quellen ---------- */
$GLOBALS['quellen'] = [
    'termin' => [5 => ['id' => 5, 'titel' => 'Herbstübung', 'beschreibung' => 'Treffpunkt', 'ort' => 'Halle 1', 'beginn' => '2026-11-14 09:00:00', 'ende' => '2026-11-14 12:30:00', 'ganztag' => 0]],
    'veranstaltung' => [9 => ['id' => 9, 'titel' => 'Tag der offenen Tür', 'beschreibung' => 'Programm', 'ort' => 'Unterkunft', 'beginn' => '2027-05-01 10:00:00', 'ende' => '2027-05-01 18:00:00', 'status' => 'geplant']],
    'besprechung' => [4 => ['id' => 4, 'titel' => 'OV-Besprechung', 'datum' => '2026-11-20', 'beginn' => '19:30:00', 'ende' => '21:00:00', 'ende_datum' => null, 'ort' => 'Schulungsraum', 'status' => 'geplant'],
                      6 => ['id' => 6, 'titel' => 'Klausur', 'datum' => '2026-12-05', 'beginn' => null, 'ende' => null, 'ende_datum' => '2026-12-06', 'ort' => '', 'status' => 'geplant']],
];
$q = divera_termin_quelle('besprechung', 4);
$check('Besprechung mit Uhrzeit', $q['beginn'] === '2026-11-20 19:30:00' && $q['ende'] === '2026-11-20 21:00:00' && !$q['ganztag'] && $q['ort'] === 'Schulungsraum' && !$q['abgesagt']);
$q = divera_termin_quelle('besprechung', 6);
$check('Besprechung ganztägig über zwei Tage', $q['ganztag'] && $q['beginn'] === '2026-12-05 00:00:00' && $q['ende'] === '2026-12-06 23:59:59');
$check('Veranstaltung und Termin', divera_termin_quelle('veranstaltung', 9)['titel'] === 'Tag der offenen Tür' && divera_termin_quelle('termin', 5)['text'] === 'Treffpunkt' && divera_termin_quelle('termin', 99) === null);

/* ---------- Anlegen, Ändern, Archivieren ---------- */
$GLOBALS['anfragen'] = [];
$r = divera_termin_speichern('termin', 5, ['empfaenger' => 'alle', 'push' => true, 'rueckmeldung' => true]);
$row = divera_termin_eintrag('termin', 5);
$check('angelegt: POST /api/v2/events, Divera-ID gemerkt', $r['ok'] && $GLOBALS['anfragen'][0][0] === 'POST' && $GLOBALS['anfragen'][0][1] === '/api/v2/events'
    && $GLOBALS['anfragen'][0][2]['Event']['foreign_id'] === 'ovb-termin-5' && $row['divera_id'] === 777 && $row['status'] === 'gesendet' && $row['beginn'] === '2026-11-14 09:00:00'
    && $row['foreign_id'] === 'ovb-termin-5' && str_contains($r['meldung'], '#777'));
$GLOBALS['anfragen'] = [];
$r = divera_termin_speichern('termin', 5, ['empfaenger' => 'alle', 'push' => false, 'rueckmeldung' => false]);
$check('geändert: PUT auf die Divera-ID', $r['ok'] && $GLOBALS['anfragen'][0][0] === 'PUT' && $GLOBALS['anfragen'][0][1] === '/api/v2/events/777' && $GLOBALS['anfragen'][0][2]['Event']['send_push'] === false
    && divera_termin_eintrag('termin', 5)['optionen']['push'] === false);
$GLOBALS['antwort'] = new DiveraException('Divera meldet einen Fehler: Limit');
$GLOBALS['anfragen'] = [];
$r = divera_termin_speichern('veranstaltung', 9, ['empfaenger' => 'alle']);
$row = divera_termin_eintrag('veranstaltung', 9);
$check('Fehler gemerkt, Versuch gezählt', !$r['ok'] && $row['status'] === 'fehler' && str_contains($row['fehler'], 'Limit') && $row['versuche'] === 1 && $row['divera_id'] === null);
$GLOBALS['antwort'] = ['success' => true, 'data' => ['id' => 888]];
$GLOBALS['anfragen'] = [];
$c = divera_termine_cron(strtotime('2026-11-08 12:00:00'));
$check('Abruf schickt Fehlgeschlagenes nach und holt den Stand', $c['gesendet'] === 1 && divera_termin_eintrag('veranstaltung', 9)['divera_id'] === 888 && divera_termin_eintrag('veranstaltung', 9)['status'] === 'gesendet'
    && $c['geprueft'] === 2 && in_array(['GET', '/api/v2/events/777', null], $GLOBALS['anfragen'], true));
$GLOBALS['antwort'] = ['success' => true, 'data' => ['id' => 777, 'count_recipients' => 12, 'count_read' => 8, 'ucr_answered' => [1, 2, 3, 4, 5]]];
foreach ($GLOBALS['rows'] as &$rr) { $rr['geprueft_am'] = null; } unset($rr);
divera_termine_cron(strtotime('2026-11-08 12:00:00'));
$row = divera_termin_eintrag('termin', 5);
$check('Stand mit Zählern', $row['stand'] === ['empfaenger' => 12, 'gelesen' => 8, 'antworten' => 5] && divera_termin_stand_text($row['stand']) === '12 Empfänger · 8 gelesen · 5 Rückmeldungen' && divera_termin_stand_text([]) === '');
$GLOBALS['anfragen'] = [];
$c = divera_termine_cron(strtotime('2026-11-08 12:10:00'));
$check('vor Ablauf des Intervalls kein neuer Abruf', $c['geprueft'] === 0 && $GLOBALS['anfragen'] === []);

// Absage einer Besprechung: archivieren, Eintrag bleibt; wieder angesetzt: neu hinaus
$GLOBALS['antwort'] = ['success' => true, 'data' => ['id' => 900]];
divera_termin_speichern('besprechung', 4, ['empfaenger' => 'alle']);
$GLOBALS['anfragen'] = [];
divera_termin_bei_absage('besprechung', 4);
$row = divera_termin_eintrag('besprechung', 4);
$check('Absage archiviert in Divera', $GLOBALS['anfragen'][0] === ['POST', '/api/v2/events/archive/900', null] && $row['status'] === 'archiviert');
$GLOBALS['anfragen'] = [];
divera_termin_bei_absage('besprechung', 4);
$check('zweite Absage schickt nichts', $GLOBALS['anfragen'] === []);
divera_termin_bei_wiederansetzung('besprechung', 4);
$row = divera_termin_eintrag('besprechung', 4);
$check('wieder angesetzt: offen, ohne alte ID', $row['status'] === 'offen' && $row['divera_id'] === null);
$GLOBALS['anfragen'] = [];
divera_termine_cron(strtotime('2026-11-08 13:00:00'));
$check('und geht als neuer Termin hinaus', $GLOBALS['anfragen'][0][0] === 'POST' && $GLOBALS['anfragen'][0][1] === '/api/v2/events');

// Veranstaltung abgesagt → beim Senden archivieren
$GLOBALS['quellen']['veranstaltung'][9]['status'] = 'abgesagt';
$GLOBALS['anfragen'] = [];
$r = divera_termin_senden(divera_termin_eintrag('veranstaltung', 9));
$check('abgesagte Veranstaltung wird archiviert statt geschickt', $r['ok'] && $GLOBALS['anfragen'][0][1] === '/api/v2/events/archive/888' && divera_termin_eintrag('veranstaltung', 9)['status'] === 'archiviert');

// Löschen entfernt den Eintrag
$GLOBALS['anfragen'] = [];
divera_termin_entfernen('termin', 5);
$check('entfernt: archiviert und Zeile gelöscht', $GLOBALS['anfragen'][0][1] === '/api/v2/events/archive/777' && divera_termin_eintrag('termin', 5) === null);
$GLOBALS['anfragen'] = [];
divera_termin_entfernen('termin', 5);
$check('nochmal entfernen tut nichts', $GLOBALS['anfragen'] === []);

// Quelle weg → Eintrag weg
$GLOBALS['rows']['termin-55'] = ['id' => 55, 'typ' => 'termin', 'ref_id' => 55, 'divera_id' => 5555, 'optionen' => '{}', 'status' => 'gesendet', 'versuche' => 0, 'fehler' => '', 'beginn' => null, 'gesendet_am' => null, 'geprueft_am' => null, 'stand' => null, 'foreign_id' => 'ovb-termin-55'];
$r = divera_termin_senden(divera_termin_eintrag('termin', 55));
$check('gelöschte Quelle räumt den Eintrag ab', !$r['ok'] && divera_termin_eintrag('termin', 55) === null);

/* ---------- Haken im Formular ---------- */
$GLOBALS['antwort'] = ['success' => true, 'data' => ['id' => 1001]];
$_SESSION['_flash'] = [];
$_POST = ['divera_feld' => '1', 'divera_an' => '1', 'divera_empfaenger' => 'gruppen', 'divera_gruppen' => ['3', '7'], 'divera_push' => '1', 'divera_rueckmeldung' => '1'];
$opt = divera_termin_optionen_aus_post();
$check('Optionen aus dem Formular', $opt['an'] === true && $opt['empfaenger'] === 'gruppen' && $opt['gruppen'] === [3, 7] && $opt['push'] && $opt['rueckmeldung'] && !$opt['text']);
divera_termin_nach_speichern('termin', 5);
$row = divera_termin_eintrag('termin', 5);
$check('Haken gesetzt: angelegt mit Gruppen', $row !== null && $row['divera_id'] === 1001 && $row['optionen']['gruppen'] === [3, 7] && ($_SESSION['_flash'][0]['type'] ?? '') === 'success');
$_POST = ['divera_feld' => '1'];
$GLOBALS['anfragen'] = [];
divera_termin_nach_speichern('termin', 5);
$check('Haken weg: archiviert und entfernt', divera_termin_eintrag('termin', 5) === null && $GLOBALS['anfragen'][0][1] === '/api/v2/events/archive/1001');
$_POST = [];
$check('ohne Abschnitt im Formular passiert nichts', divera_termin_optionen_aus_post() === null);
$GLOBALS['settings']['divera_termine_aktiv'] = '0'; settings_reset_cache();
$_POST = ['divera_feld' => '1', 'divera_an' => '1'];
$GLOBALS['anfragen'] = [];
divera_termin_nach_speichern('termin', 5);
$check('ausgeschaltet: keine Anfrage', $GLOBALS['anfragen'] === [] && divera_termin_eintrag('termin', 5) === null);
$GLOBALS['settings']['divera_termine_aktiv'] = '1'; settings_reset_cache();

/* ---------- Ansichtsteile ---------- */
$_SERVER['REQUEST_URI'] = '/?p=kalender_edit';
$_GET = ['p' => 'kalender_edit'];
$_POST = [];
$GLOBALS['rows']['termin-5'] = ['id' => 70, 'typ' => 'termin', 'ref_id' => 5, 'divera_id' => 1001, 'optionen' => json_encode(['empfaenger' => 'gruppen', 'gruppen' => [3], 'push' => true, 'rueckmeldung' => true, 'text' => false]), 'status' => 'gesendet', 'versuche' => 0, 'fehler' => '', 'beginn' => '2026-11-14 09:00:00', 'gesendet_am' => '2026-11-08 12:00:00', 'geprueft_am' => null, 'stand' => json_encode(['empfaenger' => 12, 'gelesen' => 8, 'antworten' => 5]), 'foreign_id' => 'ovb-termin-5'];
$html = render_partial('partials/divera_termin', ['typ' => 'termin', 'refId' => 5]);
$check('Formularteil mit Stand, Gruppen und Haken', str_contains($html, 'name="divera_feld"') && str_contains($html, 'name="divera_an" value="1" checked') && str_contains($html, 'in Divera (#1001)')
    && str_contains($html, '12 Empfänger') && str_contains($html, 'value="3" checked') && str_contains($html, 'Zugtrupp') && str_contains($html, 'value="gruppen" checked'));
$html = render_partial('partials/divera_termin', ['typ' => 'termin', 'refId' => null]);
$check('Formularteil neu: Vorgaben', !str_contains($html, 'name="divera_an" value="1" checked') && str_contains($html, 'name="divera_push" value="1" checked') && str_contains($html, 'value="alle" checked'));
$html = render_partial('partials/divera_stand', ['typ' => 'termin', 'refId' => 5]);
$check('Plakette auf der Seite', str_contains($html, '>in Divera<') && str_contains($html, '12 Empfänger · 8 gelesen · 5 Rückmeldungen'));
$check('Plakette ohne Eintrag leer', trim(render_partial('partials/divera_stand', ['typ' => 'termin', 'refId' => 99])) === '');
$GLOBALS['settings']['divera_termine_aktiv'] = '0'; settings_reset_cache();
$check('ausgeschaltet: kein Abschnitt', trim(render_partial('partials/divera_termin', ['typ' => 'termin', 'refId' => 5])) === '');

/* ---------- Einbau ---------- */
$check('Seiten, Hooks, Abruf, Schema, Einstellungen', str_contains((string)file_get_contents($app . '/src/pages/kalender_edit.php'), "divera_termin_nach_speichern('termin'")
    && str_contains((string)file_get_contents($app . '/src/pages/event_edit.php'), "divera_termin_nach_speichern('veranstaltung'")
    && str_contains((string)file_get_contents($app . '/src/pages/meeting_edit.php'), "divera_termin_nach_speichern('besprechung'")
    && str_contains((string)file_get_contents($app . '/src/pages/meeting_action.php'), "divera_termin_bei_absage('besprechung'")
    && str_contains((string)file_get_contents($app . '/src/lib/kalender.php'), "divera_termin_entfernen('termin'")
    && str_contains((string)file_get_contents($app . '/src/lib/events.php'), "divera_termin_entfernen('veranstaltung'")
    && str_contains((string)file_get_contents($app . '/public/cron.php'), 'divera_termine_cron')
    && str_contains((string)file_get_contents($app . '/sql/schema.sql'), 'CREATE TABLE IF NOT EXISTS divera_termine')
    && str_contains((string)file_get_contents($app . '/sql/seed.sql'), "'divera_termine_aktiv'")
    && str_contains((string)file_get_contents($app . '/views/kalender_edit.php'), "partials/divera_termin")
    && str_contains((string)file_get_contents($app . '/views/kalender_termin.php'), "partials/divera_stand"));

echo "$ok bestanden, $fail fehlgeschlagen\n";
