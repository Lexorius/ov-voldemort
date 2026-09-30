<?php
declare(strict_types=1);
/*
 * Divera-Fahrzeugdaten: Zuordnung, Funkstatus als Fahrtenbuch, Position,
 * Besatzung und Stammdaten. Die Antworten folgen der Divera-Dokumentation
 * (api_v2_pull.yaml: /pull/vehicle-status, api_v3.yaml: /vehicles).
 */
session_start();

$GLOBALS['settings'] = [
    'waehrung' => 'EUR', 'divera_aktiv' => '1', 'divera_accesskey' => 'system-key',
    'divera_fahrzeuge_aktiv' => '1', 'divera_personal_key' => 'persoenlich',
    'divera_status_journal' => '1', 'divera_position_speichern' => '1',
];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['anfragen'] = [];
$GLOBALS['antworten'] = [];

// Unsere Fahrzeuge, wie sie in der Datenbank stehen
$GLOBALS['fahrzeuge'] = [
    ['id' => 1, 'bezeichnung' => 'MTW Zugtrupp (Sprinter)', 'funkrufname' => '21/10', 'kennzeichen' => 'THW 99020',
     'issi' => '7120087', 'opta' => '', 'ric' => '', 'divera_vehicle_id' => null, 'fms_status' => null, 'fms_at' => null,
     'stein_asset_id' => '6069'],
    ['id' => 2, 'bezeichnung' => 'GKW', 'funkrufname' => '22/51', 'kennzeichen' => 'THW 90124',
     'issi' => '7120108', 'opta' => '', 'ric' => '', 'divera_vehicle_id' => null, 'fms_status' => 2,
     'fms_at' => '2026-09-18 08:00:00', 'stein_asset_id' => '2030'],
    ['id' => 3, 'bezeichnung' => 'FüKW', 'funkrufname' => '16/11', 'kennzeichen' => 'THW 86458',
     'issi' => '7120028, 7120029, 7140977', 'opta' => '', 'ric' => '', 'divera_vehicle_id' => null,
     'fms_status' => null, 'fms_at' => null, 'stein_asset_id' => '2037'],
    ['id' => 4, 'bezeichnung' => 'Anh. DLE', 'funkrufname' => '', 'kennzeichen' => 'THW 80397', 'issi' => '',
     'opta' => '', 'ric' => '', 'divera_vehicle_id' => null, 'fms_status' => null, 'fms_at' => null, 'stein_asset_id' => '7701'],
];

function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) {
            $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0];
        }
        return $r;
    }
    if (str_contains($sql, 'FROM list_items')) {
        return [];
    }
    if (str_contains($sql, 'FROM vehicles WHERE is_active = 1')) {
        return $GLOBALS['fahrzeuge'];
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int {
    $GLOBALS['updates'][] = [$t, $d, $p];
    foreach ($GLOBALS['fahrzeuge'] as &$f) {
        if ($f['id'] === $p[0]) { $f = array_merge($f, $d); }
    }
    return 1;
}
function db_lock(string $n, int $w = 0): bool { $GLOBALS['locks'][] = ['lock', $n, $w]; return !in_array($n, $GLOBALS['besetzt'] ?? [], true); }
function db_unlock(string $n): void { $GLOBALS['locks'][] = ['unlock', $n]; }
function current_user(): ?array { return ['id' => 9, 'role' => 'admin', 'display_name' => 'Tester']; }
function can(string $what, mixed $ctx = null): bool { return true; }
class DiveraException extends RuntimeException {}
function divera_request(string $path, array $query = [], ?string $key = null): array {
    $GLOBALS['anfragen'][] = [$path, $key];
    $a = $GLOBALS['antworten'][$path] ?? null;
    if ($a instanceof Throwable) { throw $a; }
    return $a ?? [];
}
function divera_extract_rows(array $data, int $depth = 0): array { return $data['data'] ?? $data; }

$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
require $app . '/src/lib/webpush.php';
require $app . '/src/lib/vehicles.php';
require $app . '/src/lib/connector.php';
require $app . '/src/lib/divera_vehicles.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

/* ---------- FMS ---------- */
$check('Status 2', fms_label(2) === 'Status 2 – Einsatzbereit auf Wache');
$check('Status 0', fms_label(0) === 'Status 0 – Notruf');
$check('unbekannter Status', fms_label(12) === 'Status 12');
$check('kein Status', fms_label(null) === '');
$check('Farbe', fms_color(3) === '#c2410c' && fms_color(null) === '#94a3b8');

/* ---------- Normalisieren ---------- */
$check('Kennzeichen mit Leerzeichen', dv_norm_plate('THW  99020') === 'THW99020');
$check('Kennzeichen mit Bindestrich', dv_norm_plate('thw-99020') === 'THW99020');
$check('Divera-Schreibweise', dv_norm_plate('W - FW 112') === 'WFW112');
$check('mehrere ISSI', dv_issi_set('7120028, 7120029, 7140977') === ['7120028', '7120029', '7140977']);
$check('ISSI leer', dv_issi_set('') === [] && dv_issi_set(null) === []);
$check('kurze Zahl ist keine ISSI', dv_issi_set('12') === []);

/* ---------- Zuordnung ---------- */
$f = $GLOBALS['fahrzeuge'];
$check('über ISSI', dv_match(['id' => 500, 'issi' => '7120087'], $f) === 1);
$check('eine von mehreren ISSI genügt', dv_match(['id' => 501, 'issi' => '7140977'], $f) === 3);
$check('über Kennzeichen, anders geschrieben', dv_match(['id' => 502, 'number' => 'THW-80397'], $f) === 4);
$check('über Funkrufname', dv_match(['id' => 503, 'name' => '22/51'], $f) === 2);
$check('nichts passt', dv_match(['id' => 504, 'name' => '99/99', 'number' => 'HB-XX 1'], $f) === null);
$check('ISSI hat Vorrang vor Namen', dv_match(['id' => 505, 'issi' => '7120108', 'name' => '21/10'], $f) === 2);
$verknuepft = $f;
$verknuepft[1]['divera_vehicle_id'] = 777;
$check('gemerkte id gewinnt', dv_match(['id' => 777, 'name' => 'ganz anders'], $verknuepft) === 2);
$check('schon verknüpftes Fahrzeug wird nicht zweimal vergeben',
    dv_match(['id' => 778, 'name' => '22/51'], $verknuepft) === null);
$doppelt = $f;
$doppelt[3]['funkrufname'] = '22/51';
$check('zwei gleiche Funkrufnamen: keine Zuordnung', dv_match(['id' => 506, 'name' => '22/51'], $doppelt) === null);

/* ---------- Besatzung ---------- */
$check('Vor- und Nachname', dv_crew_names([['id' => 1, 'firstname' => 'Anna', 'lastname' => 'Adler']]) === ['Anna Adler']);
$check('nur name', dv_crew_names([['name' => 'Bert Brandt']]) === ['Bert Brandt']);
$check('Text', dv_crew_names(['Cem Cakir']) === ['Cem Cakir']);
$check('Unbrauchbares fällt weg', dv_crew_names([['id' => 4], null, 5, '']) === []);
$check('doppelt nur einmal', dv_crew_names(['Dora', 'Dora']) === ['Dora']);
$check('keine Liste', dv_crew_names(null) === [] && dv_crew_names('x') === []);

/* ---------- Statusauswertung ---------- */
$jetzt = mktime(14, 40, 0, 9, 18, 2026);
$ts = mktime(14, 32, 0, 9, 18, 2026);
$st = ['id' => 800, 'name' => '22/51', 'fmsstatus' => 3, 'fmsstatus_id' => 3, 'fmsstatus_note' => 'Anfahrt Bahnhof',
       'fmsstatus_ts' => $ts, 'lat' => 53.079296, 'lng' => 8.801694,
       'crew' => [['firstname' => 'Anna', 'lastname' => 'Adler'], ['firstname' => 'Bert', 'lastname' => 'Brandt']]];
$r = dv_status_update($st, $f[1], true, $jetzt);
$check('Wechsel 2 → 3 erkannt', $r['statuswechsel'] === ['alt' => 2, 'neu' => 3, 'zeit' => '2026-09-18 14:32:00', 'notiz' => 'Anfahrt Bahnhof']);
$check('Status gespeichert', $r['daten']['fms_status'] === 3 && $r['daten']['fms_at'] === '2026-09-18 14:32:00');
$check('Notiz gespeichert', $r['daten']['fms_note'] === 'Anfahrt Bahnhof');
$check('Position gespeichert', $r['daten']['geo_lat'] === 53.079296 && $r['daten']['geo_lng'] === 8.801694);
$check('Besatzung gespeichert', json_decode($r['daten']['divera_besatzung'], true) === ['Anna Adler', 'Bert Brandt']);

$gleich = array_merge($f[1], ['fms_status' => 3, 'fms_at' => '2026-09-18 14:32:00']);
$check('gleicher Stand: kein Wechsel', dv_status_update($st, $gleich, true, $jetzt)['statuswechsel'] === null);
$zwischendurch = array_merge($st, ['fmsstatus_ts' => $ts + 600]);
$check('gleicher Status, neuer Zeitpunkt: zählt als Wechsel',
    dv_status_update($zwischendurch, $gleich, true, $jetzt)['statuswechsel'] !== null);
$check('Position abgeschaltet', !isset(dv_status_update($st, $f[1], false, $jetzt)['daten']['geo_lat']));
$check('Position 0/0 wird verworfen',
    !isset(dv_status_update(array_merge($st, ['lat' => 0, 'lng' => 0]), $f[1], true, $jetzt)['daten']['geo_lat']));
$check('unsinnige Position wird verworfen',
    !isset(dv_status_update(array_merge($st, ['lat' => 123, 'lng' => 8]), $f[1], true, $jetzt)['daten']['geo_lat']));
$ohne = dv_status_update(['id' => 800, 'fmsstatus' => null], $f[1], true, $jetzt);
$check('ohne Status: nichts verändert', $ohne['statuswechsel'] === null && !array_key_exists('fms_status', $ohne['daten']));
$check('Status 0 ist ein Status', dv_status_update(array_merge($st, ['fmsstatus' => 0]), $f[1], true, $jetzt)['statuswechsel']['neu'] === 0);

/* ---------- Stammdaten ---------- */
$dv = ['id' => 900, 'vehicle_type_id' => 12, 'name' => '21/10', 'opta' => 'THW HB 21/10', 'issi' => '7120087',
       'number' => 'THW 99020', 'ric' => '123456b', 'foreign_id' => null];
$d = dv_master_update($dv, $f[0]);
$check('OPTA und RIC übernommen', $d === ['opta' => 'THW HB 21/10', 'ric' => '123456b']);
$check('Vorhandenes Kennzeichen bleibt', !isset($d['kennzeichen']));
$d = dv_master_update($dv, array_merge($f[0], ['kennzeichen' => '', 'issi' => '']));
$check('leere Felder werden gefüllt', $d['kennzeichen'] === 'THW 99020' && $d['issi'] === '7120087');
$check('unverändert: nichts', dv_master_update($dv, array_merge($f[0], ['opta' => 'THW HB 21/10', 'ric' => '123456b'])) === []);

/* ---------- Kompletter Abgleich des Funkstatus ---------- */
$GLOBALS['antworten']['/v2/pull/vehicle-status'] = ['success' => true, 'data' => [
    $st,
    ['id' => 801, 'name' => '21/10', 'fmsstatus' => 2, 'fmsstatus_ts' => $ts, 'fmsstatus_note' => '', 'crew' => [],
     'lat' => null, 'lng' => null],
    ['id' => 802, 'name' => 'Heros 99/99', 'fullname' => 'Mannschaftstransportwagen', 'fmsstatus' => 6, 'crew' => []],
]];
$GLOBALS['inserts'] = [];
$res = divera_vehicles_sync_status();
$check('Abruf ok', $res['status'] === 'ok' && $res['fahrzeuge'] === 3);
$check('mit dem System-Accesskey', $GLOBALS['anfragen'][0] === ['/v2/pull/vehicle-status', null]);
$check('eins ohne Zuordnung', $res['offen'] === 1);
$check('zwei Statuswechsel', $res['wechsel'] === 2);
$journal = array_values(array_filter($GLOBALS['inserts'], static fn($i) => $i[0] === 'vehicle_journal'));
$fms = array_values(array_filter($journal, static fn($i) => $i[1]['art'] === 'fms'));
$check('Fahrtenbuch-Eintrag mit Uhrzeit', $fms[0][1]['titel'] === 'Status 3 – Einsatz übernommen um 14:32 Uhr');
$check('alter und neuer Status', $fms[0][1]['alt_wert'] === 'Status 2 – Einsatzbereit auf Wache'
    && $fms[0][1]['neu_wert'] === 'Status 3 – Einsatz übernommen');
$check('Notiz im Journal', str_starts_with($fms[0][1]['text'], 'Anfahrt Bahnhof'));
$check('Standort beim Statuswechsel vermerkt', str_contains($fms[0][1]['text'], 'Standort: '));
$check('Quelle Divera', $fms[0][1]['quelle'] === 'divera' && $fms[0][1]['autor'] === 'Divera' && $fms[0][1]['user_id'] === null);
$verknuepfungen = array_values(array_filter($journal, static fn($i) => $i[1]['titel'] === 'Mit Divera verknüpft'));
$check('beide Fahrzeuge verknüpft', count($verknuepfungen) === 2);
$check('Divera-id gemerkt', $GLOBALS['fahrzeuge'][1]['divera_vehicle_id'] === 800
    && $GLOBALS['fahrzeuge'][0]['divera_vehicle_id'] === 801);
$check('Protokoll geschrieben', in_array('divera_log', array_column($GLOBALS['inserts'], 0), true));

// Zweiter Abruf mit gleichem Stand: kein neuer Journaleintrag
$GLOBALS['inserts'] = [];
$res = divera_vehicles_sync_status(true);
$fms2 = array_filter($GLOBALS['inserts'], static fn($i) => $i[0] === 'vehicle_journal');
$check('gleicher Stand: kein Fahrtenbuch-Eintrag', $res['wechsel'] === 0 && $fms2 === []);

// Fehler der Schnittstelle
$GLOBALS['antworten']['/v2/pull/vehicle-status'] = new DiveraException('Divera antwortete mit HTTP 403');
$res = divera_vehicles_sync_status(true);
$check('Fehler wird gemeldet', $res['status'] === 'fehler' && str_contains($res['message'], '403'));

/* ---------- Stammdaten-Abruf ---------- */
$GLOBALS['antworten']['/v3/vehicles'] = [
    ['id' => 801, 'vehicle_type_id' => 3, 'name' => '21/10', 'opta' => 'THW HB 21/10', 'issi' => '7120087',
     'number' => 'THW 99020', 'ric' => '1234567', 'foreign_id' => null],
    ['id' => 950, 'vehicle_type_id' => 4, 'name' => 'Anh', 'opta' => 'THW HB ANH', 'issi' => '',
     'number' => 'THW-80397', 'ric' => '', 'foreign_id' => null],
];
$GLOBALS['anfragen'] = [];
$GLOBALS['inserts'] = [];
$res = divera_vehicles_sync_master(true);
$check('mit dem persönlichen Accesskey', $GLOBALS['anfragen'][0] === ['/v3/vehicles', 'persoenlich']);
$check('zwei Fahrzeuge aktualisiert', $res['status'] === 'ok' && $res['geaendert'] === 2);
$check('OPTA am MTW', $GLOBALS['fahrzeuge'][0]['opta'] === 'THW HB 21/10');
$check('Anhänger über das Kennzeichen erkannt', $GLOBALS['fahrzeuge'][3]['divera_vehicle_id'] === 950
    && $GLOBALS['fahrzeuge'][3]['opta'] === 'THW HB ANH');
$opta = array_values(array_filter($GLOBALS['inserts'], static fn($i) => $i[0] === 'vehicle_journal'
    && $i[1]['feld'] === 'opta'));
$check('Journal: OPTA aus Divera', $opta[0][1]['titel'] === 'OPTA aus Divera übernommen');

$GLOBALS['antworten']['/v3/vehicles'] = new DiveraException('Divera antwortete mit HTTP 401: 2FA needed');
$res = divera_vehicles_sync_master(true);
$check('401 mit Hinweis auf den persönlichen Schlüssel', $res['status'] === 'fehler'
    && str_contains($res['message'], 'persönlichen Accesskey'));

/* ---------- Nur ein Abruf zur Zeit ---------- */
$GLOBALS['besetzt'] = ['ovb_divera_status'];
$GLOBALS['anfragen'] = [];
$res = divera_vehicles_sync_status(true);
$check('läuft schon einer: warten statt doppelt abrufen', $res['status'] === 'wartet' && $GLOBALS['anfragen'] === []);
$GLOBALS['besetzt'] = [];
$GLOBALS['locks'] = [];
$GLOBALS['antworten']['/v2/pull/vehicle-status'] = ['success' => true, 'data' => []];
divera_vehicles_sync_status(true);
$check('Sperre wird wieder freigegeben', in_array(['unlock', 'ovb_divera_status'], $GLOBALS['locks'], true));
$GLOBALS['antworten']['/v2/pull/vehicle-status'] = new DiveraException('HTTP 500');
$GLOBALS['locks'] = [];
divera_vehicles_sync_status(true);
$check('auch nach einem Fehler freigegeben', in_array(['unlock', 'ovb_divera_status'], $GLOBALS['locks'], true));

/* ---------- Karte ---------- */
$check('Kartenlink', dv_map_url(53.079296, 8.801694)
    === 'https://www.openstreetmap.org/?mlat=53.079296&mlon=8.801694#map=16/53.079296/8.801694');

/* ---------- Darstellung ---------- */
require $app . '/src/lib/uploads.php';
require $app . '/src/lib/vehicle_files.php';
require $app . '/src/lib/view.php';
function stein_value_text(string $f, mixed $w): string { return (string)$w; }
$_SERVER['REQUEST_URI'] = '/?p=vehicle&id=2';
$fz = array_merge($GLOBALS['fahrzeuge'][1], [
    'typ_label' => 'GKW', 'typ_id' => 1, 'fachgruppe_label' => 'Bergung', 'status_label' => 'Einsatzbereit',
    'status_color' => '#15803d', 'status_slug' => 'einsatzbereit', 'hersteller' => '', 'modell' => '',
    'baujahr' => null, 'erstzulassung' => null, 'fahrgestellnummer' => '', 'km_stand' => null,
    'betriebsstunden' => null, 'standort' => '', 'notiz' => '', 'is_active' => 1, 'kennung' => '',
    'stein_asset_id' => null, 'stein_sync_at' => null, 'extra' => null, 'fms_note' => 'Anfahrt Bahnhof',
    'geo_lat' => 53.079296, 'geo_lng' => 8.801694, 'geo_at' => '2026-09-18 14:40:00',
    'divera_besatzung' => '["Anna Adler","Bert Brandt"]', 'divera_sync_at' => '2026-09-18 14:40:00',
    'opta' => 'THW HB 22/51', 'ric' => '1234567',
]);
$html = render_partial('vehicle', ['vehicle' => $fz, 'fristen' => [], 'auftraege' => [], 'journal' => [],
    'journalArt' => '', 'gesamt' => 0, 'extraFields' => [], 'extra' => [], 'pruefung' => null,
    'bilder' => [], 'dokumente' => [], 'titelbild' => null]);
$check('Akte: Funkstatus', str_contains($html, 'Status 3 – Einsatz übernommen'));
$check('Akte: seit', str_contains($html, 'seit 18.09.2026 14:32'));
$check('Akte: Besatzung', str_contains($html, 'Anna Adler, Bert Brandt'));
$check('Akte: Kartenlink', str_contains($html, 'openstreetmap.org/?mlat=53.079296'));
$check('Akte: OPTA und RIC in den Stammdaten', str_contains($html, 'THW HB 22/51') && str_contains($html, '1234567'));
$check('Akte: Reiter Funkstatus', str_contains($html, '>Funkstatus<'));
$check('Akte: Verknüpfung lösen', str_contains($html, 'value="dv_unassign"'));

$html = render_partial('admin/divera_fahrzeuge', ['aktiv' => true, 'grundlage' => true, 'status' => $jetzt,
    'stamm' => 0, 'offen' => [['id' => 802, 'name' => 'Heros 99/99', 'typ' => 'MTW']],
    'fahrzeuge' => [['id' => 4, 'bezeichnung' => 'Anh. DLE', 'funkrufname' => '', 'kennzeichen' => 'THW 80397']],
    'verknuepft' => [array_merge($fz, ['divera_vehicle_id' => 800])], 'protokoll' => [
        ['created_at' => '2026-09-18 14:40:00', 'status' => 'ok', 'message' => 'Funkstatus: 3 Fahrzeug(e), 2 Wechsel.']]]);
$check('Verwaltung: offenes Fahrzeug', str_contains($html, 'Heros 99/99') && str_contains($html, 'value="dv_assign"'));
$check('Verwaltung: noch nie', str_contains($html, 'noch nie'));
$check('Verwaltung: Protokoll', str_contains($html, '2 Wechsel'));
$check('Verwaltung: OPTA in der Liste', str_contains($html, 'THW HB 22/51'));

/* ---------- Standort im Journal ---------- */
$check('Koordinaten als Text', dv_position_text(51.4501234, 7.01) === '51.45012, 7.01000');
$check('ohne Angabe leer', dv_position_text(null, 7.0) === '');
$check('Text statt Zahl leer', dv_position_text('abc', 'def') === '');

$m = dv_distance_m(51.45, 7.01, 51.45, 7.01);
$check('gleiche Stelle: keine Entfernung', $m < 0.001);
$m = dv_distance_m(51.45, 7.01, 51.46, 7.01);
$check('ein Hundertstel Grad Nord sind gut 1,1 km', $m > 1050 && $m < 1160);

$check('ausgeschaltet: nie', dv_movement_note(51.45, 7.01, 51.60, 7.30, 0) === null);
$check('erste Position zählt immer', dv_movement_note(null, null, 51.45, 7.01, 500) === 0.0);
$check('ohne neue Position nichts', dv_movement_note(51.45, 7.01, null, null, 500) === null);
$check('kleine Bewegung unter der Schwelle', dv_movement_note(51.4500, 7.0100, 51.4502, 7.0100, 500) === null);
$weit = dv_movement_note(51.45, 7.01, 51.46, 7.01, 500);
$check('große Bewegung meldet die Entfernung', $weit !== null && $weit > 1000);

$quelle = (string)file_get_contents(dirname(__DIR__) . '/src/lib/divera_vehicles.php');
$check('Statuswechsel nennt den Standort', str_contains($quelle, '"\nStandort: " . $pos'));
$check('eigener Eintrag nur ohne Statuswechsel', str_contains($quelle, "} elseif (\$bewegung !== null"));
$check('bisherige Position wird geladen', str_contains($quelle, 'fms_status, fms_at, geo_lat, geo_lng'));
$seed = (string)file_get_contents(dirname(__DIR__) . '/sql/seed.sql');
$check('Einstellung mit Vorgabe aus', str_contains($seed, "('divera_position_journal_meter','0'"));


/* ---------- Standort nur alle X Minuten ---------- */
$jetzt = 1790000000;
$check('ohne Intervall immer', dv_position_due('2026-09-23 10:00:00', 0, $jetzt));
$check('ohne bisherige Meldung immer', dv_position_due(null, 30, $jetzt));
$check('frische Meldung wartet', !dv_position_due(date('Y-m-d H:i:s', $jetzt - 300), 10, $jetzt));
$check('alte Meldung ist fällig', dv_position_due(date('Y-m-d H:i:s', $jetzt - 1200), 10, $jetzt));
$check('kaputter Zeitstempel: lieber melden', dv_position_due('kein datum', 10, $jetzt));

$fz = ['fms_status' => 2, 'fms_at' => null, 'geo_lat' => 51.4, 'geo_lng' => 7.0,
       'geo_at' => date('Y-m-d H:i:s', $jetzt - 60)];
$st = ['fmsstatus' => 2, 'lat' => 51.5, 'lng' => 7.1, 'crew' => []];
$r = dv_status_update($st, $fz, true, $jetzt, 10);
$check('innerhalb des Intervalls kein neuer Standort', !array_key_exists('geo_lat', $r['daten']));
$r = dv_status_update($st, $fz, true, $jetzt, 0);
$check('ohne Intervall wird der Standort geschrieben', ($r['daten']['geo_lat'] ?? null) === 51.5);
$fz['geo_at'] = date('Y-m-d H:i:s', $jetzt - 3600);
$r = dv_status_update($st, $fz, true, $jetzt, 10);
$check('nach Ablauf wieder', ($r['daten']['geo_lng'] ?? null) === 7.1);
$r = dv_status_update($st, $fz, false, $jetzt, 0);
$check('ohne Positionsübernahme gar nichts', !array_key_exists('geo_lat', $r['daten']));

/* ---------- Karte ---------- */
$karte = dv_map_embed_url(51.45, 7.01);
$check('Karte von OpenStreetMap', str_starts_with($karte, 'https://www.openstreetmap.org/export/embed.html?'));
$check('Ausschnitt um den Punkt', str_contains($karte, 'bbox=7.002000%2C51.446000%2C7.018000%2C51.454000'));
$check('Markierung gesetzt', str_contains($karte, 'marker=51.450000%2C7.010000'));
$seed = (string)file_get_contents(dirname(__DIR__) . '/sql/seed.sql');
$check('Einstellungen vorhanden', str_contains($seed, "('divera_position_intervall_minuten','0'")
    && str_contains($seed, "('fahrzeug_karte','1'"));

$akte = (string)file_get_contents(dirname(__DIR__) . '/views/vehicle.php');
$check('Karte in der Akte eingebunden', str_contains($akte, 'dv_map_embed_url'));
$check('Karte abschaltbar', str_contains($akte, "setting_bool('fahrzeug_karte', true)"));
$check('Karte nur mit Koordinaten', str_contains($akte,
    "\$vehicle['geo_lat'] !== null && \$vehicle['geo_lng'] !== null
                  && setting_bool('fahrzeug_karte', true)"));
$check('Verweis auf die große Karte bleibt', str_contains($akte, 'dv_map_url('));


echo "$ok bestanden, $fail fehlgeschlagen\n";
